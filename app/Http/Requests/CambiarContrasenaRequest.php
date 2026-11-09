<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CambiarContrasenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contrasena_actual' => ['required', 'string'],
            'contrasena' => ['required', 'string', 'min:8', 'max:100', 'confirmed', 'different:contrasena_actual'],
        ];
    }
}
