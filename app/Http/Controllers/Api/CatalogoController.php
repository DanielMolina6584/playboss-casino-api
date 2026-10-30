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

        return $this->paginated($q->paginate($r->porPagina()));
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

        $r->filled('jornada_id') && $q->where('jornada_id', $r->integer('jornada_id'));
        $r->filled('estado') && $q->where('estado_codigo', strtoupper($r->string('estado')));
        $r->filled('desde') && $q->where('fecha_hora', '>=', $r->date('desde')->startOfDay());
        $r->filled('hasta') && $q->where('fecha_hora', '<=', $r->date('hasta')->endOfDay());
        $r->filled('equipo_id') && $q->where(fn ($w) => $w
            ->where('equipo_local_id', $r->integer('equipo_id'))
            ->orWhere('equipo_visitante_id', $r->integer('equipo_id')));
        $r->filled('temporada_id') && $q->whereHas('jornada', fn ($j) => $j->where('temporada_id', $r->integer('temporada_id')));
        $r->filled('liga_id') && $q->whereHas('jornada.temporada', fn ($t) => $t->where('liga_id', $r->integer('liga_id')));

        return $this->paginated($q->paginate($r->porPagina()));
    }

    public function partido(int $id): JsonResponse
    {
        $partido = Partido::query()->with(['local', 'visitante', 'estadio', 'estado', 'jornada.temporada.liga'])->findOrFail($id);

        return $this->ok($partido);
    }

    public function mercados(int $id): JsonResponse
    {
        Partido::query()->findOrFail($id);
        $mercados = Mercado::query()->where('match_id', $id)->where('status', 'ACTIVE')->get()->map(fn (Mercado $m) => [
            'id' => (string) $m->getKey(),
            'code' => $m->code,
            'name' => $m->name,
            'version' => $m->version,
            'selections' => collect($m->selections ?? [])->map(fn ($s) => [
                'code' => $s['code'] ?? null, 'label' => $s['label'] ?? null, 'odd' => (float) ($s['odd'] ?? 0),
            ])->values(),
        ]);

        return $this->ok($mercados->values());
    }

    private function like(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
