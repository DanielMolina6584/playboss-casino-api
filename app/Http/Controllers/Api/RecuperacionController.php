<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Http\Requests\RecuperarContrasenaRequest;
use App\Http\Requests\RestablecerContrasenaRequest;
use App\Models\Usuario\CodigoRecuperacion;
use App\Service\SvcUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Recuperación de contraseña con código de 6 dígitos (hash en SQL Server, 15 min, un solo uso). */
class RecuperacionController extends Controller
{
    use RespondsJson;

    private const MENSAJE = 'Si el correo existe, recibirás un código de recuperación.';

    public function __construct(private readonly SvcUsuario $usuarios)
    {
    }

    public function solicitar(RecuperarContrasenaRequest $r): JsonResponse
    {
        $usuario = $this->usuarios->buscarPorCorreo($r->input('correo'));
        if ($usuario && $usuario->estaActivo()) {
            $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            CodigoRecuperacion::query()->where('usuario_id', $usuario->id_usuario)->whereNull('usado_en')->update(['usado_en' => now()]);
            CodigoRecuperacion::query()->create([
                'usuario_id' => $usuario->id_usuario,
                'codigo_hash' => Hash::make($codigo),
                'fecha_expiracion' => now()->addMinutes(15),
            ]);
            try {
                Mail::raw("Tu código de recuperación PlayBoss es {$codigo}. Vence en 15 minutos.", fn ($m) => $m->to($usuario->credencial->correo)->subject('Recuperación de contraseña'));
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar el correo de recuperación.', ['exception' => $e::class]);
            }
        }

        return $this->ok([], 200, [self::MENSAJE]);
    }

    public function restablecer(RestablecerContrasenaRequest $r): JsonResponse
    {
        $usuario = $this->usuarios->buscarPorCorreo($r->input('correo'));
        $invalido = $this->fail(['Código inválido o vencido.'], 422);
        if (!$usuario || !$usuario->estaActivo()) {
            return $invalido;
        }

        $registro = CodigoRecuperacion::query()->where('usuario_id', $usuario->id_usuario)
            ->whereNull('usado_en')->where('fecha_expiracion', '>', now())
            ->orderByDesc('id_codigo')->first();
        if (!$registro || !Hash::check($r->input('codigo'), $registro->codigo_hash)) {
            return $invalido;
        }

        DB::connection('sqlsrv')->transaction(function () use ($registro, $usuario, $r) {
            $registro->usado_en = now();
            $registro->save();
            $usuario->credencial->contrasena_hash = Hash::make($r->input('contrasena'));
            $usuario->credencial->save();
            $usuario->tokens()->delete();
        });

        return $this->ok([], 200, ['Contraseña restablecida. Inicia sesión.']);
    }
}
