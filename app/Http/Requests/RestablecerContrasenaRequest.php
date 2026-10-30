<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestablecerContrasenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'correo' => ['required', 'email', 'max:120'],
            'codigo' => ['required', 'digits:6'],
            'contrasena' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ];
    }
}
