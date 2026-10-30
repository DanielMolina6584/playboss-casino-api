<?php

namespace App\Service\Bet;

use App\Exceptions\ReglaNegocioException;
use App\Models\Account\Cuenta;
use App\Models\Account\Movimiento;
use App\Models\Bet\Apuesta;
use App\Models\Bet\ApuestaDetalle;
use App\Models\Market\Mercado;
use App\Models\Sports\Partido;
use App\Service\Account\AccountService;
use App\Service\Storage\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Liquidación: marcador -> opción ganadora por mercado -> resultado por selección
 * -> estado de cada apuesta -> abono del premio. Idempotente: se puede repetir sin
 * pagar dos veces (estado bloqueado con FOR UPDATE y referencia de movimiento única).
 */
class SettlementService
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly AuditLogService $audit
    ) {
    }

    /**
     * @param array<string,string> $ganadoras  override: market_id => selection_code (o 'VOID' para anular el mercado)
     * @return array{partido_id:int,apuestas_liquidadas:int,mercados_sin_resolver:array}
     */
    public function liquidarPartido(int $partidoId, int $golesLocal, int $golesVisitante, array $ganadoras = [], ?int $adminId = null): array
    {
        $partido = Partido::query()->find($partidoId);
        if (!$partido) {
            throw new ReglaNegocioException('Partido no encontrado.', 404);
        }
        if ($partido->estado_codigo === 'FIN' && ($partido->goles_local !== $golesLocal || $partido->goles_visitante !== $golesVisitante)) {
            throw new ReglaNegocioException('El partido ya fue liquidado con otro marcador.', 409);
        }

        $partido->goles_local = $golesLocal;
        $partido->goles_visitante = $golesVisitante;
        $partido->estado_codigo = 'FIN';
        $partido->save();

        $sinResolver = [];
        foreach (Mercado::query()->where('match_id', $partidoId)->get() as $mercado) {
            $id = (string) $mercado->getKey();
            $ganadora = $ganadoras[$id] ?? $ganadoras[$mercado->code] ?? MarketResolver::ganadora($mercado->code, $golesLocal, $golesVisitante);
            if ($ganadora === null) {
                $sinResolver[] = ['market_id' => $id, 'code' => $mercado->code];
                continue;
            }
            $mercado->status = 'SETTLED';
            $mercado->save();
            $this->resolverDetalles($partidoId, $id, $ganadora);
        }

        $liquidadas = 0;
        $ids = ApuestaDetalle::query()->where('partido_id_mysql', $partidoId)->distinct()->pluck('apuesta_id');
        foreach ($ids as $apuestaId) {
            if ($this->evaluarApuesta((int) $apuestaId)) {
                $liquidadas++;
            }
        }

        $this->audit->registrarEvento('match_settled', $adminId, [
            'match_id' => $partidoId, 'score' => [$golesLocal, $golesVisitante], 'bets_settled' => $liquidadas, 'unresolved' => $sinResolver,
        ]);

        return ['partido_id' => $partidoId, 'apuestas_liquidadas' => $liquidadas, 'mercados_sin_resolver' => $sinResolver];
    }

    /** Anula una apuesta pendiente y devuelve el monto (decisión administrativa). */
    public function anularApuesta(int $apuestaId, ?int $adminId = null): Apuesta
    {
        $apuesta = DB::connection('pgsql')->transaction(function () use ($apuestaId): Apuesta {
            $apuesta = Apuesta::query()->whereKey($apuestaId)->lockForUpdate()->first();
            if (!$apuesta) {
                throw new ReglaNegocioException('Apuesta no encontrada.', 404);
            }
            if ($apuesta->estado_codigo === 'ANU') {
                return $apuesta;
            }
            if ($apuesta->estado_codigo !== 'PEND') {
                throw new ReglaNegocioException('La apuesta ya fue liquidada.', 409);
            }
            $stake = $this->accounts->aCentavos((string) $apuesta->monto);
            $this->cerrar($apuesta, 'ANU', 0, $stake, 'APUESTA_ANULADA', 'Anulación administrativa');

            return $apuesta;
        });
        $this->audit->registrarEvento('bet_voided', $adminId, ['bet_id' => $apuestaId]);

        return $apuesta->load('detalles');
    }

    private function resolverDetalles(int $partidoId, string $marketId, string $ganadora): void
    {
        $detalles = ApuestaDetalle::query()
            ->where('partido_id_mysql', $partidoId)->where('mercado_id_mongodb', $marketId)
            ->whereNull('resultado')->get();
        foreach ($detalles as $detalle) {
            $detalle->resultado = $ganadora === 'VOID' ? 'ANU' : ($detalle->seleccion_codigo === $ganadora ? 'GAN' : 'PER');
            $detalle->save();
        }
    }

    /** @return bool true si esta llamada cerró la apuesta */
    private function evaluarApuesta(int $apuestaId): bool
    {
        return DB::connection('pgsql')->transaction(function () use ($apuestaId): bool {
            $apuesta = Apuesta::query()->whereKey($apuestaId)->lockForUpdate()->first();
            if (!$apuesta || $apuesta->estado_codigo !== 'PEND') {
                return false;
            }
            $detalles = ApuestaDetalle::query()->where('apuesta_id', $apuestaId)->get();
            $stake = $this->accounts->aCentavos((string) $apuesta->monto);

            if ($detalles->contains('resultado', 'PER')) {
                $this->cerrar($apuesta, 'PER', 0, $stake, 'APUESTA_PERDIDA', 'Apuesta perdida');
                return true;
            }
            if ($detalles->contains(fn ($d) => $d->resultado === null)) {
                return false;
            }

            $ganadoras = $detalles->where('resultado', 'GAN');
            if ($ganadoras->isEmpty()) { // todas anuladas
                $this->cerrar($apuesta, 'ANU', 0, $stake, 'APUESTA_ANULADA', 'Apuesta anulada');
                return true;
            }
            // Selecciones anuladas cuentan con cuota 1.
            $cuota = BetMath::cuotaTotal($ganadoras->map(fn ($d) => (float) $d->cuota_congelada)->all());
            $premio = BetMath::pagoPotencialCentavos($stake, $cuota);
            $this->cerrar($apuesta, 'GAN', $premio, $stake, 'APUESTA_GANADA', 'Premio de apuesta');

            return true;
        });
    }

    /** Libera lo retenido, abona $abono al disponible y deja el movimiento contable. Debe correr en transacción. */
    private function cerrar(Apuesta $apuesta, string $estado, int $abonoCentavos, int $stakeCentavos, string $tipo, string $descripcion): void
    {
        $cuenta = Cuenta::query()->whereKey($apuesta->usuario_id)->lockForUpdate()->firstOrFail();
        $disponible = $this->accounts->aCentavos((string) $cuenta->saldo_disponible);
        $retenido = max(0, $this->accounts->aCentavos((string) $cuenta->saldo_retenido) - $stakeCentavos);
        $reembolso = $estado === 'ANU' ? $stakeCentavos : $abonoCentavos;
        $disponible += $reembolso;

        $cuenta->saldo_disponible = $this->accounts->desdeCentavos($disponible);
        $cuenta->saldo_retenido = $this->accounts->desdeCentavos($retenido);
        $cuenta->save();

        Movimiento::query()->create([
            'usuario_id' => $apuesta->usuario_id,
            'tipo' => $tipo,
            'monto' => $this->accounts->desdeCentavos($estado === 'PER' ? $stakeCentavos : $reembolso),
            'saldo_disponible_resultante' => $this->accounts->desdeCentavos($disponible),
            'saldo_retenido_resultante' => $this->accounts->desdeCentavos($retenido),
            'referencia_idempotencia' => 'liquidacion:apuesta:' . $apuesta->id_apuesta,
            'apuesta_id' => $apuesta->id_apuesta,
            'descripcion' => $descripcion,
            'creado_en' => now(),
        ]);

        $apuesta->estado_codigo = $estado;
        $apuesta->liquidada_en = now();
        $apuesta->save();
    }
}
