<?php

namespace App\Http\Middleware;

use App\Service\Auth\Mfa\SvcMfa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Los roles de MFA_REQUIRED_ROLES (ADMIN por defecto) no pueden usar la ruta hasta activar el 2FA. */
class EnsureMfaRol
{
    public function __construct(private readonly SvcMfa $mfa)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if ($u && $this->mfa->rolExige($u) && !$this->mfa->activo((int) $u->getAuthIdentifier())) {
            return response()->json([
                'error' => 1,
                'mensaje' => ['Debes activar la autenticación en dos pasos para usar esta función.'],
                'data' => ['mfa_requerido' => true],
            ], 403);
        }

        return $next($request);
    }
}
