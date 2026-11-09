<?php

namespace App\Service\Bet;

/**
 * Resuelve las opciones ganadoras de un mercado a partir del marcador final y,
 * para mercados de estadísticas, de los datos del partido.
 *
 * Mercados por marcador:
 *  - 1X2 (1/X/2), DC doble oportunidad (1X/X2/12), DNB empate no válido (1/2; empate = VOID)
 *  - BTTS ambos marcan (YES/NO), ODD_EVEN total par/impar (ODD/EVEN)
 *  - OU_<n>_<d> total goles, HOME_OU_<n>_<d> / AWAY_OU_<n>_<d> goles por equipo (OVER/UNDER)
 *  - CS marcador exacto ("2-1"; fuera de 0-0..3-3 = OTHER)
 *  - HCP_M1 / HCP_P1 hándicap europeo del local -1 / +1 (1/X/2)
 *  - RES_BTTS resultado + ambos marcan (1_YES, X_NO…)
 * Mercados por estadísticas (requieren $stats):
 *  - HT_1X2 resultado al descanso, HTFT descanso/final ("1/1", "X/2"…)
 *  - CORNERS_OU_*, CARDS_OU_*, SHOTS_OU_*, SOT_OU_* (OVER/UNDER), CORNERS_1X2 más córners (1/X/2)
 *
 * Claves de $stats: ht_local, ht_visitante, corners_local, corners_visitante, tarjetas_local,
 * tarjetas_visitante, tiros_local, tiros_visitante, tiros_puerta_local, tiros_puerta_visitante.
 */
class MarketResolver
{
    /** Opción especial: el mercado se anula y la selección cuenta con cuota 1. */
    public const VOID = 'VOID';

    /** Marcadores con opción propia en el mercado CS; el resto es OTHER. */
    public const CS_MAX_GOLES = 3;

    /**
     * Opción ganadora de mercados con un único ganador; null si no se puede resolver
     * o si el mercado tiene varias opciones ganadoras.
     */
    public static function ganadora(string $marketCode, int $golesLocal, int $golesVisitante, array $stats = []): ?string
    {
        $ganadoras = self::ganadoras($marketCode, $golesLocal, $golesVisitante, $stats);

        return $ganadoras !== null && count($ganadoras) === 1 ? $ganadoras[0] : null;
    }

    /**
     * Lista de opciones ganadoras ([VOID] si el mercado se anula); null si no se puede resolver.
     * @return list<string>|null
     */
    public static function ganadoras(string $marketCode, int $golesLocal, int $golesVisitante, array $stats = []): ?array
    {
        $code = strtoupper($marketCode);
        $resultado = self::signo($golesLocal, $golesVisitante);
        $btts = $golesLocal > 0 && $golesVisitante > 0 ? 'YES' : 'NO';

        switch ($code) {
            case '1X2':
                return [$resultado];
            case 'DC':
                return match ($resultado) {
                    '1' => ['1X', '12'],
                    'X' => ['1X', 'X2'],
                    default => ['X2', '12'],
                };
            case 'DNB':
                return [$resultado === 'X' ? self::VOID : $resultado];
            case 'BTTS':
                return [$btts];
            case 'ODD_EVEN':
                return [($golesLocal + $golesVisitante) % 2 === 0 ? 'EVEN' : 'ODD'];
            case 'CS':
                return [self::marcadorExacto($golesLocal, $golesVisitante)];
            case 'HCP_M1':
                return [self::signo($golesLocal - 1, $golesVisitante)];
            case 'HCP_P1':
                return [self::signo($golesLocal + 1, $golesVisitante)];
            case 'RES_BTTS':
                return [$resultado . '_' . $btts];
            case 'HT_1X2':
                return self::tiene($stats, ['ht_local', 'ht_visitante'])
                    ? [self::signo((int) $stats['ht_local'], (int) $stats['ht_visitante'])]
                    : null;
            case 'HTFT':
                return self::tiene($stats, ['ht_local', 'ht_visitante'])
                    ? [self::signo((int) $stats['ht_local'], (int) $stats['ht_visitante']) . '/' . $resultado]
                    : null;
            case 'CORNERS_1X2':
                return self::tiene($stats, ['corners_local', 'corners_visitante'])
                    ? [self::signo((int) $stats['corners_local'], (int) $stats['corners_visitante'])]
                    : null;
        }

        if (preg_match('/^(HOME_|AWAY_|CORNERS_|CARDS_|SHOTS_|SOT_)?OU_(\d+)_(\d)$/', $code, $m)) {
            $total = self::totalLinea($m[1], $golesLocal, $golesVisitante, $stats);
            if ($total === null) {
                return null;
            }
            $linea = (float) ($m[2] . '.' . $m[3]);
            if (abs($total - $linea) < 0.00001) {
                return null; // línea entera exacta (push): requiere decisión manual
            }

            return [$total > $linea ? 'OVER' : 'UNDER'];
        }

        return null;
    }

    /**
     * Código del marcador exacto para el mercado CS.
     */
    public static function marcadorExacto(int $golesLocal, int $golesVisitante): string
    {
        return $golesLocal <= self::CS_MAX_GOLES && $golesVisitante <= self::CS_MAX_GOLES
            ? $golesLocal . '-' . $golesVisitante
            : 'OTHER';
    }

    /**
     * Signo 1X2 de una comparación local/visitante.
     */
    private static function signo(int $local, int $visitante): string
    {
        return $local > $visitante ? '1' : ($local < $visitante ? '2' : 'X');
    }

    /**
     * Valor que se compara con la línea de un mercado más/menos; null si faltan estadísticas.
     */
    private static function totalLinea(string $prefijo, int $golesLocal, int $golesVisitante, array $stats): ?int
    {
        $claves = match ($prefijo) {
            'CORNERS_' => ['corners_local', 'corners_visitante'],
            'CARDS_' => ['tarjetas_local', 'tarjetas_visitante'],
            'SHOTS_' => ['tiros_local', 'tiros_visitante'],
            'SOT_' => ['tiros_puerta_local', 'tiros_puerta_visitante'],
            default => null,
        };

        if ($claves === null) {
            return match ($prefijo) {
                'HOME_' => $golesLocal,
                'AWAY_' => $golesVisitante,
                default => $golesLocal + $golesVisitante,
            };
        }

        return self::tiene($stats, $claves) ? (int) $stats[$claves[0]] + (int) $stats[$claves[1]] : null;
    }

    /**
     * Indica si $stats trae todas las claves numéricas pedidas.
     */
    private static function tiene(array $stats, array $claves): bool
    {
        foreach ($claves as $clave) {
            if (!isset($stats[$clave]) || !is_numeric($stats[$clave])) {
                return false;
            }
        }

        return true;
    }
}
