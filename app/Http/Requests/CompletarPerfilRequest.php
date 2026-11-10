<?php

namespace App\Http\Requests;

use App\Models\Usuario\PerfilUsuario;
use App\Models\Usuario\TipoDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompletarPerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $edad = (int) config('playboss.bets.min_age', 18);

        return [
            'primer_nombre' => ['sometimes', 'required', 'string', 'max:50'],
            'segundo_nombre' => ['sometimes', 'nullable', 'string', 'max:50'],
            'primer_apellido' => ['required', 'string', 'max:50'],
            'segundo_apellido' => ['sometimes', 'nullable', 'string', 'max:50'],
            'tipo_documento_codigo' => ['required', 'string', Rule::exists(TipoDocumento::class, 'codigo')],
            'numero_documento' => [
                'required', 'string', 'max:20',
                Rule::unique(PerfilUsuario::class, 'numero_documento')
                    ->where(fn ($q) => $q->where('tipo_documento_codigo', $this->input('tipo_documento_codigo'))),
            ],
            'celular' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[0-9+\s\-]{7,20}$/'],
            'fecha_nacimiento' => ['required', 'date', 'before_or_equal:' . now()->subYears($edad)->toDateString()],
        ];
    }

    public function messages(): array
    {
        return ['fecha_nacimiento.before_or_equal' => 'Debes ser mayor de edad.'];
    }
}
