<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Service\Account\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/** Límites de negocio públicos para que el front no los hardcodee. */
class ConfiguracionController extends Controller
{
    use RespondsJson;

    public function __construct(private readonly AccountService $accounts)
    {
    }

    public function limites(): JsonResponse
    {
        $minRecarga = (int) config('services.payment_gateway.minimum_amount_minor', 100);

        return $this->ok([
            'moneda' => config('playboss.currency', 'COP'),
            'apuestas' => [
                'monto_minimo' => $this->monto(config('playboss.bets.min_stake')),
                'monto_maximo' => $this->monto(config('playboss.bets.max_stake')),
                'max_selecciones' => (int) config('playboss.bets.max_selections', 10),
                'edad_minima' => (int) config('playboss.bets.min_age', 18),
                'limite_diario' => $this->monto(config('playboss.bets.daily_stake_limit')),
                'max_apuestas_dia' => (int) config('playboss.bets.daily_bet_count_limit', 100),
                'nota' => 'Cotas globales; cada mercado puede aplicar un rango más estricto (el servidor responde 422 con el rango exacto).',
            ],
            'recarga' => [
                'monto_minimo' => $this->accounts->desdeCentavos($minRecarga),
                'monto_maximo' => $this->monto(config('playboss.deposits.max_amount')),
            ],
            'retiro' => [
                'monto_minimo' => $this->monto(config('playboss.withdrawals.min_amount')),
                'monto_maximo' => $this->monto(config('playboss.withdrawals.max_amount')),
                'max_pendientes' => (int) config('playboss.withdrawals.max_pending', 3),
            ],
        ]);
    }

    private function monto(mixed $valor): string
    {
        return $this->accounts->desdeCentavos($this->accounts->aCentavos((string) $valor));
    }
}
