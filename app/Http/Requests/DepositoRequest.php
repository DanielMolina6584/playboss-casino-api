<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Concerns\ResuelveClaveIdempotencia;

class DepositoRequest extends FormRequest
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
        $this->merge(['idempotency_key' => $this->claveIdempotencia($this->only(['monto']))]);
    }

    public function rules(): array
    {
        return [
            'monto' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
        ];
    }
}
