<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Service\Storage\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/** CRUD administrativo sobre un modelo Eloquent (rol ADMIN). Cada subclase define modelo y reglas. */
abstract class AdminResourceController extends Controller
{
    use RespondsJson;

    /** @return class-string<Model> */
    abstract protected function modelo(): string;

    /** Reglas de validación; $actual es el registro en edición (null al crear). */
    abstract protected function reglas(?Model $actual): array;

    protected function with(): array
    {
        return [];
    }

    protected function etiqueta(): string
    {
        return strtolower(class_basename($this->modelo()));
    }

    protected function validarNegocio(array $datos, ?Model $actual): void
    {
    }

    public function index(Request $r): JsonResponse
    {
        $r->validate(['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:100']);
        $page = ($this->modelo())::query()->with($this->with())->latest((new ($this->modelo()))->getKeyName())
            ->paginate((int) $r->integer('per_page', 20));

        return $this->paginated($page);
    }

    public function show(string $id): JsonResponse
    {
        return $this->ok(($this->modelo())::query()->with($this->with())->findOrFail($id));
    }

    public function store(Request $r): JsonResponse
    {
        $datos = $r->validate($this->reglas(null));
        $this->validarNegocio($datos, null);
        $registro = ($this->modelo())::query()->create($datos);
        $this->auditar($r, 'created', $registro);

        return $this->ok($registro->fresh($this->with()), 201);
    }

    public function update(Request $r, string $id): JsonResponse
    {
        $registro = ($this->modelo())::query()->findOrFail($id);
        $datos = $r->validate($this->reglas($registro));
        $this->validarNegocio($datos, $registro);
        $registro->fill($datos)->save();
        $this->auditar($r, 'updated', $registro);

        return $this->ok($registro->fresh($this->with()));
    }

    public function destroy(Request $r, string $id): JsonResponse
    {
        $registro = ($this->modelo())::query()->findOrFail($id);
        try {
            $registro->delete();
        } catch (\Illuminate\Database\QueryException) {
            throw ValidationException::withMessages(['id' => 'El registro está en uso y no puede eliminarse.']);
        }
        $this->auditar($r, 'deleted', $registro);

        return $this->ok([], 200, ['Eliminado.']);
    }

    protected function auditar(Request $r, string $accion, Model $registro): void
    {
        app(AuditLogService::class)->registrarEvento('admin_' . $this->etiqueta() . '_' . $accion, (int) $r->user()->getAuthIdentifier(), [
            'id' => (string) $registro->getKey(),
        ]);
    }
}
