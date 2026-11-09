<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Http\Requests\CrearApuestaRequest;
use App\Http\Requests\ListadoRequest;
use App\Models\Bet\Apuesta;
use App\Models\Market\Mercado;
use App\Service\Bet\BetService;
use Illuminate\Http\JsonResponse;
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

        return $this->paginated($q->paginate($r->porPagina()), fn (Apuesta $a) => $this->presentar($a));
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

    private function presentar(Apuesta $a): array
    {
        $mercados = $this->mercados($a);

        return [
            'id' => $a->id_apuesta,
            'tipo' => $a->detalles->count() === 1 ? 'SIMPLE' : 'COMBINADA',
            'estado' => $a->estado_codigo,
            'monto' => $a->monto,
            'cuota_total' => $a->cuota_total,
            'pago_potencial' => $a->ganancia_potencial,
            'creada_en' => $a->creada_en,
            'liquidada_en' => $a->liquidada_en,
            'selecciones' => $a->detalles->map(fn ($d) => [
                'partido_id' => $d->partido_id_mysql,
                'market_id' => $d->mercado_id_mongodb,
                'mercado_codigo' => $mercados[$d->mercado_id_mongodb]['code'] ?? null,
                'mercado' => $mercados[$d->mercado_id_mongodb]['name'] ?? null,
                'selection_code' => $d->seleccion_codigo,
                'etiqueta' => $d->seleccion_etiqueta,
                'cuota' => $d->cuota_congelada,
                'resultado' => $d->resultado,
            ])->values(),
        ];
    }

    /**
     * Código y nombre de los mercados (Mongo) de las selecciones de una apuesta.
     * @return array<string,array{code:string,name:string}>
     */
    private function mercados(Apuesta $a): array
    {
        $resultado = [];
        $ids = $a->detalles->pluck('mercado_id_mongodb')->unique()->values()->all();
        foreach (Mercado::query()->whereIn('_id', $ids)->get(['_id', 'code', 'name']) as $mercado) {
            $resultado[(string) $mercado->getKey()] = ['code' => (string) $mercado->code, 'name' => (string) $mercado->name];
        }

        return $resultado;
    }
}
