<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LiquidarPartidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'goles_local' => ['required', 'integer', 'min:0', 'max:99'],
            'goles_visitante' => ['required', 'integer', 'min:0', 'max:99'],
            // market_id o código de mercado => opción ganadora, o VOID para anular el mercado
            'ganadoras' => ['sometimes', 'array'],
            'ganadoras.*' => ['string', 'max:40'],
            // Para mercados de descanso, córners, tarjetas y tiros (se guardan en Mongo match_stats)
            'estadisticas' => ['sometimes', 'array'],
            'estadisticas.ht_local' => ['integer', 'min:0', 'lte:goles_local'],
            'estadisticas.ht_visitante' => ['integer', 'min:0', 'lte:goles_visitante'],
            'estadisticas.corners_local' => ['integer', 'min:0', 'max:40'],
            'estadisticas.corners_visitante' => ['integer', 'min:0', 'max:40'],
            'estadisticas.tarjetas_local' => ['integer', 'min:0', 'max:20'],
            'estadisticas.tarjetas_visitante' => ['integer', 'min:0', 'max:20'],
            'estadisticas.tiros_local' => ['integer', 'min:0', 'max:80'],
            'estadisticas.tiros_visitante' => ['integer', 'min:0', 'max:80'],
            'estadisticas.tiros_puerta_local' => ['integer', 'min:0', 'lte:estadisticas.tiros_local'],
            'estadisticas.tiros_puerta_visitante' => ['integer', 'min:0', 'lte:estadisticas.tiros_visitante'],
        ];
    }
}
