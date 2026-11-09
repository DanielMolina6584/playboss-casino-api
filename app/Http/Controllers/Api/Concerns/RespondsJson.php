<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/** Sobre de respuesta común: {error, mensaje[], data}. */
trait RespondsJson
{
    protected function ok(mixed $data = [], int $status = 200, array $mensaje = []): JsonResponse
    {
        return response()->json(['error' => 0, 'mensaje' => $mensaje, 'data' => $data], $status);
    }

    protected function fail(array|string $mensaje, int $status = 422): JsonResponse
    {
        return response()->json(['error' => 1, 'mensaje' => (array) $mensaje, 'data' => []], $status);
    }

    protected function paginated(LengthAwarePaginator $page, ?callable $map = null): JsonResponse
    {
        $items = $map ? $page->getCollection()->map($map)->values() : $page->items();

        return $this->ok([
            'items' => $items,
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
