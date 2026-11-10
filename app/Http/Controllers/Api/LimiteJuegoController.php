<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Http\Requests\ActualizarLimitesRequest;
use App\Models\Account\LimiteJuego;
use App\Service\Account\LimiteJuegoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Juego responsable: límites de depósito/apuesta y autoexclusión temporal. */
class LimiteJuegoController extends Controller
{
    use RespondsJson;

    public function __construct(private readonly LimiteJuegoService $limites)
    {
    }

    public function show(Request $r): JsonResponse
    {
        return $this->ok($this->presentar((int) $r->user()->getAuthIdentifier()));
    }

    public function update(ActualizarLimitesRequest $r): JsonResponse
    {
        $usuarioId = (int) $r->user()->getAuthIdentifier();
        $this->limites->actualizar($usuarioId, $r->validated());

        return $this->ok($this->presentar($usuarioId), 200, ['Límites actualizados.']);
    }

    private function presentar(int $usuarioId): array
    {
        $limite = $this->limites->obtener($usuarioId);
        $consumo = $this->limites->consumo($usuarioId);

        return [
            'limites' => collect(LimiteJuego::CAMPOS)->mapWithKeys(fn (string $c) => [$c => $limite->{$c} === null ? null : (string) $limite->{$c}])->all(),
            'consumo' => $consumo,
            'autoexclusion' => [
                'activa' => $this->limites->estaExcluido($limite),
                'hasta' => $limite->autoexcluido_hasta,
            ],
        ];
    }
}
