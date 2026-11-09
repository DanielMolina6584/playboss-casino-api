<?php

namespace App\Service\Demo;

/**
 * Mercados que ofrece la demo para cada partido (como en una casa de apuestas) y
 * la regla de apuesta de cada uno según su categoría.
 */
class CatalogoMercados
{
    public const PRINCIPAL = 'principal';
    public const ESPECIAL = 'especial';
    public const ESTADISTICAS = 'estadisticas';

    /** Límites por categoría: monto mínimo, máximo y pago máximo (COP). */
    private const REGLAS = [
        self::PRINCIPAL => ['min_stake' => '1000.00', 'max_stake' => '2000000.00', 'max_payout' => '50000000.00'],
        self::ESPECIAL => ['min_stake' => '1000.00', 'max_stake' => '500000.00', 'max_payout' => '20000000.00'],
        self::ESTADISTICAS => ['min_stake' => '1000.00', 'max_stake' => '1000000.00', 'max_payout' => '20000000.00'],
    ];

    /**
     * Mercados de un partido con nombre, categoría y opciones (código => etiqueta).
     * El primero es 1X2 porque es el que se muestra en la tarjeta del partido.
     * @return list<array{code:string,name:string,categoria:string,opciones:array<string,string>}>
     */
    public static function definiciones(string $local, string $visitante): array
    {
        $mercados = [
            self::mercado('1X2', 'Resultado final', self::PRINCIPAL, ['1' => $local, 'X' => 'Empate', '2' => $visitante]),
            self::mercado('DC', 'Doble oportunidad', self::PRINCIPAL, ['1X' => "{$local} o empate", 'X2' => "Empate o {$visitante}", '12' => "{$local} o {$visitante}"]),
            self::mercado('DNB', 'Empate apuesta no válida', self::PRINCIPAL, ['1' => $local, '2' => $visitante]),
            self::mercado('BTTS', 'Ambos equipos marcan', self::PRINCIPAL, ['YES' => 'Sí', 'NO' => 'No']),
        ];

        foreach (['0_5', '1_5', '2_5', '3_5', '4_5'] as $linea) {
            $mercados[] = self::masMenos("OU_{$linea}", 'Total de goles', $linea, self::PRINCIPAL);
        }
        foreach (['0_5', '1_5'] as $linea) {
            $mercados[] = self::masMenos("HOME_OU_{$linea}", "Goles de {$local}", $linea, self::PRINCIPAL);
            $mercados[] = self::masMenos("AWAY_OU_{$linea}", "Goles de {$visitante}", $linea, self::PRINCIPAL);
        }

        $mercados[] = self::mercado('ODD_EVEN', 'Total de goles par o impar', self::PRINCIPAL, ['ODD' => 'Impar', 'EVEN' => 'Par']);
        $mercados[] = self::mercado('HCP_M1', "Hándicap {$local} (-1)", self::PRINCIPAL, ['1' => "{$local} (-1)", 'X' => 'Empate (-1)', '2' => "{$visitante} (+1)"]);
        $mercados[] = self::mercado('HCP_P1', "Hándicap {$local} (+1)", self::PRINCIPAL, ['1' => "{$local} (+1)", 'X' => 'Empate (+1)', '2' => "{$visitante} (-1)"]);
        $mercados[] = self::mercado('HT_1X2', 'Resultado al descanso', self::ESPECIAL, ['1' => $local, 'X' => 'Empate', '2' => $visitante]);
        $mercados[] = self::mercado('HTFT', 'Descanso / Final', self::ESPECIAL, self::opcionesHtft($local, $visitante));
        $mercados[] = self::mercado('RES_BTTS', 'Resultado y ambos marcan', self::ESPECIAL, [
            '1_YES' => "{$local} y ambos marcan", '1_NO' => "{$local} y no marcan ambos",
            'X_YES' => 'Empate y ambos marcan', 'X_NO' => 'Empate sin goles',
            '2_YES' => "{$visitante} y ambos marcan", '2_NO' => "{$visitante} y no marcan ambos",
        ]);
        $mercados[] = self::mercado('CS', 'Marcador exacto', self::ESPECIAL, self::opcionesMarcador());

        $mercados[] = self::mercado('CORNERS_1X2', 'Más tiros de esquina', self::ESTADISTICAS, ['1' => $local, 'X' => 'Igual cantidad', '2' => $visitante]);
        foreach (['8_5', '9_5', '10_5'] as $linea) {
            $mercados[] = self::masMenos("CORNERS_OU_{$linea}", 'Total de tiros de esquina', $linea, self::ESTADISTICAS);
        }
        foreach (['3_5', '4_5', '5_5'] as $linea) {
            $mercados[] = self::masMenos("CARDS_OU_{$linea}", 'Total de tarjetas', $linea, self::ESTADISTICAS);
        }
        foreach (['21_5', '24_5'] as $linea) {
            $mercados[] = self::masMenos("SHOTS_OU_{$linea}", 'Total de tiros', $linea, self::ESTADISTICAS);
        }
        foreach (['7_5', '9_5'] as $linea) {
            $mercados[] = self::masMenos("SOT_OU_{$linea}", 'Total de tiros a puerta', $linea, self::ESTADISTICAS);
        }

        return $mercados;
    }

    /**
     * Regla de apuesta (Mongo bet_rules) para un código de mercado.
     * @return array<string,mixed>
     */
    public static function regla(string $code, string $categoria): array
    {
        return ['code' => 'REGLA_' . $code, 'market_code' => $code, 'min_age' => 18, 'active' => true, 'version' => 1]
            + self::REGLAS[$categoria];
    }

    /**
     * Indica si la categoría usa el margen alto de mercados especiales.
     */
    public static function esEspecial(string $categoria): bool
    {
        return $categoria !== self::PRINCIPAL;
    }

    /** @param array<string,string> $opciones */
    private static function mercado(string $code, string $name, string $categoria, array $opciones): array
    {
        return ['code' => $code, 'name' => $name, 'categoria' => $categoria, 'opciones' => $opciones];
    }

    private static function masMenos(string $code, string $titulo, string $linea, string $categoria): array
    {
        $valor = str_replace('_', '.', $linea);

        return self::mercado($code, "{$titulo} más/menos {$valor}", $categoria, ['OVER' => "Más de {$valor}", 'UNDER' => "Menos de {$valor}"]);
    }

    /** @return array<string,string> */
    private static function opcionesHtft(string $local, string $visitante): array
    {
        $nombres = ['1' => $local, 'X' => 'Empate', '2' => $visitante];
        $opciones = [];
        foreach ($nombres as $ht => $nombreHt) {
            foreach ($nombres as $ft => $nombreFt) {
                $opciones["{$ht}/{$ft}"] = "{$nombreHt} / {$nombreFt}";
            }
        }

        return $opciones;
    }

    /** @return array<string,string> */
    private static function opcionesMarcador(): array
    {
        $opciones = [];
        for ($h = 0; $h <= 3; $h++) {
            for ($a = 0; $a <= 3; $a++) {
                $opciones["{$h}-{$a}"] = "{$h} - {$a}";
            }
        }
        $opciones['OTHER'] = 'Otro marcador';

        return $opciones;
    }
}
