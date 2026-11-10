<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResuelveClaveIdempotencia;
use Illuminate\Foundation\Http\FormRequest;

class RetiroRequest extends FormRequest
{
    use ResuelveClaveIdempotencia;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_numeric($this->input('monto'))) {
            $this->merge(['monto' => (string) $this->input('monto')]);
        }
        $this->merge(['idempotency_key' => $this->claveIdempotencia($this->only(['monto', 'banco', 'numero_cuenta']))]);
    }

    public function rules(): array
    {
        return [
            'monto' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'banco' => ['required', 'string', 'max:60'],
            'tipo_cuenta' => ['required', 'string', 'in:AHORROS,CORRIENTE,NEQUI,DAVIPLATA'],
            'numero_cuenta' => ['required', 'string', 'regex:/^\d{6,20}$/'],
            'titular' => ['required', 'string', 'min:3', 'max:120'],
            'documento_titular' => ['required', 'string', 'regex:/^\d{5,15}$/'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
        ];
    }
}
