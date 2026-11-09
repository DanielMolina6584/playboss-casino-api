<?php

namespace App\Service\Bet;

/**
 * Resuelve la opción ganadora de mercados estándar a partir del marcador final.
 * Mercados: 1X2 (1/X/2), BTTS (YES/NO), OU_<entero>_<decimal> (OVER/UNDER, p. ej. OU_2_5).
 * Otros códigos requieren que el admin indique la opción ganadora.
 */
class MarketResolver
{
    public static function ganadora(string $marketCode, int $golesLocal, int $golesVisitante): ?string
    {
        $code = strtoupper($marketCode);

        if ($code === '1X2') {
            return $golesLocal > $golesVisitante ? '1' : ($golesLocal < $golesVisitante ? '2' : 'X');
        }
        if ($code === 'BTTS') {
            return $golesLocal > 0 && $golesVisitante > 0 ? 'YES' : 'NO';
        }
        if (preg_match('/^OU_(\d+)_(\d)$/', $code, $m)) {
            $linea = (float) ($m[1] . '.' . $m[2]);
            $total = $golesLocal + $golesVisitante;
            if (abs($total - $linea) < 0.00001) {
                return null; // línea entera exacta (push): requiere decisión manual
            }

            return $total > $linea ? 'OVER' : 'UNDER';
        }

        return null;
    }
}
