<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Documentación OpenAPI/Swagger. Se desactiva con API_DOCS_ENABLED=false (responde 404). */
class DocsController extends Controller
{
    public function __construct()
    {
        abort_unless(config('playboss.docs_enabled'), 404);
    }

    public function ui(Request $r)
    {
        return response()->view('api-docs', ['specUrl' => url('/api/openapi.json')]);
    }

    public function spec(Request $r): JsonResponse
    {
        $spec = require resource_path('openapi/openapi.php');
        $spec['servers'] = [['url' => url('/api'), 'description' => 'Servidor actual']];

        return response()->json($spec, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
