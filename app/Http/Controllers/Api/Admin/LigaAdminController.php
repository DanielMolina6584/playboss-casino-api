<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Sports\Liga;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class LigaAdminController extends AdminResourceController
{
    protected function modelo(): string
    {
        return Liga::class;
    }

    protected function with(): array
    {
        return ['pais', 'servicio'];
    }

    protected function reglas(?Model $actual): array
    {
        $req = $actual ? 'sometimes' : 'required';

        return [
            'nombre' => [$req, 'string', 'max:80'],
            'servicio_codigo' => [$req, 'string', Rule::exists('mysql.servicio', 'codigo')],
            'pais_codigo' => [$req, 'string', 'size:2', Rule::exists('mysql.pais', 'codigo_iso')],
            'logo_drive_id' => ['nullable', 'string', 'max:255'],
            'activa' => ['sometimes', 'boolean'],
        ];
    }
}
