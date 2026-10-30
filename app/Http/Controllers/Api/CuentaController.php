<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Http\Requests\ListadoRequest;
use App\Models\Account\Movimiento;
use App\Service\Account\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CuentaController extends Controller
{
    use RespondsJson;

    public function __construct(private readonly AccountService $accounts)
    {
    }

    public function show(ListadoRequest $r): JsonResponse
    {
        $cuenta = $this->accounts->abrir((int) $r->user()->getAuthIdentifier());

        return $this->ok([
            'saldo_disponible' => $cuenta->saldo_disponible,
            'saldo_retenido' => $cuenta->saldo_retenido,
            'moneda' => $cuenta->moneda,
        ]);
    }

    public function movimientos(ListadoRequest $r): JsonResponse
    {
        $q = Movimiento::query()->where('usuario_id', $r->user()->getAuthIdentifier())->orderByDesc('creado_en')->orderByDesc('id_movimiento');
        $r->filled('tipo') && $q->where('tipo', strtoupper($r->string('tipo')));
        $r->filled('desde') && $q->where('creado_en', '>=', $r->date('desde')->startOfDay());
        $r->filled('hasta') && $q->where('creado_en', '<=', $r->date('hasta')->endOfDay());

        return $this->paginated($q->paginate($r->porPagina()), fn (Movimiento $m) => [
            'id' => $m->id_movimiento,
            'tipo' => $m->tipo,
            'monto' => $m->monto,
            'saldo_disponible' => $m->saldo_disponible_resultante,
            'saldo_retenido' => $m->saldo_retenido_resultante,
            'apuesta_id' => $m->apuesta_id,
            'descripcion' => $m->descripcion,
            'fecha' => $m->creado_en,
        ]);
    }
}
