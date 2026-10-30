<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Http\Requests\LiquidarPartidoRequest;
use App\Service\Bet\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LiquidacionController extends Controller
{
    use RespondsJson;

    public function __construct(private readonly SettlementService $settlement)
    {
    }

    public function partido(LiquidarPartidoRequest $r, int $id): JsonResponse
    {
        $resultado = $this->settlement->liquidarPartido(
            $id,
            (int) $r->input('goles_local'),
            (int) $r->input('goles_visitante'),
            (array) $r->input('ganadoras', []),
            (int) $r->user()->getAuthIdentifier()
        );

        return $this->ok($resultado);
    }

    public function anularApuesta(Request $r, int $id): JsonResponse
    {
        $apuesta = $this->settlement->anularApuesta($id, (int) $r->user()->getAuthIdentifier());

        return $this->ok(['id' => $apuesta->id_apuesta, 'estado' => $apuesta->estado_codigo]);
    }
}
