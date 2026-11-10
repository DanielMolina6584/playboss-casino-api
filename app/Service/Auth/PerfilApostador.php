<?php

namespace App\Service\Auth;

use App\Exceptions\ReglaNegocioException;
use App\Models\Usuario\PerfilUsuario;

/** Exige perfil completo (fecha de nacimiento y documento) y mayoría de edad antes de apostar o depositar. */
class PerfilApostador
{
    public static function exigir(int $usuarioId): void
    {
        $perfil = PerfilUsuario::query()->find($usuarioId);

        if (!$perfil || $perfil->fecha_nacimiento === null || !$perfil->tipo_documento_codigo || !$perfil->numero_documento) {
            throw new ReglaNegocioException('Completa tu perfil (fecha de nacimiento y documento) antes de continuar.', 403, ['perfil_incompleto' => true]);
        }

        if ($perfil->fecha_nacimiento->isFuture() || $perfil->fecha_nacimiento->diffInYears(now()) < (int) config('playboss.bets.min_age', 18)) {
            throw new ReglaNegocioException('Debes ser mayor de edad para realizar esta operación.', 403);
        }
    }
}
