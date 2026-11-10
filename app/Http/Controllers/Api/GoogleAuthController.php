<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Http\Requests\CompletarPerfilRequest;
use App\Models\Usuario\Usuario;
use App\Service\Auth\SvcGoogleAuth;
use App\Service\Storage\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GoogleAuthController extends Controller
{
    use RespondsJson;

    public function __construct(private readonly SvcGoogleAuth $google, private readonly AuditLogService $auditoria)
    {
    }

    /** Intercambia el id_token de Google por un token Sanctum. */
    public function login(Request $r): JsonResponse
    {
        $r->validate(['credential' => ['required', 'string', 'max:4096']]);

        $res = $this->google->autenticar($r->input('credential'), $r->ip(), $r->userAgent());
        $incompleto = !$res['usuario']->perfilCompleto();

        return $this->ok([
            'usuario' => $res['usuario']->datosPublicos(),
            'token' => $res['token'],
            'estado' => $incompleto ? 'perfil_incompleto' : 'ok',
            'perfil_incompleto' => $incompleto,
            'nuevo' => $res['nuevo'],
        ], $res['nuevo'] ? 201 : 200);
    }

    /** Completa fecha de nacimiento y documento de un usuario creado con Google (requerido para apostar/depositar). */
    public function completar(CompletarPerfilRequest $r): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $r->user();
        $usuario->loadMissing('perfil');
        if ($usuario->perfilCompleto()) {
            return $this->fail(['Tu perfil ya está completo.'], 409);
        }

        $usuario->perfil->fill($r->validated())->save();
        $this->auditoria->registrarEvento('profile_completed', $usuario->id_usuario, ['ip' => $r->ip()]);

        return $this->ok(['usuario' => $usuario->fresh()->datosPublicos(), 'perfil_incompleto' => false], 200, ['Perfil completado.']);
    }
}
