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
        ];
    }
}
