<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Http\Controllers\Api\RetiroController;
use App\Http\Requests\ListadoRequest;
use App\Http\Requests\RechazarRetiroRequest;
use App\Models\Payment\Retiro;
use App\Service\Payment\RetiroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class RetiroAdminController extends Controller
{
    use RespondsJson;

    public function __construct(private readonly RetiroService $retiros)
    {
    }

    public function index(ListadoRequest $r): JsonResponse
    {
        $q = Retiro::query()->orderByRaw("CASE estado WHEN 'PENDIENTE' THEN 0 ELSE 1 END")->orderBy('solicitado_en');
        $r->filled('estado') && $q->where('estado', $r->string('estado'));
        $r->filled('usuario_id') && $q->where('usuario_id', $r->integer('usuario_id'));
        $r->filled('desde') && $q->where('solicitado_en', '>=', $r->date('desde')->startOfDay());
        $r->filled('hasta') && $q->where('solicitado_en', '<=', $r->date('hasta')->endOfDay());

        return $this->paginated($q->paginate($r->porPagina()), fn (Retiro $x) => RetiroController::presentar($x, true));
    }

    public function aprobar(int $id): JsonResponse
    {
        $retiro = $this->retiros->aprobar((int) request()->user()->getAuthIdentifier(), $id);

        return $this->ok(RetiroController::presentar($retiro, true));
    }

    public function rechazar(RechazarRetiroRequest $r, int $id): JsonResponse
    {
        $retiro = $this->retiros->rechazar((int) $r->user()->getAuthIdentifier(), $id, (string) $r->input('motivo'));

        return $this->ok(RetiroController::presentar($retiro, true));
    }
}
