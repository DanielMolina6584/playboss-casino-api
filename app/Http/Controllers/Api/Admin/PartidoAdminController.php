<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Sports\Partido;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PartidoAdminController extends AdminResourceController
{
    protected function modelo(): string
    {
        return Partido::class;
    }

    protected function with(): array
    {
        return ['local', 'visitante', 'estadio', 'estado', 'jornada'];
    }

    protected function reglas(?Model $actual): array
    {
        $req = $actual ? 'sometimes' : 'required';

        return [
            'jornada_id' => [$req, 'integer', Rule::exists('mysql.jornada', 'id_jornada')],
            'equipo_local_id' => [$req, 'integer', Rule::exists('mysql.equipo', 'id_equipo')],
            'equipo_visitante_id' => [$req, 'integer', Rule::exists('mysql.equipo', 'id_equipo')],
            'estadio_id' => ['nullable', 'integer', Rule::exists('mysql.estadio', 'id_estadio')],
            'fecha_hora' => [$req, 'date'],
            // El marcador y el estado FIN solo se fijan liquidando (POST /admin/partidos/{id}/liquidar).
            'estado_codigo' => ['sometimes', Rule::in(['PROG', 'ENJ', 'SUSP'])],
        ];
    }

    protected function validarNegocio(array $datos, ?Model $actual): void
    {
        $local = $datos['equipo_local_id'] ?? $actual?->equipo_local_id;
        $visitante = $datos['equipo_visitante_id'] ?? $actual?->equipo_visitante_id;
        if ($local && $visitante && (int) $local === (int) $visitante) {
            throw ValidationException::withMessages(['equipo_visitante_id' => 'Local y visitante deben ser equipos distintos.']);
        }
        if ($actual && $actual->estado_codigo === 'FIN') {
            throw ValidationException::withMessages(['estado_codigo' => 'Un partido finalizado no se puede editar.']);
        }
    }

    public function store(\Illuminate\Http\Request $r): \Illuminate\Http\JsonResponse
    {
        $r->merge(['estado_codigo' => $r->input('estado_codigo', 'PROG')]);

        return parent::store($r);
    }
}
