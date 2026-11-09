<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();
        if (!$usuario || !in_array($usuario->rol_codigo, $roles, true)) {
            return response()->json(['error' => 1, 'mensaje' => ['No tienes permisos para esta acción.'], 'data' => []], 403);
        }

        return $next($request);
    }
}
