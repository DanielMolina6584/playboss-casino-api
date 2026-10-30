<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarPerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'primer_nombre' => ['sometimes', 'required', 'string', 'max:50'],
            'segundo_nombre' => ['sometimes', 'nullable', 'string', 'max:50'],
            'primer_apellido' => ['sometimes', 'required', 'string', 'max:50'],
            'segundo_apellido' => ['sometimes', 'nullable', 'string', 'max:50'],
            'celular' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[0-9+\s\-]{7,20}$/'],
        ];
    }
}
