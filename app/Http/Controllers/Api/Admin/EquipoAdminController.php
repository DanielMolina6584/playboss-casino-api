<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Sports\Equipo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class EquipoAdminController extends AdminResourceController
{
    protected function modelo(): string
    {
        return Equipo::class;
    }

    protected function with(): array
    {
        return ['ciudad', 'estadio'];
    }

    protected function reglas(?Model $actual): array
    {
        $req = $actual ? 'sometimes' : 'required';

        return [
            'nombre' => [$req, 'string', 'max:80'],
            'ciudad_id' => [$req, 'integer', Rule::exists('mysql.ciudad', 'id_ciudad')],
            'estadio_id' => ['nullable', 'integer', Rule::exists('mysql.estadio', 'id_estadio')],
            'escudo_drive_id' => ['nullable', 'string', 'max:255'],
            'fecha_fundacion' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
