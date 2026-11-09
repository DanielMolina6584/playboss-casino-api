<?php

namespace App\Http\Requests;

use App\Service\SvcFotoPerfil;
use Illuminate\Foundation\Http\FormRequest;

class SubirFotoPerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['foto' => ['required', ...SvcFotoPerfil::REGLAS]];
    }
}
