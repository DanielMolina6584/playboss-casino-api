<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['nombre', 'correo', 'asunto', 'mensaje'] as $campo) {
            if (is_string($this->input($campo))) {
                $this->merge([$campo => trim($this->input($campo))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:100'],
            'correo' => ['required', 'string', 'email:rfc', 'max:150'],
            'asunto' => ['required', 'string', 'min:3', 'max:150'],
            'mensaje' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
