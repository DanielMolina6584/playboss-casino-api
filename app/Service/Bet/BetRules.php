<?php

namespace App\Service\Bet;

use Illuminate\Validation\ValidationException;

/** Reglas estructurales de una boleta, sin acceso a bases de datos. */
class BetRules
{
    /**
     * - Sin selecciones repetidas (partido+mercado+opción).
     * - Un solo pick por partido: opciones del mismo partido son excluyentes o correlacionadas
     *   y no se permiten en una combinada.
     * - Máximo de selecciones.
     */
    public static function validarSelecciones(array $selecciones, int $maximo): void
    {
        $cantidad = count($selecciones);
        if ($cantidad < 1) {
            throw ValidationException::withMessages(['selecciones' => 'Incluye al menos una selección.']);
        }
        if ($cantidad > $maximo) {
            throw ValidationException::withMessages(['selecciones' => "Máximo {$maximo} selecciones por apuesta."]);
        }

        $exactas = [];
        $partidos = [];
        foreach ($selecciones as $s) {
            $exacta = ($s['match_id'] ?? '') . '|' . ($s['market_id'] ?? '') . '|' . ($s['selection_code'] ?? '');
            if (isset($exactas[$exacta])) {
                throw ValidationException::withMessages(['selecciones' => 'Hay selecciones duplicadas.']);
            }
            $exactas[$exacta] = true;

            $partido = (string) ($s['match_id'] ?? '');
            if (isset($partidos[$partido])) {
                throw ValidationException::withMessages(['selecciones' => 'No se permiten selecciones excluyentes del mismo partido en una combinada.']);
            }
            $partidos[$partido] = true;
        }
    }

    public static function tipo(array $selecciones): string
    {
        return count($selecciones) === 1 ? 'SIMPLE' : 'COMBINADA';
    }
}
