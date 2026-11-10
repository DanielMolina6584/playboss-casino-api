<?php

namespace App\Service\Account;

use App\Models\Account\LimiteJuego;
use App\Models\Bet\Apuesta;
use App\Models\Payment\Transaccion;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Juego responsable: límites de depósito/apuesta por periodo calendario (día, semana, mes)
 * y autoexclusión temporal. Vive en PostgreSQL junto al dinero que limita.
 */
class LimiteJuegoService
{
    public const PERIODOS = ['diario', 'semanal', 'mensual'];

    public function __construct(private readonly AccountService $accounts)
    {
    }

    public function obtener(int $usuarioId): LimiteJuego
    {
        return LimiteJuego::query()->find($usuarioId) ?? new LimiteJuego(['usuario_id' => $usuarioId]);
    }

    /**
     * Actualiza solo las claves presentes en $cambios (null = sin límite).
     * La autoexclusión activa no se puede acortar ni retirar.
     *
     * @param array<string,?string> $cambios límites en pesos; `autoexclusion_dias` (int) inicia o amplía la exclusión
     */
    public function actualizar(int $usuarioId, array $cambios): LimiteJuego
    {
        $actual = $this->obtener($usuarioId);
        $valores = [];
        foreach (LimiteJuego::CAMPOS as $campo) {
            $valores[$campo] = array_key_exists($campo, $cambios)
                ? ($cambios[$campo] === null ? null : $this->accounts->desdeCentavos($this->accounts->aCentavos((string) $cambios[$campo])))
                : ($actual->{$campo} === null ? null : (string) $actual->{$campo});
        }
        $this->validarEscalera($valores, 'deposito');
        $this->validarEscalera($valores, 'apuesta');

        $hasta = $actual->autoexcluido_hasta;
        if (!empty($cambios['autoexclusion_dias'])) {
            $nueva = now()->addDays((int) $cambios['autoexclusion_dias']);
            if ($this->estaExcluido($actual) && $nueva->lt($hasta)) {
                throw ValidationException::withMessages(['autoexclusion_dias' => 'Ya estás autoexcluido hasta ' . $hasta->toDateString() . '; no se puede acortar.']);
            }
            $hasta = $nueva;
        }

        return LimiteJuego::query()->updateOrCreate(['usuario_id' => $usuarioId], $valores + [
            'autoexcluido_hasta' => $hasta,
            'actualizado_en' => now(),
        ]);
    }

    public function estaExcluido(LimiteJuego $limite): bool
    {
        return $limite->autoexcluido_hasta !== null && $limite->autoexcluido_hasta->isFuture();
    }

    public function exigirNoExcluido(int $usuarioId): void
    {
        $limite = $this->obtener($usuarioId);
        if ($this->estaExcluido($limite)) {
            throw ValidationException::withMessages(['usuario' => 'Estás autoexcluido hasta ' . $limite->autoexcluido_hasta->toDateString() . '; no puedes apostar ni depositar.']);
        }
    }

    /** Debe llamarse con la cuenta bloqueada para que dos apuestas simultáneas no superen el tope. */
    public function validarApuesta(int $usuarioId, int $stakeCentavos): void
    {
        $limite = $this->obtener($usuarioId);
        $this->exigirNoExcluido($usuarioId);
        $this->validarPeriodos($limite, 'apuesta', $stakeCentavos, fn (CarbonInterface $desde) => $this->apostado($usuarioId, $desde), 'apuestas');
    }

    public function validarDeposito(int $usuarioId, int $centavos): void
    {
        $limite = $this->obtener($usuarioId);
        $this->exigirNoExcluido($usuarioId);
        $this->validarPeriodos($limite, 'deposito', $centavos, fn (CarbonInterface $desde) => $this->depositado($usuarioId, $desde), 'depósitos');
    }

    /** @return array<string,array{limite:?string,usado:string,disponible:?string}> consumo actual por clave de límite */
    public function consumo(int $usuarioId): array
    {
        $limite = $this->obtener($usuarioId);
        $resultado = [];
        foreach (['apuesta' => fn ($d) => $this->apostado($usuarioId, $d), 'deposito' => fn ($d) => $this->depositado($usuarioId, $d)] as $tipo => $usado) {
            foreach (self::PERIODOS as $periodo) {
                $tope = $limite->{"{$tipo}_{$periodo}"};
                $gastado = $usado($this->inicio($periodo));
                $resultado["{$tipo}_{$periodo}"] = [
                    'limite' => $tope === null ? null : (string) $tope,
                    'usado' => $this->accounts->desdeCentavos($gastado),
                    'disponible' => $tope === null ? null : $this->accounts->desdeCentavos(max(0, $this->accounts->aCentavos((string) $tope) - $gastado)),
                ];
            }
        }

        return $resultado;
    }

    private function validarPeriodos(LimiteJuego $limite, string $tipo, int $nuevo, callable $usado, string $plural): void
    {
        foreach (self::PERIODOS as $periodo) {
            $tope = $limite->{"{$tipo}_{$periodo}"};
            if ($tope === null) {
                continue;
            }
            if ($usado($this->inicio($periodo)) + $nuevo > $this->accounts->aCentavos((string) $tope)) {
                throw ValidationException::withMessages(['monto' => "Superas tu límite {$periodo} de {$plural} ({$tope})."]);
            }
        }
    }

    private function inicio(string $periodo): CarbonInterface
    {
        return match ($periodo) {
            'diario' => now()->startOfDay(),
            'semanal' => now()->startOfWeek(),
            default => now()->startOfMonth(),
        };
    }

    private function apostado(int $usuarioId, CarbonInterface $desde): int
    {
        $total = Apuesta::query()->where('usuario_id', $usuarioId)->where('creada_en', '>=', $desde)
            ->where('estado_codigo', '!=', 'ANU')->sum('monto');

        return $this->accounts->aCentavos(number_format((float) $total, 2, '.', ''));
    }

    /** Depósitos aprobados más los pendientes de la última hora (checkout en curso). */
    private function depositado(int $usuarioId, CarbonInterface $desde): int
    {
        $total = Transaccion::query()->where('usuario_id', $usuarioId)->where('tipo', 'DEPOSITO')->where('fecha_hora', '>=', $desde)
            ->where(fn ($q) => $q->where('estado', 'APROBADA')->orWhere(fn ($p) => $p->where('estado', 'PENDIENTE')->where('fecha_hora', '>=', now()->subHour())))
            ->sum('monto');

        return $this->accounts->aCentavos(number_format((float) $total, 2, '.', ''));
    }

    /** diario <= semanal <= mensual cuando ambos existen. */
    private function validarEscalera(array $valores, string $tipo): void
    {
        $previo = null;
        foreach (self::PERIODOS as $periodo) {
            $v = $valores["{$tipo}_{$periodo}"];
            if ($v === null) {
                continue;
            }
            if ($previo !== null && $this->accounts->aCentavos($v) < $this->accounts->aCentavos($previo[1])) {
                throw ValidationException::withMessages(["{$tipo}_{$periodo}" => "El límite {$periodo} no puede ser menor que el {$previo[0]}."]);
            }
            $previo = [$periodo, $v];
        }
    }
}
