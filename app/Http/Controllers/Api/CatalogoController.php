<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Http\Requests\ListadoRequest;
use App\Models\Market\Mercado;
use App\Models\Sports\Equipo;
use App\Models\Sports\Estadio;
use App\Models\Sports\Jornada;
use App\Models\Sports\Jugador;
use App\Models\Sports\Liga;
use App\Models\Sports\Partido;
use App\Models\Sports\Servicio;
use App\Models\Sports\Temporada;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/** Catálogo público de solo lectura (MySQL) y mercados/cuotas (MongoDB). */
class CatalogoController extends Controller
{
    use RespondsJson;

    public function deportes(): JsonResponse
    {
        return $this->ok(Servicio::query()->where('activo', true)->orderBy('nombre')->get());
    }

    public function ligas(ListadoRequest $r): JsonResponse
    {
        $q = Liga::query()->with('pais')->where('activa', true)->orderBy('nombre');
        $r->filled('servicio_codigo') && $q->where('servicio_codigo', $r->string('servicio_codigo'));
        $r->filled('pais_codigo') && $q->where('pais_codigo', strtoupper($r->string('pais_codigo')));
        $r->filled('q') && $q->where('nombre', 'like', '%' . $this->like($r->string('q')) . '%');

        return $this->ok($this->cacheado('ligas', $r, fn () => $this->paginated($q->paginate($r->porPagina()))->getData(true)['data'], 300));
    }

    public function temporadas(ListadoRequest $r): JsonResponse
    {
        $q = Temporada::query()->orderByDesc('fecha_inicio');
        $r->filled('liga_id') && $q->where('liga_id', $r->integer('liga_id'));

        return $this->paginated($q->paginate($r->porPagina()));
    }

    public function equipos(ListadoRequest $r): JsonResponse
    {
        $q = Equipo::query()->with(['ciudad', 'estadio'])->orderBy('nombre');
        $r->filled('q') && $q->where('nombre', 'like', '%' . $this->like($r->string('q')) . '%');
        $r->filled('ciudad_id') && $q->where('ciudad_id', $r->integer('ciudad_id'));
        if ($r->filled('temporada_id')) {
            $q->whereIn('id_equipo', fn ($s) => $s->select('equipo_id')->from('equipo_liga_temporada')->where('temporada_id', $r->integer('temporada_id')));
        }

        return $this->paginated($q->paginate($r->porPagina()));
    }

    public function estadios(ListadoRequest $r): JsonResponse
    {
        $q = Estadio::query()->with('ciudad')->orderBy('nombre');
        $r->filled('q') && $q->where('nombre', 'like', '%' . $this->like($r->string('q')) . '%');
        $r->filled('ciudad_id') && $q->where('ciudad_id', $r->integer('ciudad_id'));

        return $this->paginated($q->paginate($r->porPagina()));
    }

    public function jugadores(ListadoRequest $r): JsonResponse
    {
        $q = Jugador::query()->with(['equipo', 'posicion', 'pais'])->orderBy('primer_apellido')->orderBy('primer_nombre');
        $r->filled('equipo_id') && $q->where('equipo_actual_id', $r->integer('equipo_id'));
        $r->filled('pais_codigo') && $q->where('pais_codigo', strtoupper($r->string('pais_codigo')));
        $r->filled('q') && $q->where(fn ($w) => $w
            ->where('primer_nombre', 'like', '%' . $this->like($r->string('q')) . '%')
            ->orWhere('primer_apellido', 'like', '%' . $this->like($r->string('q')) . '%'));

        return $this->paginated($q->paginate($r->porPagina()));
    }

    public function jornadas(ListadoRequest $r): JsonResponse
    {
        $q = Jornada::query()->orderBy('temporada_id')->orderBy('numero');
        $r->filled('temporada_id') && $q->where('temporada_id', $r->integer('temporada_id'));

        return $this->paginated($q->paginate($r->porPagina()));
    }

    public function partidos(ListadoRequest $r): JsonResponse
    {
        $q = Partido::query()->with(['local', 'visitante', 'estadio', 'estado', 'jornada.temporada.liga'])
            ->orderBy('fecha_hora', $r->input('orden', 'asc'));

        if ($r->filled('q')) {
            $like = '%' . $this->like($r->string('q')) . '%';
            $q->where(fn ($w) => $w
                ->whereHas('local', fn ($e) => $e->where('nombre', 'like', $like))
                ->orWhereHas('visitante', fn ($e) => $e->where('nombre', 'like', $like))
                ->orWhereHas('jornada.temporada.liga', fn ($l) => $l->where('nombre', 'like', $like)));
        }
        $r->filled('jornada_id') && $q->where('jornada_id', $r->integer('jornada_id'));
        $r->filled('estado') && $q->where('estado_codigo', strtoupper($r->string('estado')));
        $r->filled('desde') && $q->where('fecha_hora', '>=', $r->date('desde')->startOfDay());
        $r->filled('hasta') && $q->where('fecha_hora', '<=', $r->date('hasta')->endOfDay());
        $r->filled('equipo_id') && $q->where(fn ($w) => $w
            ->where('equipo_local_id', $r->integer('equipo_id'))
            ->orWhere('equipo_visitante_id', $r->integer('equipo_id')));
        $r->filled('temporada_id') && $q->whereHas('jornada', fn ($j) => $j->where('temporada_id', $r->integer('temporada_id')));
        $r->filled('liga_id') && $q->whereHas('jornada.temporada', fn ($t) => $t->where('liga_id', $r->integer('liga_id')));

        return $this->ok($this->cacheado('partidos', $r, function () use ($q, $r) {
            $page = $q->paginate($r->porPagina());
            // Una sola consulta a MongoDB para todo el listado (evita pedir /mercados partido por partido).
            $resumen = $r->boolean('con_mercados') ? $this->resumenMercados($page->getCollection()->pluck('id_partido')->all()) : null;

            return $this->paginated($page, function (Partido $p) use ($resumen) {
                $fila = $p->toArray();
                if ($resumen !== null) {
                    $fila += $resumen[$p->id_partido] ?? ['mercado_principal' => null, 'mercados_total' => 0];
                }

                return $fila;
            })->getData(true)['data'];
        }));
    }

    public function partido(int $id): JsonResponse
    {
        $partido = Partido::query()->with(['local', 'visitante', 'estadio', 'estado', 'jornada.temporada.liga'])->findOrFail($id);

        return $this->ok($partido);
    }

    public function mercados(int $id): JsonResponse
    {
        Partido::query()->findOrFail($id);
        $mercados = Cache::remember('catalogo:mercados:' . $id, $this->ttl(20), fn () => Mercado::query()
            ->where('match_id', $id)->where('status', 'ACTIVE')->get()
            ->map(fn (Mercado $m) => $this->presentarMercado($m))->values()->all());

        return $this->ok($mercados);
    }

    private function presentarMercado(Mercado $m): array
    {
        return [
            'id' => (string) $m->getKey(),
            'code' => $m->code,
            'name' => $m->name,
            'version' => $m->version,
            'selections' => collect($m->selections ?? [])->map(fn ($s) => [
                'code' => $s['code'] ?? null, 'label' => $s['label'] ?? null, 'odd' => (float) ($s['odd'] ?? 0),
            ])->values()->all(),
        ];
    }

    /**
     * Mercado principal (1X2 o, si no existe, el primero activo) y total de mercados activos por partido,
     * con una única consulta.
     *
     * @param  array<int,int>  $ids
     * @return array<int,array{mercado_principal:?array,mercados_total:int}>
     */
    private function resumenMercados(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $porPartido = Mercado::query()->whereIn('match_id', $ids)->where('status', 'ACTIVE')->get()->groupBy('match_id');

        return $porPartido->map(function ($lista) {
            $principal = $lista->firstWhere('code', '1X2') ?? $lista->first();

            return ['mercado_principal' => $this->presentarMercado($principal), 'mercados_total' => $lista->count()];
        })->all();
    }

    /** Caché de lecturas públicas del catálogo; CATALOGO_CACHE_TTL=0 la desactiva. */
    private function cacheado(string $nombre, $r, callable $resolver, int $base = 30): array
    {
        $query = $r->query();
        ksort($query);

        return Cache::remember('catalogo:' . $nombre . ':' . md5(json_encode($query)), $this->ttl($base), $resolver);
    }

    private function ttl(int $base): int
    {
        $max = config('catalogo.cache_ttl');

        return $max === null ? $base : min($base, max(0, (int) $max));
    }

    private function like(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
