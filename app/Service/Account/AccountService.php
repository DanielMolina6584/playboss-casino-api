<?php

namespace App\Service\Account;

use App\Models\Account\Cuenta;
use App\Models\Account\Movimiento;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AccountService
{
    public function abrir(int $usuarioId): Cuenta
    {
        return DB::connection('pgsql')->transaction(function () use ($usuarioId): Cuenta {
            Cuenta::query()->firstOrCreate(['usuario_id' => $usuarioId], [
                'saldo_disponible' => '0.00',
                'saldo_retenido' => '0.00',
                'moneda' => config('playboss.currency', 'COP'),
            ]);

            return Cuenta::query()->whereKey($usuarioId)->lockForUpdate()->firstOrFail();
        });
    }

    public function acreditar(int $usuarioId, string $monto, string $claveIdempotencia, ?int $transaccionId = null): Movimiento
    {
        return $this->ajustar($usuarioId, $monto, $claveIdempotencia, 'DEPOSITO', true, $transaccionId);
    }

    public function debitar(int $usuarioId, string $monto, string $claveIdempotencia, string $tipo = 'RETIRO'): Movimiento
    {
        return $this->ajustar($usuarioId, $monto, $claveIdempotencia, $tipo, false);
    }

    private function ajustar(
        int $usuarioId,
        string $monto,
        string $claveIdempotencia,
        string $tipo,
        bool $acreditar,
        ?int $transaccionId = null
    ): Movimiento {
        $centavos = $this->aCentavos($monto);
        if ($centavos <= 0 || trim($claveIdempotencia) === '') {
            throw ValidationException::withMessages(['monto' => 'El monto y la clave de idempotencia son obligatorios.']);
        }

        return DB::connection('pgsql')->transaction(function () use ($usuarioId, $centavos, $claveIdempotencia, $tipo, $acreditar, $transaccionId): Movimiento {
            $existente = Movimiento::query()->where('referencia_idempotencia', $claveIdempotencia)->first();
            if ($existente) {
                if ((int) $existente->usuario_id !== $usuarioId || $existente->tipo !== $tipo) {
                    throw new RuntimeException('La clave de idempotencia ya pertenece a otra operación.');
                }
                return $existente;
            }

            $cuenta = Cuenta::query()->firstOrCreate(['usuario_id' => $usuarioId], [
                'saldo_disponible' => '0.00',
                'saldo_retenido' => '0.00',
                'moneda' => config('playboss.currency', 'COP'),
            ]);
            $cuenta = Cuenta::query()->whereKey($usuarioId)->lockForUpdate()->firstOrFail();
            $existente = Movimiento::query()->where('referencia_idempotencia', $claveIdempotencia)->first();
            if ($existente) {
                if ((int) $existente->usuario_id !== $usuarioId || $existente->tipo !== $tipo) {
                    throw new RuntimeException('La clave de idempotencia ya pertenece a otra operación.');
                }
                return $existente;
            }
            $disponible = $this->aCentavos((string) $cuenta->saldo_disponible);
            $retenido = $this->aCentavos((string) $cuenta->saldo_retenido);
            $nuevoDisponible = $acreditar ? $disponible + $centavos : $disponible - $centavos;

            if ($nuevoDisponible < 0) {
                throw ValidationException::withMessages(['monto' => 'Saldo disponible insuficiente.']);
            }

            $cuenta->saldo_disponible = $this->desdeCentavos($nuevoDisponible);
            $cuenta->save();

            return Movimiento::query()->create([
                'usuario_id' => $usuarioId,
                'tipo' => $tipo,
                'monto' => $this->desdeCentavos($centavos),
                'saldo_disponible_resultante' => $this->desdeCentavos($nuevoDisponible),
                'saldo_retenido_resultante' => $this->desdeCentavos($retenido),
                'referencia_idempotencia' => $claveIdempotencia,
                'transaccion_id' => $transaccionId,
                'descripcion' => $tipo,
                'creado_en' => now(),
            ]);
        });
    }

    public function aCentavos(string $monto): int
    {
        $monto = trim($monto);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $monto)) {
            throw ValidationException::withMessages(['monto' => 'El monto debe ser positivo y tener hasta dos decimales.']);
        }

        [$unidades, $decimales] = array_pad(explode('.', $monto, 2), 2, '');
        return ((int) $unidades * 100) + (int) str_pad($decimales, 2, '0');
    }

    public function desdeCentavos(int $centavos): string
    {
        return intdiv($centavos, 100) . '.' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }
}
