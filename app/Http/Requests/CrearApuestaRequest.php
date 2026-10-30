<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Concerns\ResuelveClaveIdempotencia;
use Illuminate\Validation\Validator;

class CrearApuestaRequest extends FormRequest
{
    use ResuelveClaveIdempotencia;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->claveIdempotencia($this->only(['monto', 'tipo', 'selecciones'])),
        ]);
    }

    public function rules(): array
    {
        return [
            'monto' => ['required', 'string', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'tipo' => ['nullable', 'in:SIMPLE,COMBINADA'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
            'selecciones' => ['required', 'array', 'min:1', 'max:' . (int) config('playboss.bets.max_selections', 10)],
            'selecciones.*.match_id' => ['required', 'integer', 'min:1'],
            'selecciones.*.market_id' => ['required', 'string', 'max:64'],
            'selecciones.*.selection_code' => ['required', 'string', 'max:40'],
            'selecciones.*.odd' => ['nullable', 'numeric', 'gt:1'],
        ];
    }


    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $n = count((array) $this->input('selecciones', []));
            $tipo = $this->input('tipo');
            if ($tipo === 'SIMPLE' && $n !== 1) {
                $v->errors()->add('tipo', 'Una apuesta simple lleva exactamente una selección.');
            }
            if ($tipo === 'COMBINADA' && $n < 2) {
                $v->errors()->add('tipo', 'Una combinada necesita al menos dos selecciones.');
            }
        });
    }
}
