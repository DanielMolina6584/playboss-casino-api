<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CambiarEstadoUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('estado'))) {
            $this->merge(['estado' => strtoupper($this->input('estado'))]);
        }
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', 'in:ACT,BLQ,SUS'],
            'motivo' => ['nullable', 'string', 'max:200'],
        ];
    }
}
