<?php

namespace App\Service\Bet;

/** Cálculo de cuotas y pagos en enteros (centavos) para evitar errores de coma flotante. */
class BetMath
{
    /** Producto de cuotas, redondeado a 4 decimales como se persiste. */
    public static function cuotaTotal(array $odds): float
    {
        $total = 1.0;
        foreach ($odds as $odd) {
            $total = round($total * round((float) $odd, 4), 4);
        }

        return $total;
    }

    /** Retorno total (monto * cuota) en centavos, incluye el monto apostado. */
    public static function pagoPotencialCentavos(int $stakeCentavos, float $cuotaTotal): int
    {
        return (int) round($stakeCentavos * $cuotaTotal);
    }
}
