<?php

namespace App\Service\Auth\Mfa;

use App\Exceptions\ReglaNegocioException;
use App\Models\Usuario\Usuario;
use App\Service\Auth\GoogleIdTokenVerifier;
use App\Service\Storage\AuditLogService;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * 2FA por TOTP. Secreto cifrado con APP_KEY; códigos de recuperación solo como HMAC; el reto de login usa un
 * token opaco en caché (no es un token Sanctum, así que NUNCA pasa auth:sanctum).
 */
class SvcMfa
{
    private const PREFIJO_RETO = 'mfa_reto:';
    private const ALFABETO_RECUPERACION = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly MfaAlmacen $almacen,
        private readonly AuditLogService $auditoria,
        private readonly GoogleIdTokenVerifier $google
    ) {
    }

    // ------------------------------------------------------------------ política

    public function habilitado(): bool
    {
        return (bool) config('mfa.enabled');
    }

    public function rolExige(Usuario $u): bool
    {
        return $this->habilitado() && in_array($u->rol_codigo, (array) config('mfa.required_roles'), true);
    }

    public function activo(int $usuarioId): bool
    {
        return $this->habilitado() && ($this->almacen->obtener($usuarioId)['confirmado_en'] ?? null) !== null;
    }

    public function estado(Usuario $u): array
    {
        $u->loadMissing('credencial');
        $fila = $this->habilitado() ? $this->almacen->obtener($u->id_usuario) : null;
        $activo = ($fila['confirmado_en'] ?? null) !== null;

        return [
            'habilitado' => $this->habilitado(),
            'activo' => $activo,
            'obligatorio' => $this->rolExige($u),
            'configuracion_pendiente' => $fila !== null && !$activo,
            'recuperacion_restantes' => $activo ? $this->almacen->recuperacionRestantes($u->id_usuario) : 0,
            'confirmado_en' => $activo ? $fila['confirmado_en'] : null,
            'emisor' => (string) config('mfa.issuer'),
            'tiene_contrasena' => !empty($u->credencial?->contrasena_hash),
        ];
    }

    // ------------------------------------------------------------------ configuración

    public function configurar(Usuario $u, string $ip): array
    {
        $this->exigirHabilitado();
        if ($this->activo($u->id_usuario)) {
            throw new ReglaNegocioException('La autenticación en dos pasos ya está activa.', 409);
        }
        $u->loadMissing('credencial');
        $secreto = Totp::generarSecreto();
        $this->almacen->guardarPendiente($u->id_usuario, Crypt::encryptString($secreto));
        $this->auditoria->registrarEvento('mfa.setup_started', $u->id_usuario, ['ip' => $ip]);
        $cuenta = (string) ($u->credencial?->correo ?: 'usuario-' . $u->id_usuario);

        return [
            'secreto' => $secreto,
            'otpauth_uri' => Totp::uri($secreto, $cuenta, (string) config('mfa.issuer'), (int) config('mfa.digits'), (int) config('mfa.period')),
            'emisor' => (string) config('mfa.issuer'),
            'cuenta' => $cuenta,
            'algoritmo' => 'SHA1', 'digitos' => (int) config('mfa.digits'), 'periodo' => (int) config('mfa.period'),
        ];
    }

    /** @return string[] códigos de recuperación en claro (se muestran una sola vez) */
    public function activar(Usuario $u, string $codigo, string $ip, ?string $ua): array
    {
        $this->exigirHabilitado();
        $fila = $this->almacen->obtener($u->id_usuario);
        if (!$fila) {
            throw new ReglaNegocioException('Primero genera la configuración del segundo paso.', 409);
        }
        if ($fila['confirmado_en'] !== null) {
            throw new ReglaNegocioException('La autenticación en dos pasos ya está activa.', 409);
        }

        ['contador' => $contador] = $this->verificarFactor($u, $codigo, null, $ip, $ua, 422, true);
        $codigos = $this->generarCodigos();
        $this->almacen->confirmar($u->id_usuario, $contador, array_map($this->hashRecuperacion(...), $codigos));
        // Las sesiones abiertas antes de activar el 2FA dejan de valer (salvo la actual).
        $this->almacen->revocarSesiones($u->id_usuario, $u->currentAccessToken()?->id ?? null);
        $this->auditoria->registrarEvento('mfa.enabled', $u->id_usuario, ['ip' => $ip]);

        return $codigos;
    }

    public function desactivar(Usuario $u, ?string $contrasena, ?string $credencialGoogle, ?string $codigo, ?string $recuperacion, string $ip, ?string $ua): void
    {
        $this->exigirHabilitado();
        if (!$this->activo($u->id_usuario)) {
            throw new ReglaNegocioException('La autenticación en dos pasos no está activa.', 409);
        }
        if ($this->rolExige($u)) {
            throw new ReglaNegocioException('Tu rol exige la autenticación en dos pasos; no se puede desactivar. Si perdiste el acceso, pide a otro administrador que la restablezca.', 403);
        }

        $this->bloqueo($u, $ip);
        $this->reautenticar($u, $contrasena, $credencialGoogle, $ip, $ua);
        $this->verificarFactor($u, $codigo, $recuperacion, $ip, $ua);

        $this->almacen->eliminar($u->id_usuario);
        $this->auditoria->registrarEvento('mfa.disabled', $u->id_usuario, ['ip' => $ip]);
    }

    /** @return string[] */
    public function regenerarCodigos(Usuario $u, ?string $codigo, ?string $recuperacion, string $ip, ?string $ua): array
    {
        $this->exigirHabilitado();
        if (!$this->activo($u->id_usuario)) {
            throw new ReglaNegocioException('La autenticación en dos pasos no está activa.', 409);
        }
        $this->verificarFactor($u, $codigo, $recuperacion, $ip, $ua);
        $codigos = $this->generarCodigos();
        $this->almacen->reemplazarRecuperacion($u->id_usuario, array_map($this->hashRecuperacion(...), $codigos));
        $this->auditoria->registrarEvento('mfa.recovery_regenerated', $u->id_usuario, ['ip' => $ip]);

        return $codigos;
    }

    /** Restablecimiento por un administrador (o por consola con $adminId null): borra el 2FA y cierra sus sesiones. */
    public function restablecer(int $usuarioId, ?int $adminId, ?string $motivo, string $ip): bool
    {
        $objetivo = $this->almacen->usuario($usuarioId);
        if (!$objetivo) {
            throw new ReglaNegocioException('Usuario no encontrado.', 404);
        }
        $tenia = $this->almacen->obtener($usuarioId) !== null;
        $this->almacen->eliminar($usuarioId);
        $this->almacen->revocarSesiones($usuarioId);
        $this->auditoria->registrarEvento('mfa.admin_reset', $adminId ?? $usuarioId, [
            'usuario_id' => $usuarioId, 'admin_id' => $adminId, 'origen' => $adminId ? 'api' : 'consola', 'tenia_mfa' => $tenia, 'motivo' => $motivo, 'ip' => $ip,
        ]);

        return $tenia;
    }

    // ------------------------------------------------------------------ reto de login

    /** Crea el reto tras validar la contraseña (o Google): el cliente NO recibe token de API hasta verificar. */
    public function iniciarReto(Usuario $u, string $via, array $extra = []): array
    {
        $ttl = (int) config('mfa.pending_ttl');
        $token = Str::random(48);
        Cache::put(self::PREFIJO_RETO . hash('sha256', $token), [
            'uid' => $u->id_usuario, 'via' => $via, 'extra' => $extra, 'intentos' => 0, 'exp' => now()->timestamp + $ttl,
        ], $ttl);

        return ['mfa_required' => true, 'mfa_token' => $token, 'expira_en' => $ttl, 'metodos' => ['totp', 'recuperacion']];
    }

    /** @return array{usuario:Usuario,token:string,via:string,extra:array,factor:string,recuperacion_restantes:int} */
    public function completarReto(string $mfaToken, ?string $codigo, ?string $recuperacion, string $ip, ?string $ua): array
    {
        $this->exigirHabilitado();
        $clave = self::PREFIJO_RETO . hash('sha256', $mfaToken);
        $reto = Cache::get($clave);
        $invalido = new ReglaNegocioException('La verificación venció o es inválida. Inicia sesión de nuevo.', 401);
        if (!is_array($reto)) {
            throw $invalido;
        }
        $u = $this->almacen->usuario((int) $reto['uid']);
        if (!$u || !$u->estaActivo() || !$this->activo($u->id_usuario)) {
            Cache::forget($clave);
            throw $invalido;
        }

        try {
            $r = $this->verificarFactor($u, $codigo, $recuperacion, $ip, $ua, 401);
        } catch (ReglaNegocioException $e) {
            if ($e->status === 401) {
                // Un reto tolera pocos fallos y se quema: hay que volver a poner la contraseña.
                $reto['intentos']++;
                if ($reto['intentos'] >= (int) config('mfa.max_attempts')) {
                    Cache::forget($clave);
                } else {
                    Cache::put($clave, $reto, Carbon::createFromTimestamp($reto['exp']));
                }
            }
            throw $e;
        }

        Cache::forget($clave); // un solo uso
        [$plano, $tokenId] = $this->almacen->emitirToken($u);
        $correo = $u->credencial?->correo;
        $restantes = $this->almacen->recuperacionRestantes($u->id_usuario);
        $this->auditoria->registrarAutenticacion($u->id_usuario, $correo, true, $ip, $ua, $r['tipo'] === 'recuperacion' ? 'mfa_recuperacion' : 'mfa_totp', $tokenId);
        if ($r['tipo'] === 'recuperacion') {
            $this->auditoria->registrarEvento('mfa.recovery_used', $u->id_usuario, ['ip' => $ip, 'restantes' => $restantes]);
        }

        return ['usuario' => $u, 'token' => $plano, 'via' => (string) $reto['via'], 'extra' => (array) $reto['extra'], 'factor' => $r['tipo'], 'recuperacion_restantes' => $restantes];
    }

    // ------------------------------------------------------------------ retiros

    /** Paso extra para retirar: si el usuario tiene 2FA activo, exige un código vigente en cada solicitud. */
    public function exigirParaRetiro(Usuario $u, ?string $codigo, ?string $recuperacion, string $ip, ?string $ua): void
    {
        if (!$this->habilitado()) {
            return;
        }
        if (!$this->activo($u->id_usuario)) {
            if ($this->rolExige($u)) {
                throw new ReglaNegocioException('Debes activar la autenticación en dos pasos para continuar.', 403, ['mfa_requerido' => true]);
            }

            return;
        }
        if (blank($codigo) && blank($recuperacion)) {
            throw new ReglaNegocioException('Ingresa el código de tu app autenticadora para solicitar el retiro.', 403, ['mfa_codigo_requerido' => true]);
        }
        $this->verificarFactor($u, $codigo, $recuperacion, $ip, $ua);
    }

    // ------------------------------------------------------------------ núcleo

    /** @return array{tipo:string,contador:?int} */
    private function verificarFactor(Usuario $u, ?string $codigo, ?string $recuperacion, string $ip, ?string $ua, int $statusFallo = 422, bool $pendiente = false): array
    {
        $uid = $u->id_usuario;
        $this->bloqueo($u, $ip);
        $fila = $this->almacen->obtener($uid);
        $resultado = null;

        if ($fila && ($pendiente || $fila['confirmado_en'] !== null)) {
            if (!blank($codigo)) {
                try {
                    $secreto = Crypt::decryptString($fila['secreto_cifrado']);
                    $contador = Totp::verificar($secreto, preg_replace('/\s+/', '', (string) $codigo), now()->timestamp, $fila['ultimo_contador'], (int) config('mfa.window'), (int) config('mfa.period'), (int) config('mfa.digits'));
                    if ($contador !== null && $this->almacen->avanzarContador($uid, $contador)) {
                        $resultado = ['tipo' => 'totp', 'contador' => $contador];
                    }
                } catch (DecryptException) {
                    Log::error('MFA: no se pudo descifrar el secreto (¿cambió APP_KEY?).', ['usuario_id' => $uid]);
                }
            } elseif (!blank($recuperacion) && !$pendiente) {
                if ($this->almacen->consumirRecuperacion($uid, $this->hashRecuperacion((string) $recuperacion))) {
                    $resultado = ['tipo' => 'recuperacion', 'contador' => null];
                }
            }
        }

        if ($resultado === null) {
            $this->registrarFallo($u, $ip, $ua, 'MFA: código incorrecto');
            throw new ReglaNegocioException('Código incorrecto o vencido.', $statusFallo);
        }
        RateLimiter::clear($this->claveUsuarioIp($uid, $ip));
        RateLimiter::clear($this->claveUsuario($uid));

        return $resultado;
    }

    private function reautenticar(Usuario $u, ?string $contrasena, ?string $credencialGoogle, string $ip, ?string $ua): void
    {
        $u->loadMissing('credencial');
        $hash = (string) ($u->credencial?->contrasena_hash ?? '');
        if ($hash !== '') {
            $ok = !blank($contrasena) && Hash::check((string) $contrasena, $hash);
            $mensaje = 'La contraseña no es correcta.';
        } else {
            // Cuenta creada con Google (sin contraseña): reautentica con un id_token de Google reciente.
            $ok = false;
            if (!blank($credencialGoogle)) {
                try {
                    $g = $this->google->verificar((string) $credencialGoogle);
                    $sub = (string) ($u->credencial?->google_sub ?? '');
                    $ok = $g['email_verified'] && $sub !== '' && hash_equals($sub, $g['sub']);
                } catch (ReglaNegocioException $e) {
                    if ($e->status !== 401) {
                        throw $e;
                    }
                }
            }
            $mensaje = 'No pudimos confirmar tu identidad con Google. Vuelve a iniciar sesión con Google e intenta de nuevo.';
        }
        if (!$ok) {
            $this->registrarFallo($u, $ip, $ua, 'MFA: reautenticación fallida');
            throw new ReglaNegocioException($mensaje, 422);
        }
    }

    private function bloqueo(Usuario $u, string $ip): void
    {
        foreach ([[$this->claveUsuarioIp($u->id_usuario, $ip), 'max_attempts'], [$this->claveUsuario($u->id_usuario), 'user_max_attempts']] as [$clave, $max]) {
            if (RateLimiter::tooManyAttempts($clave, (int) config("mfa.$max"))) {
                $seg = RateLimiter::availableIn($clave);
                throw new ReglaNegocioException('Demasiados intentos. Intenta de nuevo en ' . max(1, (int) ceil($seg / 60)) . ' minuto(s).', 429, ['retry_after' => $seg]);
            }
        }
    }

    private function registrarFallo(Usuario $u, string $ip, ?string $ua, string $motivo): void
    {
        RateLimiter::hit($this->claveUsuarioIp($u->id_usuario, $ip), (int) config('mfa.decay'));
        RateLimiter::hit($this->claveUsuario($u->id_usuario), (int) config('mfa.user_decay'));
        $u->loadMissing('credencial');
        $this->auditoria->registrarAutenticacion($u->id_usuario, $u->credencial?->correo, false, $ip, $ua, $motivo);
        if (RateLimiter::tooManyAttempts($this->claveUsuarioIp($u->id_usuario, $ip), (int) config('mfa.max_attempts'))) {
            $this->auditoria->registrarEvento('mfa.locked', $u->id_usuario, ['ip' => $ip, 'segundos' => (int) config('mfa.decay')]);
        }
    }

    private function claveUsuarioIp(int $uid, string $ip): string
    {
        return "mfa:$uid:$ip";
    }

    private function claveUsuario(int $uid): string
    {
        return "mfa:$uid";
    }

    private function exigirHabilitado(): void
    {
        if (!$this->habilitado()) {
            throw new ReglaNegocioException('La autenticación en dos pasos no está habilitada.', 404);
        }
    }

    /** @return string[] formato XXXXX-XXXXX (alfabeto sin caracteres ambiguos, 50 bits cada uno) */
    private function generarCodigos(): array
    {
        $codigos = [];
        while (count($codigos) < (int) config('mfa.recovery_codes')) {
            $c = '';
            for ($i = 0; $i < 10; $i++) {
                $c .= self::ALFABETO_RECUPERACION[random_int(0, 31)];
            }
            $codigos[$c] = substr($c, 0, 5) . '-' . substr($c, 5);
        }

        return array_values($codigos);
    }

    /** Alta entropía + un solo uso: HMAC-SHA256 con APP_KEY (bcrypt por código haría lento cada intento). */
    private function hashRecuperacion(string $codigo): string
    {
        return hash_hmac('sha256', preg_replace('/[^A-Z0-9]/', '', strtoupper($codigo)), (string) config('app.key'));
    }
}
