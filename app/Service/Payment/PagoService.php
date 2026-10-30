<?php

namespace App\Service\Payment;

use App\Contracts\CheckoutDataProvider;
use App\Contracts\ConsultableGateway;
use App\Contracts\PaymentGateway;
use App\Exceptions\ReglaNegocioException;
use App\Models\Account\Cuenta;
use App\Models\Account\Movimiento;
use App\Models\Payment\EventoPago;
use App\Models\Payment\Transaccion;
use App\Service\Account\AccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PagoService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly AccountService $accounts
    ) {
    }

    public function iniciarDeposito(int $usuarioId, string $monto, string $claveIdempotencia): Transaccion
    {
        $centavos = $this->accounts->aCentavos($monto);
        if (strlen(trim($claveIdempotencia)) < 8 || strlen($claveIdempotencia) > 100) {
            throw ValidationException::withMessages(['idempotency_key' => 'La clave de idempotencia debe tener entre 8 y 100 caracteres.']);
        }
        if ($centavos < (int) config('services.payment_gateway.minimum_amount_minor', 100)) {
            throw ValidationException::withMessages(['monto' => 'El depósito está por debajo del mínimo permitido.']);
        }

        $cuenta = $this->accounts->abrir($usuarioId);
        $transaccion = Transaccion::query()->firstOrCreate(
            ['clave_idempotencia' => $claveIdempotencia],
            [
                'usuario_id' => $usuarioId,
                'metodo_pago_codigo' => config('services.payment_gateway.method_code', 'FIREBASE'),
                'tipo' => 'DEPOSITO',
                'monto' => $monto,
                'moneda' => $cuenta->moneda,
                'estado' => 'PENDIENTE',
                'fecha_hora' => now(),
            ]
        );

        if ((int) $transaccion->usuario_id !== $usuarioId || $transaccion->tipo !== 'DEPOSITO') {
            throw new ReglaNegocioException('La clave de idempotencia ya pertenece a otra transacción.', 409);
        }
        if ($transaccion->referencia_externa !== null) {
            return $transaccion;
        }

        try {
            $intent = $this->gateway->crearIntento([
                'transaction_id' => $transaccion->id_transaccion,
                'user_id' => $usuarioId,
                'amount' => number_format($centavos / 100, 2, '.', ''),
                'currency' => $cuenta->moneda,
            ], $claveIdempotencia);
        } catch (\Throwable $exception) {
            Transaccion::query()->whereKey($transaccion->id_transaccion)
                ->whereNull('referencia_externa')
                ->update(['estado' => 'ERROR']);
            throw $exception;
        }

        if (!in_array($intent['status'], ['PENDING', 'REQUIRES_ACTION'], true)) {
            throw new RuntimeException('La pasarela devolvió un estado no reconocido.');
        }

        return DB::connection('pgsql')->transaction(function () use ($transaccion, $intent): Transaccion {
            $locked = Transaccion::query()->whereKey($transaccion->id_transaccion)->lockForUpdate()->firstOrFail();
            if ($locked->referencia_externa === null) {
                $locked->referencia_externa = $intent['reference'];
                $locked->estado = 'PENDIENTE';
                $locked->url_redireccion = $intent['checkout_url'];
                $locked->save();
            }
            return $locked;
        });
    }

    /**
     * Confirma eventos solo después de que la pasarela (Wompi o Cloud Function) valide la
     * firma del proveedor y el estado real. El crédito y el registro contable comparten
     * una sola transacción PostgreSQL.
     */
    public function confirmarEvento(array $eventoEntrante): Transaccion
    {
        $evento = $this->gateway->verificarEvento($eventoEntrante);
        if (!$evento['verified'] || $evento['event_id'] === '' || $evento['reference'] === '') {
            throw new ReglaNegocioException('El evento de pago no fue verificado.', 401);
        }

        return $this->aplicarEventoVerificado($evento);
    }

    /** Datos que el front necesita para abrir el widget/checkout de la pasarela, si la soporta. */
    public function datosCheckout(Transaccion $transaccion): ?array
    {
        if (!$this->gateway instanceof CheckoutDataProvider || $transaccion->referencia_externa === null) {
            return null;
        }

        return $this->gateway->datosCheckout(
            $transaccion->referencia_externa,
            $this->accounts->aCentavos((string) $transaccion->monto) ,
            $transaccion->moneda ?: config('playboss.currency', 'COP')
        );
    }

    /**
     * Si la transacción sigue pendiente y la pasarela permite consultar, trae el estado real
     * (útil cuando el webhook aún no llega, p. ej. en desarrollo sin túnel público).
     */
    public function sincronizar(Transaccion $transaccion): Transaccion
    {
        if ($transaccion->estado !== 'PENDIENTE' || !$this->gateway instanceof ConsultableGateway || $transaccion->referencia_externa === null) {
            return $transaccion;
        }
        try {
            $evento = $this->gateway->consultarPorReferencia($transaccion->referencia_externa);
            if ($evento && $evento['verified']) {
                return $this->aplicarEventoVerificado($evento);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $transaccion->refresh();
    }

    public function aplicarEventoVerificado(array $evento): Transaccion
    {
        return DB::connection('pgsql')->transaction(function () use ($evento): Transaccion {
            $eventoExistente = EventoPago::query()->where('id_evento_proveedor', $evento['event_id'])->first();
            if ($eventoExistente) {
                return Transaccion::query()->findOrFail($eventoExistente->transaccion_id);
            }

            $transaccion = Transaccion::query()
                ->where('referencia_externa', $evento['reference'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($evento['status'] === 'PENDING') {
                return $transaccion; // sin cambios: aún no hay resultado final
            }

            if ($transaccion->tipo !== 'DEPOSITO'
                || $this->accounts->aCentavos((string) $transaccion->monto) !== $this->accounts->aCentavos($evento['amount'])
                || strtoupper($transaccion->moneda) !== $evento['currency']) {
                throw new ReglaNegocioException('El evento no coincide con el monto, moneda o tipo de la transacción.', 422);
            }

            EventoPago::query()->create([
                'id_evento_proveedor' => $evento['event_id'],
                'transaccion_id' => $transaccion->id_transaccion,
                'tipo_evento' => $evento['status'],
                'verificado' => true,
                'metadata' => ['provider' => config('services.payment_gateway.method_code'), 'status' => $evento['status']],
                'recibido_en' => now(),
            ]);

            if ($evento['status'] === 'APPROVED' && $transaccion->estado !== 'APROBADA') {
                if ($transaccion->estado !== 'PENDIENTE') {
                    throw new ReglaNegocioException('La transacción ya fue cerrada con otro estado.', 409);
                }

                $cuenta = Cuenta::query()->whereKey($transaccion->usuario_id)->lockForUpdate()->firstOrFail();
                $centavos = $this->accounts->aCentavos((string) $transaccion->monto);
                $disponible = $this->accounts->aCentavos((string) $cuenta->saldo_disponible) + $centavos;
                $retenido = $this->accounts->aCentavos((string) $cuenta->saldo_retenido);
                $cuenta->saldo_disponible = $this->accounts->desdeCentavos($disponible);
                $cuenta->save();

                Movimiento::query()->create([
                    'usuario_id' => $transaccion->usuario_id,
                    'tipo' => 'DEPOSITO',
                    'monto' => $this->accounts->desdeCentavos($centavos),
                    'saldo_disponible_resultante' => $this->accounts->desdeCentavos($disponible),
                    'saldo_retenido_resultante' => $this->accounts->desdeCentavos($retenido),
                    'referencia_idempotencia' => 'deposito:transaccion:' . $transaccion->id_transaccion,
                    'transaccion_id' => $transaccion->id_transaccion,
                    'descripcion' => 'Depósito confirmado por proveedor de pago',
                    'creado_en' => now(),
                ]);
                $transaccion->estado = 'APROBADA';
                $transaccion->confirmada_en = now();
            } elseif (in_array($evento['status'], ['DECLINED', 'FAILED', 'CANCELLED'], true)) {
                if ($transaccion->estado !== 'PENDIENTE') {
                    throw new ReglaNegocioException('No se puede cambiar una transacción que ya fue cerrada.', 409);
                }
                $transaccion->estado = $evento['status'];
            } else {
                throw new ReglaNegocioException('Estado del evento de pago no reconocido.', 422);
            }

            $transaccion->save();
            return $transaccion;
        });
    }
}
