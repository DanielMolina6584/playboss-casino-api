<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Http\Requests\CrearApuestaRequest;
use App\Http\Requests\ListadoRequest;
use App\Models\Bet\Apuesta;
use App\Models\Market\Mercado;
use App\Models\Sports\Partido;
use App\Service\Bet\BetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Routing\Controller;

class ApuestaController extends Controller
{
    use RespondsJson;

    public function __construct(private readonly BetService $bets)
    {
    }

    public function store(CrearApuestaRequest $r): JsonResponse
    {
        $apuesta = $this->bets->crear(
            (int) $r->user()->getAuthIdentifier(),
            (string) $r->input('monto'),
            $r->input('selecciones'),
            (string) $r->input('idempotency_key')
        );

        return $this->ok($this->presentar($apuesta), $apuesta->wasRecentlyCreated ? 201 : 200);
    }

    public function index(ListadoRequest $r): JsonResponse
    {
        $q = Apuesta::query()->with('detalles')->where('usuario_id', $r->user()->getAuthIdentifier())->orderByDesc('creada_en');
        $r->filled('estado') && $q->where('estado_codigo', strtoupper($r->string('estado')));
        $r->filled('desde') && $q->where('creada_en', '>=', $r->date('desde')->startOfDay());
        $r->filled('hasta') && $q->where('creada_en', '<=', $r->date('hasta')->endOfDay());

        $page = $q->paginate($r->porPagina());
        $ctx = $this->contexto($page->getCollection());

        return $this->paginated($page, fn (Apuesta $a) => $this->presentar($a, $ctx));
    }

    public function show(int $id): JsonResponse
    {
        return $this->ok($this->presentar($this->propia($id)));
    }

    public function cancelar(int $id): JsonResponse
    {
        $apuesta = $this->bets->cancelar((int) request()->user()->getAuthIdentifier(), $id);

        return $this->ok($this->presentar($apuesta));
    }

    private function propia(int $id): Apuesta
    {
        $apuesta = Apuesta::query()->with('detalles')->find($id);
        if (!$apuesta || (int) $apuesta->usuario_id !== (int) request()->user()->getAuthIdentifier()) {
            throw new ReglaNegocioException('Apuesta no encontrada.', 404);
        }

        return $apuesta;
    }

    /**
     * @param array{mercados:array,partidos:array}|null $ctx datos precargados (listados); si es null se cargan para esta apuesta
     */
    private function presentar(Apuesta $a, ?array $ctx = null): array
    {
        $ctx ??= $this->contexto(collect([$a]));
        $mercados = $ctx['mercados'];
        $partidos = $ctx['partidos'];

        return [
            'id' => $a->id_apuesta,
            'tipo' => $a->detalles->count() === 1 ? 'SIMPLE' : 'COMBINADA',
            'estado' => $a->estado_codigo,
            'monto' => $a->monto,
            'cuota_total' => $a->cuota_total,
            'pago_potencial' => $a->ganancia_potencial,
            'creada_en' => $a->creada_en,
            'liquidada_en' => $a->liquidada_en,
            'selecciones' => $a->detalles->map(function ($d) use ($mercados, $partidos) {
                $partido = $partidos[$d->partido_id_mysql] ?? null;

                return [
                    'partido_id' => $d->partido_id_mysql,
                    'partido' => $partido['nombre'] ?? null,
                    'local' => $partido['local'] ?? null,
                    'visitante' => $partido['visitante'] ?? null,
                    'local_escudo_url' => $partido['local_escudo_url'] ?? null,
                    'visitante_escudo_url' => $partido['visitante_escudo_url'] ?? null,
                    'liga' => $partido['liga'] ?? null,
                    'fecha_partido' => $partido['fecha'] ?? null,
                    'estado_partido' => $partido['estado'] ?? null,
                    'market_id' => $d->mercado_id_mongodb,
                    'mercado_codigo' => $mercados[$d->mercado_id_mongodb]['code'] ?? null,
                    'mercado' => $mercados[$d->mercado_id_mongodb]['name'] ?? null,
                    'selection_code' => $d->seleccion_codigo,
                    'etiqueta' => $d->seleccion_etiqueta,
                    'opcion' => $d->seleccion_etiqueta,
                    'cuota' => $d->cuota_congelada,
                    'resultado' => $d->resultado,
                ];
            })->values(),
        ];
    }

    /**
     * Una consulta a Mongo (mercados) y una a MySQL (partidos con equipos y liga) para todas las apuestas dadas.
     * @param Collection<int,Apuesta> $apuestas
     * @return array{mercados:array<string,array{code:string,name:string}>,partidos:array<int,array<string,mixed>>}
     */
    private function contexto(Collection $apuestas): array
    {
        $detalles = $apuestas->flatMap(fn (Apuesta $a) => $a->detalles);

        $mercados = [];
        $ids = $detalles->pluck('mercado_id_mongodb')->unique()->values()->all();
        if ($ids) {
            foreach (Mercado::query()->whereIn('_id', $ids)->get(['_id', 'code', 'name']) as $mercado) {
                $mercados[(string) $mercado->getKey()] = ['code' => (string) $mercado->code, 'name' => (string) $mercado->name];
            }
        }

        $partidos = [];
        $ids = $detalles->pluck('partido_id_mysql')->unique()->values()->all();
        if ($ids) {
            foreach (Partido::query()->with(['local', 'visitante', 'jornada.temporada.liga'])->whereIn('id_partido', $ids)->get() as $p) {
                $partidos[$p->id_partido] = [
                    'nombre' => ($p->local?->nombre ?? '?') . ' vs ' . ($p->visitante?->nombre ?? '?'),
                    'local' => $p->local?->nombre,
                    'visitante' => $p->visitante?->nombre,
                    'local_escudo_url' => $p->local?->escudo_url,
                    'visitante_escudo_url' => $p->visitante?->escudo_url,
                    'liga' => $p->jornada?->temporada?->liga?->nombre,
                    'fecha' => $p->fecha_hora,
                    'estado' => $p->estado_codigo,
                ];
            }
        }

        return ['mercados' => $mercados, 'partidos' => $partidos];
    }
}
