<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Market\ReglaApuesta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ReglaApuestaAdminController extends AdminResourceController
{
    protected function modelo(): string
    {
        return ReglaApuesta::class;
    }

    protected function etiqueta(): string
    {
        return 'bet_rule';
    }

    protected function reglas(?Model $actual): array
    {
        $req = $actual ? 'sometimes' : 'required';

        return [
            'code' => [$req, 'string', 'max:40', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'market_code' => [$req, 'string', 'max:30'],
            'min_stake' => [$req, 'numeric', 'min:0.01', 'max:1000000000'],
            'max_stake' => [$req, 'numeric', 'min:0.01', 'max:1000000000'],
            'max_payout' => ['nullable', 'numeric', 'min:0.01'],
            'min_age' => [$req, 'integer', 'min:18', 'max:100'],
            'active' => ['sometimes', 'boolean'],
            'effective_from' => ['sometimes', 'date'],
        ];
    }

    protected function validarNegocio(array $datos, ?Model $actual): void
    {
        $min = $datos['min_stake'] ?? $actual?->min_stake;
        $max = $datos['max_stake'] ?? $actual?->max_stake;
        if ($min !== null && $max !== null && (float) $max < (float) $min) {
            throw ValidationException::withMessages(['max_stake' => 'El monto máximo debe ser mayor o igual al mínimo.']);
        }
        if (!$actual && ReglaApuesta::query()->where('code', $datos['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'Ya existe una regla con ese código.']);
        }
    }

    public function store(\Illuminate\Http\Request $r): \Illuminate\Http\JsonResponse
    {
        $r->merge(['active' => $r->input('active', true), 'effective_from' => $r->input('effective_from', now()->toDateTimeString())]);

        return parent::store($r);
    }
}
