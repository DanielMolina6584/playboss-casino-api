<?php

namespace App\Service\Payment;

use App\Exceptions\ReglaNegocioException;
use App\Models\Account\Cuenta;
use App\Models\Account\Movimiento;
use App\Models\Payment\Retiro;
use App\Models\Usuario\Usuario;
use App\Service\Account\AccountService;
use App\Service\Storage\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Retiros como SOLICITUD pendiente: al pedirlo el saldo pasa de disponible a retenido;
 * un administrador lo aprueba (retenido sale como pagado) o lo rechaza (vuelve a disponible).
 *
 * TODO(payout): la aprobación solo marca el retiro como PAGADO. Falta ejecutar la
 * transferencia real (Wompi Payouts / ACH) y reflejar su resultado antes de marcarlo.
 */
class RetiroService
{
    public function __construct(private readonly AccountService $accounts, private readonly AuditLogService $audit)
    {
    }

    /** @param array{banco:string,tipo_cuenta:string,numero_cuenta:string,titular:string,documento_titular:string} $destino */
    public function solicitar(int $usuarioId, string $monto, array $destino, string $clave): Retiro
    {
        if (strlen(trim($clave)) < 8 || strlen($clave) > 100) {
            throw ValidationException::withMessages(['idempotency_key' => 'La clave de idempotencia debe tener entre 8 y 100 caracteres.']);
        }
        $reintento = Retiro::query()->where('clave_idempotencia', $clave)->first();
        if ($reintento) {
            return $this->propio($reintento, $usuarioId);
        }

        $centavos = $this->accounts->aCentavos($monto);
        $min = $this->accounts->aCentavos((string) config('playboss.withdrawals.min_amount', '10000.00'));
        $max = $this->accounts->aCentavos((string) config('playboss.withdrawals.max_amount', '5000000.00'));
        if ($centavos < $min || $centavos > $max) {
            throw ValidationException::withMessages(['monto' => 'El retiro debe estar entre ' . $this->accounts->desdeCentavos($min) . ' y ' . $this->accounts->desdeCentavos($max) . '.']);
        }

        $usuario = Usuario::query()->with('perfil')->find($usuarioId);
        if (!$usuario || !$usuario->estaActivo()) {
            throw new ReglaNegocioException('La cuenta está inactiva.', 403);
        }
        $perfil = $usuario->perfil;
        $edadMinima = (int) config('playboss.bets.min_age', 18);
        if (!$perfil || !$perfil->fecha_nacimiento || $perfil->fecha_nacimiento->isFuture() || $perfil->fecha_nacimiento->diffInYears(now()) < $edadMinima) {
            throw ValidationException::withMessages(['usuario' => 'El usuario no cumple la edad mínima para retirar.']);
        }
        // La cuenta destino debe ser del propio usuario (anti-fraude): el documento del titular debe coincidir.
        if (preg_replace('/\D/', '', (string) $perfil->numero_documento) !== preg_replace('/\D/', '', $destino['documento_titular'])) {
            throw ValidationException::withMessages(['documento_titular' => 'La cuenta destino debe pertenecer al titular de la cuenta (el documento no coincide).']);
        }

        $retiro = DB::connection('pgsql')->transaction(function () use ($usuarioId, $centavos, $destino, $clave): Retiro {
            Cuenta::query()->firstOrCreate(['usuario_id' => $usuarioId], [
                'saldo_disponible' => '0.00', 'saldo_retenido' => '0.00', 'moneda' => config('playboss.currency', 'COP'),
            ]);
            $cuenta = Cuenta::query()->whereKey($usuarioId)->lockForUpdate()->firstOrFail();

            $existente = Retiro::query()->where('clave_idempotencia', $clave)->first();
            if ($existente) {
                return $this->propio($existente, $usuarioId);
            }
            $pendientes = Retiro::query()->where('usuario_id', $usuarioId)->where('estado', 'PENDIENTE')->count();
            if ($pendientes >= (int) config('playboss.withdrawals.max_pending', 3)) {
                throw ValidationException::withMessages(['monto' => 'Tienes demasiadas solicitudes de retiro pendientes.']);
            }

            $disponible = $this->accounts->aCentavos((string) $cuenta->saldo_disponible);
            $retenido = $this->accounts->aCentavos((string) $cuenta->saldo_retenido);
            if ($disponible < $centavos) {
                throw ValidationException::withMessages(['monto' => 'Saldo disponible insuficiente.']);
            }

            $retiro = Retiro::query()->create([
                'usuario_id' => $usuarioId,
                'monto' => $this->accounts->desdeCentavos($centavos),
                'moneda' => $cuenta->moneda,
                'estado' => 'PENDIENTE',
                'clave_idempotencia' => $clave,
                'banco' => $destino['banco'],
                'tipo_cuenta' => $destino['tipo_cuenta'],
                'numero_cuenta' => $destino['numero_cuenta'],
                'titular' => $destino['titular'],
                'documento_titular' => $destino['documento_titular'],
                'solicitado_en' => now(),
            ]);

            $this->mover($cuenta, $usuarioId, $disponible - $centavos, $retenido + $centavos, 'RETIRO_RESERVADO', $centavos, 'retiro:reserva:' . $retiro->id_retiro, 'Reserva de fondos para retiro');

            return $retiro;
        });

        if ($retiro->wasRecentlyCreated) {
            $this->audit->registrarEvento('withdrawal.requested', $usuarioId, ['retiro_id' => $retiro->id_retiro, 'monto' => (string) $retiro->monto]);
        }

        return $retiro;
    }

    /** Marca el retiro como pagado y consume el saldo retenido. Idempotente. TODO(payout): transferencia real. */
    public function aprobar(int $adminId, int $retiroId): Retiro
    {
        return $this->resolver($adminId, $retiroId, 'PAGADO', null);
    }

    /** Rechaza el retiro y devuelve el saldo retenido a disponible. Idempotente. */
    public function rechazar(int $adminId, int $retiroId, string $motivo): Retiro
    {
        return $this->resolver($adminId, $retiroId, 'RECHAZADO', $motivo);
    }

    private function resolver(int $adminId, int $retiroId, string $destino, ?string $motivo): Retiro
    {
        $retiro = DB::connection('pgsql')->transaction(function () use ($adminId, $retiroId, $destino, $motivo): Retiro {
            $retiro = Retiro::query()->whereKey($retiroId)->lockForUpdate()->first();
            if (!$retiro) {
                throw new ReglaNegocioException('Retiro no encontrado.', 404);
            }
            if ($retiro->estado === $destino) {
                return $retiro;
            }
            if ($retiro->estado !== 'PENDIENTE') {
                throw new ReglaNegocioException('El retiro ya fue resuelto como ' . $retiro->estado . '.', 409);
            }

            $cuenta = Cuenta::query()->whereKey($retiro->usuario_id)->lockForUpdate()->firstOrFail();
            $centavos = $this->accounts->aCentavos((string) $retiro->monto);
            $disponible = $this->accounts->aCentavos((string) $cuenta->saldo_disponible);
            $retenido = $this->accounts->aCentavos((string) $cuenta->saldo_retenido);
            if ($retenido < $centavos) {
                throw new ReglaNegocioException('Saldo retenido inconsistente para este retiro.', 409);
            }

            if ($destino === 'PAGADO') {
                $this->mover($cuenta, (int) $retiro->usuario_id, $disponible, $retenido - $centavos, 'RETIRO_PAGADO', $centavos, 'retiro:pago:' . $retiro->id_retiro, 'Retiro aprobado');
            } else {
                $this->mover($cuenta, (int) $retiro->usuario_id, $disponible + $centavos, $retenido - $centavos, 'RETIRO_LIBERADO', $centavos, 'retiro:liberacion:' . $retiro->id_retiro, 'Retiro rechazado: saldo liberado');
            }

            $retiro->estado = $destino;
            $retiro->admin_id = $adminId;
            $retiro->motivo_rechazo = $motivo;
            $retiro->resuelto_en = now();
            $retiro->save();

            return $retiro;
        });

        $this->audit->registrarEvento($destino === 'PAGADO' ? 'withdrawal.approved' : 'withdrawal.rejected', $adminId, ['retiro_id' => $retiro->id_retiro, 'usuario_id' => $retiro->usuario_id]);

        return $retiro;
    }

    private function mover(Cuenta $cuenta, int $usuarioId, int $disponible, int $retenido, string $tipo, int $monto, string $referencia, string $descripcion): void
    {
        $cuenta->saldo_disponible = $this->accounts->desdeCentavos($disponible);
        $cuenta->saldo_retenido = $this->accounts->desdeCentavos($retenido);
        $cuenta->save();

        Movimiento::query()->create([
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
            'monto' => $this->accounts->desdeCentavos($monto),
            'saldo_disponible_resultante' => $this->accounts->desdeCentavos($disponible),
            'saldo_retenido_resultante' => $this->accounts->desdeCentavos($retenido),
            'referencia_idempotencia' => $referencia,
            'descripcion' => $descripcion,
            'creado_en' => now(),
        ]);
    }

    private function propio(Retiro $retiro, int $usuarioId): Retiro
    {
        if ((int) $retiro->usuario_id !== $usuarioId) {
            throw new ReglaNegocioException('La clave de idempotencia ya pertenece a otro retiro.', 409);
        }

        return $retiro;
    }
}
