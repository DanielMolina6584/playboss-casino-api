<?php

namespace App\Service\Auth\Mfa;

use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioMfa;
use App\Models\Usuario\UsuarioMfaRecuperacion;
use Illuminate\Support\Facades\DB;

class MfaAlmacenEloquent implements MfaAlmacen
{
    public function usuario(int $usuarioId): ?Usuario
    {
        return Usuario::with(['credencial', 'perfil'])->find($usuarioId);
    }

    public function obtener(int $usuarioId): ?array
    {
        $m = UsuarioMfa::query()->find($usuarioId);

        return $m ? ['secreto_cifrado' => $m->secreto_cifrado, 'confirmado_en' => $m->confirmado_en, 'ultimo_contador' => $m->ultimo_contador] : null;
    }

    public function guardarPendiente(int $usuarioId, string $secretoCifrado): void
    {
        UsuarioMfa::query()->updateOrCreate(['usuario_id' => $usuarioId], [
            'secreto_cifrado' => $secretoCifrado, 'confirmado_en' => null, 'ultimo_contador' => null, 'creado_en' => now(),
        ]);
        UsuarioMfaRecuperacion::query()->where('usuario_id', $usuarioId)->delete();
    }

    public function confirmar(int $usuarioId, int $contador, array $hashesRecuperacion): void
    {
        DB::connection('sqlsrv')->transaction(function () use ($usuarioId, $contador, $hashesRecuperacion): void {
            UsuarioMfa::query()->whereKey($usuarioId)->update(['confirmado_en' => now(), 'ultimo_contador' => $contador]);
            $this->reemplazarRecuperacion($usuarioId, $hashesRecuperacion);
        });
    }

    public function avanzarContador(int $usuarioId, int $contador): bool
    {
        return UsuarioMfa::query()->whereKey($usuarioId)
            ->where(fn ($q) => $q->whereNull('ultimo_contador')->orWhere('ultimo_contador', '<', $contador))
            ->update(['ultimo_contador' => $contador]) === 1;
    }

    public function reemplazarRecuperacion(int $usuarioId, array $hashes): void
    {
        UsuarioMfaRecuperacion::query()->where('usuario_id', $usuarioId)->delete();
        foreach ($hashes as $h) {
            UsuarioMfaRecuperacion::query()->create(['usuario_id' => $usuarioId, 'codigo_hash' => $h, 'creado_en' => now()]);
        }
    }

    public function consumirRecuperacion(int $usuarioId, string $hash): bool
    {
        return UsuarioMfaRecuperacion::query()->where('usuario_id', $usuarioId)->where('codigo_hash', $hash)->whereNull('usado_en')
            ->update(['usado_en' => now()]) === 1;
    }

    public function recuperacionRestantes(int $usuarioId): int
    {
        return UsuarioMfaRecuperacion::query()->where('usuario_id', $usuarioId)->whereNull('usado_en')->count();
    }

    public function eliminar(int $usuarioId): void
    {
        DB::connection('sqlsrv')->transaction(function () use ($usuarioId): void {
            UsuarioMfaRecuperacion::query()->where('usuario_id', $usuarioId)->delete();
            UsuarioMfa::query()->whereKey($usuarioId)->delete();
        });
    }

    public function emitirToken(Usuario $usuario): array
    {
        $t = $usuario->createToken('playboss-api');

        return [$t->plainTextToken, (int) $t->accessToken->id];
    }

    public function revocarSesiones(int $usuarioId, ?int $excepto = null): void
    {
        $q = Usuario::query()->findOrFail($usuarioId)->tokens();
        $excepto !== null && $q->where('id', '!=', $excepto);
        $q->delete();
    }
}
