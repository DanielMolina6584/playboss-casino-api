<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Models\Market\Mercado;
use App\Models\Sports\Partido;
use App\Service\Storage\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Mercados y cuotas en MongoDB. */
class MercadoAdminController extends Controller
{
    use RespondsJson;

    public function index(Request $r): JsonResponse
    {
        $r->validate(['match_id' => 'sometimes|integer|min:1', 'status' => 'sometimes|in:ACTIVE,SUSPENDED,SETTLED', 'per_page' => 'sometimes|integer|min:1|max:100']);
        $q = Mercado::query()->orderBy('match_id');
        $r->filled('match_id') && $q->where('match_id', $r->integer('match_id'));
        $r->filled('status') && $q->where('status', $r->string('status'));

        return $this->paginated($q->paginate((int) $r->integer('per_page', 20)));
    }

    public function show(string $id): JsonResponse
    {
        return $this->ok(Mercado::query()->findOrFail($id));
    }

    public function store(Request $r): JsonResponse
    {
        $d = $r->validate([
            'match_id' => ['required', 'integer', Rule::exists('mysql.partido', 'id_partido')],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_]+$/'],
            'name' => ['required', 'string', 'max:80'],
            'status' => ['sometimes', 'in:ACTIVE,SUSPENDED'],
        ] + $this->reglasSelecciones(true));
        $this->validarSelecciones($d['selections']);

        $partido = Partido::query()->findOrFail($d['match_id']);
        if ($partido->estado_codigo === 'FIN') {
            throw ValidationException::withMessages(['match_id' => 'El partido ya finalizó.']);
        }
        if (Mercado::query()->where('match_id', $d['match_id'])->where('code', $d['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'Ya existe ese mercado para el partido.']);
        }

        $mercado = Mercado::query()->create($d + ['status' => 'ACTIVE', 'version' => 1, 'updated_at' => now()]);
        $this->auditar($r, 'created', $mercado);

        return $this->ok($mercado, 201);
    }

    /** Actualiza nombre/estado/cuotas; cada cambio sube la versión. */
    public function update(Request $r, string $id): JsonResponse
    {
        $mercado = Mercado::query()->findOrFail($id);
        if ($mercado->status === 'SETTLED') {
            throw ValidationException::withMessages(['status' => 'Un mercado liquidado no se puede editar.']);
        }
        $d = $r->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'status' => ['sometimes', 'in:ACTIVE,SUSPENDED'],
        ] + $this->reglasSelecciones(false));
        if (isset($d['selections'])) {
            $this->validarSelecciones($d['selections']);
        }
        $mercado->fill($d + ['version' => ((int) $mercado->version) + 1, 'updated_at' => now()])->save();
        $this->auditar($r, 'updated', $mercado);

        return $this->ok($mercado);
    }

    public function destroy(Request $r, string $id): JsonResponse
    {
        $mercado = Mercado::query()->findOrFail($id);
        $mercado->status = 'SUSPENDED'; // no se borra: las apuestas referencian el id
        $mercado->save();
        $this->auditar($r, 'suspended', $mercado);

        return $this->ok($mercado);
    }

    private function reglasSelecciones(bool $requerido): array
    {
        $req = $requerido ? 'required' : 'sometimes';

        return [
            'selections' => [$req, 'array', 'min:2', 'max:20'],
            'selections.*.code' => ['required', 'string', 'max:40'],
            'selections.*.label' => ['required', 'string', 'max:120'],
            'selections.*.odd' => ['required', 'numeric', 'gt:1', 'max:1000'],
        ];
    }

    private function validarSelecciones(array $selections): void
    {
        $codes = array_column($selections, 'code');
        if (count($codes) !== count(array_unique($codes))) {
            throw ValidationException::withMessages(['selections' => 'Los códigos de opción no pueden repetirse.']);
        }
    }

    private function auditar(Request $r, string $accion, Mercado $m): void
    {
        app(AuditLogService::class)->registrarEvento('admin_market_' . $accion, (int) $r->user()->getAuthIdentifier(), ['id' => (string) $m->getKey(), 'match_id' => $m->match_id]);
    }
}
