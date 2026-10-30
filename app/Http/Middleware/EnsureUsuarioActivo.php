<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();
        if ($usuario && method_exists($usuario, 'estaActivo') && !$usuario->estaActivo()) {
            return response()->json([
                'error' => 1,
                'mensaje' => ['La cuenta está inactiva.'],
                'data' => [],
            ], 403);
        }

        return $next($request);
    }
}
