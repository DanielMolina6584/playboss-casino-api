<?php

namespace App\Service\Auth;

use App\Exceptions\ReglaNegocioException;
use App\Models\Usuario\CredencialUsuario;
use App\Models\Usuario\PerfilUsuario;
use App\Models\Usuario\Usuario;
use App\Service\Storage\AuditLogService;
use App\Service\Auth\Mfa\SvcMfa;
use App\Service\SvcUsuario;
use Illuminate\Support\Facades\DB;

/** Inicio de sesión / registro con Google: vincula por google_sub o correo verificado, o crea el usuario sin contraseña. */
class SvcGoogleAuth
{
    public function __construct(
        private readonly GoogleIdTokenVerifier $verificador,
        private readonly SvcUsuario $usuarios,
        private readonly AuditLogService $auditoria,
        private readonly SvcMfa $mfa
    ) {
    }

    /** @return array{usuario:Usuario,token:?string,nuevo:bool,mfa:?array} `mfa` trae el reto cuando el usuario tiene 2FA (y `token` es null). */
    public function autenticar(string $credential, string $ip, ?string $dispositivo): array
    {
        $g = $this->verificador->verificar($credential);

        if (!$g['email_verified']) {
            $this->auditoria->registrarAutenticacion(null, $g['email'], false, $ip, $dispositivo, 'Google: correo no verificado');
            throw new ReglaNegocioException('Tu correo de Google no está verificado.', 403);
        }

        $nuevo = false;
        $credencial = CredencialUsuario::query()->where('google_sub', $g['sub'])->first();
        $usuario = $credencial ? Usuario::with('credencial', 'perfil')->find($credencial->usuario_id) : $this->usuarios->buscarPorCorreo($g['email']);

        if ($usuario && !$credencial) {
            if ($usuario->credencial->google_sub !== null && $usuario->credencial->google_sub !== $g['sub']) {
                $this->auditoria->registrarAutenticacion($usuario->id_usuario, $g['email'], false, $ip, $dispositivo, 'Google: cuenta vinculada a otra identidad');
                throw new ReglaNegocioException('Esta cuenta ya está vinculada a otra cuenta de Google.', 409);
            }
            $usuario->credencial->google_sub = $g['sub'];
            $usuario->credencial->correo_verificado_en ??= now();
            $usuario->credencial->save();
            $this->auditoria->registrarEvento('google_account_linked', $usuario->id_usuario, ['ip' => $ip]);
        }

        if (!$usuario) {
            $usuario = $this->crear($g);
            $nuevo = true;
            $this->auditoria->registrarEvento('google_account_created', $usuario->id_usuario, ['ip' => $ip]);
        }

        if (!$usuario->estaActivo()) {
            $this->auditoria->registrarAutenticacion($usuario->id_usuario, $g['email'], false, $ip, $dispositivo, 'Cuenta inactiva (Google)');
            throw new ReglaNegocioException('No fue posible iniciar sesión con Google.', 401);
        }

        if ($this->mfa->activo($usuario->id_usuario)) {
            $this->auditoria->registrarAutenticacion($usuario->id_usuario, $g['email'], true, $ip, $dispositivo, 'google_mfa_pendiente');

            return ['usuario' => $usuario, 'token' => null, 'nuevo' => $nuevo, 'mfa' => $this->mfa->iniciarReto($usuario, 'google', ['nuevo' => $nuevo])];
        }

        $token = $usuario->createToken('playboss-api');
        $this->auditoria->registrarAutenticacion($usuario->id_usuario, $g['email'], true, $ip, $dispositivo, 'google', $token->accessToken->id);

        return ['usuario' => $usuario->fresh(['credencial', 'perfil']), 'token' => $token->plainTextToken, 'nuevo' => $nuevo, 'mfa' => null];
    }

    private function crear(array $g): Usuario
    {
        $nombre = trim((string) ($g['given_name'] ?: $g['name'] ?: strstr($g['email'], '@', true)));

        return DB::connection('sqlsrv')->transaction(function () use ($g, $nombre): Usuario {
            $usuario = new Usuario();
            $usuario->fill(['rol_codigo' => 'CLIENTE', 'estado_codigo' => 'ACT']);
            $usuario->save();

            $credencial = new CredencialUsuario();
            $credencial->correo = $g['email'];
            $credencial->contrasena_hash = null;
            $credencial->google_sub = $g['sub'];
            $credencial->auth_proveedor = 'GOOGLE';
            $credencial->correo_verificado_en = now();
            $usuario->credencial()->save($credencial);

            $perfil = new PerfilUsuario();
            $perfil->fill([
                'primer_nombre' => mb_substr($nombre, 0, 50),
                'primer_apellido' => $g['family_name'] ? mb_substr($g['family_name'], 0, 50) : null,
                'foto_url' => $g['picture'] ? mb_substr($g['picture'], 0, 500) : null,
            ]);
            $usuario->perfil()->save($perfil);

            return $usuario;
        });
    }
}
