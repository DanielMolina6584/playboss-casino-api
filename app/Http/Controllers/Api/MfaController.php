<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Service\Auth\Mfa\SvcMfa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MfaController extends Controller
{
    use RespondsJson;

    public function __construct(private readonly SvcMfa $mfa)
    {
    }

    public function estado(Request $r): JsonResponse
    {
        return $this->ok($this->mfa->estado($r->user()));
    }

    public function configurar(Request $r): JsonResponse
    {
        return $this->ok($this->mfa->configurar($r->user(), $r->ip()), 200, ['Escanea el código QR con tu app autenticadora y confirma con el código de 6 dígitos.']);
    }

    public function activar(Request $r): JsonResponse
    {
        $r->validate(['codigo' => ['required', 'string', 'max:16']]);
        $codigos = $this->mfa->activar($r->user(), (string) $r->input('codigo'), $r->ip(), $r->userAgent());

        return $this->ok(['recuperacion_codigos' => $codigos] + $this->mfa->estado($r->user()), 200, ['Autenticación en dos pasos activada. Guarda tus códigos de recuperación: no se volverán a mostrar.']);
    }

    public function desactivar(Request $r): JsonResponse
    {
        $this->validarFactor($r, ['contrasena' => ['nullable', 'string', 'max:200'], 'credential' => ['nullable', 'string', 'max:4096']]);
        $this->mfa->desactivar($r->user(), $r->input('contrasena'), $r->input('credential'), $r->input('codigo'), $r->input('codigo_recuperacion'), $r->ip(), $r->userAgent());

        return $this->ok($this->mfa->estado($r->user()), 200, ['Autenticación en dos pasos desactivada.']);
    }

    public function regenerarCodigos(Request $r): JsonResponse
    {
        $this->validarFactor($r);
        $codigos = $this->mfa->regenerarCodigos($r->user(), $r->input('codigo'), $r->input('codigo_recuperacion'), $r->ip(), $r->userAgent());

        return $this->ok(['recuperacion_codigos' => $codigos], 200, ['Códigos regenerados. Los anteriores ya no sirven.']);
    }

    /** Canjea el mfa_token + código por el token de API definitivo. */
    public function verificar(Request $r): JsonResponse
    {
        $this->validarFactor($r, ['mfa_token' => ['required', 'string', 'max:100']]);
        $res = $this->mfa->completarReto((string) $r->input('mfa_token'), $r->input('codigo'), $r->input('codigo_recuperacion'), $r->ip(), $r->userAgent());

        $data = ['usuario' => $res['usuario']->datosPublicos(), 'token' => $res['token'], 'factor' => $res['factor'], 'recuperacion_restantes' => $res['recuperacion_restantes']];
        if ($res['via'] === 'google') {
            $incompleto = !$res['usuario']->perfilCompleto();
            $data += ['estado' => $incompleto ? 'perfil_incompleto' : 'ok', 'perfil_incompleto' => $incompleto, 'nuevo' => (bool) ($res['extra']['nuevo'] ?? false)];
        }

        return $this->ok($data);
    }

    /** `codigo` (TOTP) o `codigo_recuperacion`, al menos uno. */
    private function validarFactor(Request $r, array $extra = []): void
    {
        $r->validate($extra + [
            'codigo' => ['nullable', 'string', 'max:16', 'required_without:codigo_recuperacion'],
            'codigo_recuperacion' => ['nullable', 'string', 'max:32', 'required_without:codigo'],
        ]);
    }
}
