<?php

namespace App\Console\Commands;

use App\Exceptions\ReglaNegocioException;
use App\Service\Auth\Mfa\SvcMfa;
use App\Service\SvcUsuario;
use Illuminate\Console\Command;

/** Último recurso cuando no queda ningún administrador con 2FA para restablecer a otro por la API. */
class MfaResetCommand extends Command
{
    protected $signature = 'playboss:mfa-reset {correo : Correo del usuario} {--motivo=consola : Motivo para la auditoría} {--force : No pedir confirmación}';
    protected $description = 'Quita el 2FA de un usuario y cierra todas sus sesiones (queda auditado en MongoDB)';

    public function handle(SvcUsuario $usuarios, SvcMfa $mfa): int
    {
        $u = $usuarios->buscarPorCorreo((string) $this->argument('correo'));
        if (!$u) {
            $this->error('No existe un usuario con ese correo.');

            return self::FAILURE;
        }
        if (!$this->option('force') && !$this->confirm("¿Quitar el 2FA y cerrar las sesiones de usuario #{$u->id_usuario}?")) {
            return self::SUCCESS;
        }
        try {
            $tenia = $mfa->restablecer($u->id_usuario, null, (string) $this->option('motivo'), '127.0.0.1');
        } catch (ReglaNegocioException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info($tenia ? '2FA restablecido. El usuario deberá configurarlo de nuevo.' : 'El usuario no tenía 2FA; se cerraron sus sesiones.');

        return self::SUCCESS;
    }
}
