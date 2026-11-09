<?php

namespace App\Service\Bet;

use App\Exceptions\ReglaNegocioException;
use App\Models\Account\Cuenta;
use App\Models\Account\Movimiento;
use App\Models\Bet\Apuesta;
use App\Models\Bet\ApuestaDetalle;
use App\Models\Market\Mercado;
use App\Models\Market\ReglaApuesta;
use App\Models\Sports\Partido;
use App\Models\Usuario\PerfilUsuario;
use App\Service\Account\AccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BetService
{
    public function __construct(private readonly AccountService $accounts)
    {
    }

    /**
     * Valida partidos (MySQL) y cuotas/reglas (MongoDB) antes de abrir la transacción
     * financiera (PostgreSQL) y congela las cuotas. Las cuotas del cliente solo se
     * comparan con las del servidor: si cambiaron, se rechaza con 409.
     */
    public function crear(int $usuarioId, string $monto, array $selecciones, string $claveIdempotencia): Apuesta
    {
        if (strlen(trim($claveIdempotencia)) < 8 || strlen($claveIdempotencia) > 100) {
            throw ValidationException::withMessages(['idempotency_key' => 'La clave de idempotencia debe tener entre 8 y 100 caracteres.']);
        }
        BetRules::validarSelecciones($selecciones, (int) config('playboss.bets.max_selections', 10));

        $reintento = Apuesta::query()->where('clave_idempotencia', $claveIdempotencia)->with('detalles')->first();
        if ($reintento) {
            return $this->propia($reintento, $usuarioId);
        }

        $stakeCentavos = $this->accounts->aCentavos($monto);
        if ($stakeCentavos <= 0) {
            throw ValidationException::withMessages(['monto' => 'El monto de la apuesta debe ser mayor que cero.']);
        }

        $snapshots = $this->validarSelecciones($selecciones);
        $cuotaTotal = BetMath::cuotaTotal(array_column($snapshots, 'odd'));
        $pagoCentavos = BetMath::pagoPotencialCentavos($stakeCentavos, $cuotaTotal);
        $reglas = collect($snapshots)->pluck('rule')->unique(fn (ReglaApuesta $rule) => (string) $rule->getKey());
        $montoMinimo = $reglas->max(fn (ReglaApuesta $rule) => $this->accounts->aCentavos((string) $rule->min_stake));
        $montoMaximo = $reglas->min(fn (ReglaApuesta $rule) => $this->accounts->aCentavos((string) $rule->max_stake));
        $pagoMaximo = $reglas->filter(fn (ReglaApuesta $rule) => $rule->max_payout !== null)
            ->min(fn (ReglaApuesta $rule) => $this->accounts->aCentavos((string) $rule->max_payout));
        $edadMinima = max((int) $reglas->max('min_age'), (int) config('playboss.bets.min_age', 18));

        if ($stakeCentavos < $montoMinimo || $stakeCentavos > $montoMaximo) {
            throw ValidationException::withMessages([
                'monto' => 'El monto debe estar entre ' . $this->accounts->desdeCentavos($montoMinimo) . ' y ' . $this->accounts->desdeCentavos($montoMaximo) . '.',
            ]);
        }
        if ($pagoMaximo !== null && $pagoCentavos > $pagoMaximo) {
            throw ValidationException::withMessages(['selecciones' => 'El pago potencial supera el máximo permitido.']);
        }

        $perfil = PerfilUsuario::query()->find($usuarioId);
        if (!$perfil || $perfil->fecha_nacimiento->isFuture() || $perfil->fecha_nacimiento->diffInYears(now()) < $edadMinima) {
            throw ValidationException::withMessages(['usuario' => 'El usuario no cumple la edad mínima para apostar.']);
        }

        return DB::connection('pgsql')->transaction(function () use ($usuarioId, $stakeCentavos, $snapshots, $cuotaTotal, $pagoCentavos, $claveIdempotencia): Apuesta {
            Cuenta::query()->firstOrCreate(['usuario_id' => $usuarioId], [
                'saldo_disponible' => '0.00',
                'saldo_retenido' => '0.00',
                'moneda' => config('playboss.currency', 'COP'),
            ]);
            $cuenta = Cuenta::query()->whereKey($usuarioId)->lockForUpdate()->firstOrFail();

            $existente = Apuesta::query()->where('clave_idempotencia', $claveIdempotencia)->with('detalles')->first();
            if ($existente) {
                return $this->propia($existente, $usuarioId);
            }

            $disponible = $this->accounts->aCentavos((string) $cuenta->saldo_disponible);
            $retenido = $this->accounts->aCentavos((string) $cuenta->saldo_retenido);
            if ($disponible < $stakeCentavos) {
                throw ValidationException::withMessages(['monto' => 'Saldo disponible insuficiente.']);
            }

            $this->validarLimitesDiarios($usuarioId, $stakeCentavos);

            $apuesta = Apuesta::query()->create([
                'usuario_id' => $usuarioId,
                'monto' => $this->accounts->desdeCentavos($stakeCentavos),
                'cuota_total' => number_format($cuotaTotal, 4, '.', ''),
                'ganancia_potencial' => $this->accounts->desdeCentavos($pagoCentavos),
                'estado_codigo' => 'PEND',
                'clave_idempotencia' => $claveIdempotencia,
                'creada_en' => now(),
            ]);

            foreach ($snapshots as $snapshot) {
                ApuestaDetalle::query()->create([
                    'apuesta_id' => $apuesta->id_apuesta,
                    'partido_id_mysql' => $snapshot['match_id'],
                    'mercado_id_mongodb' => $snapshot['market_id'],
                    'seleccion_codigo' => $snapshot['selection_code'],
                    'seleccion_etiqueta' => $snapshot['label'],
                    'cuota_congelada' => number_format($snapshot['odd'], 4, '.', ''),
                ]);
            }

            $nuevoDisponible = $disponible - $stakeCentavos;
            $nuevoRetenido = $retenido + $stakeCentavos;
            $cuenta->saldo_disponible = $this->accounts->desdeCentavos($nuevoDisponible);
            $cuenta->saldo_retenido = $this->accounts->desdeCentavos($nuevoRetenido);
            $cuenta->save();

            Movimiento::query()->create([
                'usuario_id' => $usuarioId,
                'tipo' => 'APUESTA_RESERVADA',
                'monto' => $this->accounts->desdeCentavos($stakeCentavos),
                'saldo_disponible_resultante' => $this->accounts->desdeCentavos($nuevoDisponible),
                'saldo_retenido_resultante' => $this->accounts->desdeCentavos($nuevoRetenido),
                'referencia_idempotencia' => 'apuesta:' . $claveIdempotencia,
                'apuesta_id' => $apuesta->id_apuesta,
                'descripcion' => 'Reserva de fondos para apuesta',
                'creado_en' => now(),
            ]);

            return $apuesta->load('detalles');
        });
    }

    /**
     * Cancela una apuesta pendiente mientras ningún partido haya comenzado y
     * devuelve el monto reservado. Idempotente: repetir devuelve la apuesta ya anulada.
     */
    public function cancelar(int $usuarioId, int $apuestaId): Apuesta
    {
        $apuesta = Apuesta::query()->with('detalles')->find($apuestaId);
        if (!$apuesta || (int) $apuesta->usuario_id !== $usuarioId) {
            throw new ReglaNegocioException('Apuesta no encontrada.', 404);
        }
        if ($apuesta->estado_codigo === 'ANU') {
            return $apuesta;
        }
        if ($apuesta->estado_codigo !== 'PEND') {
            throw new ReglaNegocioException('Solo se pueden cancelar apuestas pendientes.', 409);
        }

        $ids = $apuesta->detalles->pluck('partido_id_mysql')->unique()->all();
        $partidos = Partido::query()->whereIn('id_partido', $ids)->get();
        $iniciado = $partidos->count() !== count($ids)
            || $partidos->contains(fn (Partido $p) => $p->estado_codigo !== 'PROG' || $p->fecha_hora->lte(now()));
        if ($iniciado) {
            throw new ReglaNegocioException('Un partido de la apuesta ya comenzó; no se puede cancelar.', 409);
        }

        return DB::connection('pgsql')->transaction(function () use ($apuestaId, $usuarioId): Apuesta {
            $cuenta = Cuenta::query()->whereKey($usuarioId)->lockForUpdate()->firstOrFail();
            $apuesta = Apuesta::query()->whereKey($apuestaId)->lockForUpdate()->firstOrFail();
            if ($apuesta->estado_codigo === 'ANU') {
                return $apuesta->load('detalles');
            }
            if ($apuesta->estado_codigo !== 'PEND') {
                throw new ReglaNegocioException('Solo se pueden cancelar apuestas pendientes.', 409);
            }

            $stake = $this->accounts->aCentavos((string) $apuesta->monto);
            $disponible = $this->accounts->aCentavos((string) $cuenta->saldo_disponible) + $stake;
            $retenido = max(0, $this->accounts->aCentavos((string) $cuenta->saldo_retenido) - $stake);
            $cuenta->saldo_disponible = $this->accounts->desdeCentavos($disponible);
            $cuenta->saldo_retenido = $this->accounts->desdeCentavos($retenido);
            $cuenta->save();

            Movimiento::query()->create([
                'usuario_id' => $usuarioId,
                'tipo' => 'APUESTA_ANULADA',
                'monto' => $this->accounts->desdeCentavos($stake),
                'saldo_disponible_resultante' => $this->accounts->desdeCentavos($disponible),
                'saldo_retenido_resultante' => $this->accounts->desdeCentavos($retenido),
                'referencia_idempotencia' => 'cancelacion:apuesta:' . $apuesta->id_apuesta,
                'apuesta_id' => $apuesta->id_apuesta,
                'descripcion' => 'Cancelación de apuesta por el usuario',
                'creado_en' => now(),
            ]);
            $apuesta->estado_codigo = 'ANU';
            $apuesta->liquidada_en = now();
            $apuesta->save();

            return $apuesta->load('detalles');
        });
    }

    private function propia(Apuesta $apuesta, int $usuarioId): Apuesta
    {
        if ((int) $apuesta->usuario_id !== $usuarioId) {
            throw new ReglaNegocioException('La clave de idempotencia ya pertenece a otra apuesta.', 409);
        }

        return $apuesta;
    }

    /** Se invoca con la cuenta bloqueada, por lo que dos apuestas simultáneas no superan el tope. */
    private function validarLimitesDiarios(int $usuarioId, int $stakeCentavos): void
    {
        $inicio = now()->startOfDay();
        $fila = Apuesta::query()->where('usuario_id', $usuarioId)->where('creada_en', '>=', $inicio)
            ->where('estado_codigo', '!=', 'ANU')
            ->selectRaw('COALESCE(SUM(monto), 0) AS total, COUNT(*) AS cantidad')->first();
        $total = $this->accounts->aCentavos(number_format((float) $fila->total, 2, '.', ''));
        $limite = $this->accounts->aCentavos((string) config('playboss.bets.daily_stake_limit', '5000000.00'));
        if ($total + $stakeCentavos > $limite) {
            throw ValidationException::withMessages(['monto' => 'Superas el límite diario de apuestas.']);
        }
        if ((int) $fila->cantidad >= (int) config('playboss.bets.daily_bet_count_limit', 100)) {
            throw ValidationException::withMessages(['monto' => 'Superas el número máximo de apuestas por día.']);
        }
    }

    private function validarSelecciones(array $selecciones): array
    {
        $snapshots = [];
        foreach ($selecciones as $seleccion) {
            $partidoId = filter_var($seleccion['match_id'] ?? null, FILTER_VALIDATE_INT);
            $marketId = (string) ($seleccion['market_id'] ?? '');
            $codigo = (string) ($seleccion['selection_code'] ?? '');

            if (!$partidoId || $marketId === '' || $codigo === '') {
                throw ValidationException::withMessages(['selecciones' => 'Cada selección necesita partido, mercado y opción.']);
            }
            $partido = Partido::query()->select(['id_partido', 'estado_codigo', 'fecha_hora'])->whereKey($partidoId)->first();
            if (!$partido) {
                throw ValidationException::withMessages(['selecciones' => 'Uno de los partidos no existe.']);
            }
            if ($partido->estado_codigo !== 'PROG' || $partido->fecha_hora->lte(now())) {
                throw ValidationException::withMessages(['selecciones' => 'Uno de los partidos ya comenzó o no admite apuestas.']);
            }

            $mercado = Mercado::query()->whereKey($marketId)->where('match_id', $partidoId)->where('status', 'ACTIVE')->first();
            if (!$mercado) {
                throw ValidationException::withMessages(['selecciones' => 'Uno de los mercados ya no está disponible.']);
            }

            $opcion = collect($mercado->selections ?? [])->firstWhere('code', $codigo);
            $odd = (float) ($opcion['odd'] ?? 0);
            if (!$opcion || $odd <= 1) {
                throw ValidationException::withMessages(['selecciones' => 'La opción o su cuota ya no son válidas.']);
            }
            if (isset($seleccion['odd']) && $seleccion['odd'] !== null && abs((float) $seleccion['odd'] - $odd) > 0.00001) {
                throw new ReglaNegocioException('La cuota cambió; actualiza tu boleta.', 409, [
                    'selecciones' => ['market_id' => $marketId, 'selection_code' => $codigo, 'odd_actual' => $odd],
                ]);
            }

            $regla = ReglaApuesta::query()
                ->where('market_code', $mercado->code)
                ->where('active', true)
                ->where('effective_from', '<=', now())
                ->orderByDesc('effective_from')
                ->first();
            if (!$regla) {
                throw ValidationException::withMessages(['selecciones' => 'El mercado no tiene una regla de apuesta vigente.']);
            }

            $snapshots[] = [
                'match_id' => $partidoId,
                'market_id' => $marketId,
                'selection_code' => $codigo,
                'label' => (string) ($opcion['label'] ?? $codigo),
                'odd' => $odd,
                'rule' => $regla,
            ];
        }

        return $snapshots;
    }
}
