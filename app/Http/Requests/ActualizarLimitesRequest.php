<?php

namespace App\Http\Requests;

use App\Models\Account\LimiteJuego;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarLimitesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (LimiteJuego::CAMPOS as $campo) {
            if ($this->has($campo) && is_numeric($this->input($campo))) {
                $this->merge([$campo => (string) $this->input($campo)]);
            }
        }
    }

    public function rules(): array
    {
        $reglas = ['autoexclusion_dias' => ['sometimes', 'integer', 'in:1,7,30,90,180,365']];
        foreach (LimiteJuego::CAMPOS as $campo) {
            $reglas[$campo] = ['sometimes', 'nullable', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/'];
        }

        return $reglas;
    }
}
