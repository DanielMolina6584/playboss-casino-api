<?php

namespace App\Service\Demo;

use App\Service\Bet\MarketResolver;

/**
 * Modelo de Poisson de un partido: calcula las probabilidades de cada mercado,
 * las convierte en cuotas con margen de casa y simula el resultado real.
 * Los goles por tiempo son independientes (44% del total esperado en el primero).
 */
class ModeloPartido
{
    private const MAX_GOLES = 8;
    private const FRACCION_PRIMER_TIEMPO = 0.44;
    private const MARGEN = 0.06;
    private const MARGEN_ESPECIAL = 0.12;

    /** @var array<int,array<int,float>> probabilidad del marcador final [local][visitante] */
    private array $marcador = [];

    public function __construct(
        public readonly float $golesLocal,
        public readonly float $golesVisitante,
        public readonly float $cornersLocal,
        public readonly float $cornersVisitante,
        public readonly float $tarjetas,
        public readonly float $tirosLocal,
        public readonly float $tirosVisitante,
    ) {
        for ($h = 0; $h <= self::MAX_GOLES; $h++) {
            for ($a = 0; $a <= self::MAX_GOLES; $a++) {
                $this->marcador[$h][$a] = self::poisson($golesLocal, $h) * self::poisson($golesVisitante, $a);
            }
        }
    }

    /**
     * Crea el modelo a partir de la fuerza de los dos equipos.
     * @param array{ataque:float,defensa:float} $local
     * @param array{ataque:float,defensa:float} $visitante
     */
    public static function entre(array $local, array $visitante): self
    {
        $gl = 1.35 * $local['ataque'] * $visitante['defensa'];
        $gv = 1.05 * $visitante['ataque'] * $local['defensa'];
        $dominio = $gl / ($gl + $gv);

        return new self($gl, $gv, 9.6 * $dominio + 0.6, 9.6 * (1 - $dominio) + 0.4, 4.6, 24 * $dominio + 1.5, 24 * (1 - $dominio) + 1.0);
    }

    /**
     * Probabilidades por opción de un mercado (suman 1 salvo DC, que reparte 2).
     * @return array<string,float>
     */
    public function probabilidades(string $code): array
    {
        return match (true) {
            $code === 'HT_1X2' => $this->porMitades(fn (int $h1, int $a1, int $h, int $a): string => MarketResolver::ganadora('1X2', $h1, $a1)),
            $code === 'HTFT' => $this->porMitades(fn (int $h1, int $a1, int $h, int $a): string => MarketResolver::ganadora('1X2', $h1, $a1) . '/' . MarketResolver::ganadora('1X2', $h, $a)),
            $code === 'CORNERS_1X2' => $this->comparacion($this->cornersLocal, $this->cornersVisitante),
            (bool) preg_match('/^(CORNERS|CARDS|SHOTS|SOT)_OU_(\d+)_(\d)$/', $code, $m) => $this->masMenos($this->esperado($m[1]), (float) ($m[2] . '.' . $m[3])),
            default => $this->porMarcador($code),
        };
    }

    /**
     * Cuotas decimales por opción, con el margen de la casa incluido.
     * @param list<string> $opciones
     * @return array<string,float>
     */
    public function cuotas(string $code, array $opciones, bool $especial = false): array
    {
        $probabilidades = $this->probabilidades($code);
        $margen = 1 + ($especial ? self::MARGEN_ESPECIAL : self::MARGEN);
        $cuotas = [];
        foreach ($opciones as $opcion) {
            $p = max($probabilidades[$opcion] ?? 0.0, 0.004);
            $cuotas[$opcion] = round(min(max(1 / ($p * $margen), 1.02), 250), 2);
        }

        return $cuotas;
    }

    /**
     * Simula el partido: marcador, descanso, córners, tarjetas y tiros.
     * @return array{goles_local:int,goles_visitante:int,estadisticas:array<string,int>}
     */
    public function simular(): array
    {
        $htLocal = self::muestraPoisson($this->golesLocal * self::FRACCION_PRIMER_TIEMPO);
        $htVisitante = self::muestraPoisson($this->golesVisitante * self::FRACCION_PRIMER_TIEMPO);
        $golesLocal = $htLocal + self::muestraPoisson($this->golesLocal * (1 - self::FRACCION_PRIMER_TIEMPO));
        $golesVisitante = $htVisitante + self::muestraPoisson($this->golesVisitante * (1 - self::FRACCION_PRIMER_TIEMPO));
        $tirosLocal = max($golesLocal, self::muestraPoisson($this->tirosLocal));
        $tirosVisitante = max($golesVisitante, self::muestraPoisson($this->tirosVisitante));
        $tarjetasTotal = self::muestraPoisson($this->tarjetas);
        $tarjetasLocal = self::muestraBinomial($tarjetasTotal, 0.47);

        return [
            'goles_local' => $golesLocal,
            'goles_visitante' => $golesVisitante,
            'estadisticas' => [
                'ht_local' => $htLocal,
                'ht_visitante' => $htVisitante,
                'corners_local' => self::muestraPoisson($this->cornersLocal),
                'corners_visitante' => self::muestraPoisson($this->cornersVisitante),
                'tarjetas_local' => $tarjetasLocal,
                'tarjetas_visitante' => $tarjetasTotal - $tarjetasLocal,
                'tiros_local' => $tirosLocal,
                'tiros_visitante' => $tirosVisitante,
                'tiros_puerta_local' => max($golesLocal, self::muestraBinomial($tirosLocal, 0.36)),
                'tiros_puerta_visitante' => max($golesVisitante, self::muestraBinomial($tirosVisitante, 0.36)),
            ],
        ];
    }

    /**
     * Probabilidades de mercados que dependen solo del marcador final.
     * @return array<string,float>
     */
    private function porMarcador(string $code): array
    {
        $resultado = [];
        foreach ($this->marcador as $h => $fila) {
            foreach ($fila as $a => $p) {
                foreach (MarketResolver::ganadoras($code, $h, $a) ?? [] as $opcion) {
                    $resultado[$opcion] = ($resultado[$opcion] ?? 0) + $p;
                }
            }
        }

        // Empate no válido: la cuota se calcula sin el empate.
        if ($code === 'DNB') {
            $total = ($resultado['1'] ?? 0) + ($resultado['2'] ?? 0);
            return ['1' => ($resultado['1'] ?? 0) / $total, '2' => ($resultado['2'] ?? 0) / $total];
        }

        return $resultado;
    }

    /**
     * Probabilidades de mercados que dependen del marcador al descanso y final.
     * @return array<string,float>
     */
    private function porMitades(callable $opcion): array
    {
        $max = 5;
        $resultado = [];
        $pt = self::FRACCION_PRIMER_TIEMPO;
        for ($h1 = 0; $h1 <= $max; $h1++) {
            for ($a1 = 0; $a1 <= $max; $a1++) {
                $p1 = self::poisson($this->golesLocal * $pt, $h1) * self::poisson($this->golesVisitante * $pt, $a1);
                for ($h2 = 0; $h2 <= $max; $h2++) {
                    for ($a2 = 0; $a2 <= $max; $a2++) {
                        $p = $p1 * self::poisson($this->golesLocal * (1 - $pt), $h2) * self::poisson($this->golesVisitante * (1 - $pt), $a2);
                        $clave = $opcion($h1, $a1, $h1 + $h2, $a1 + $a2);
                        $resultado[$clave] = ($resultado[$clave] ?? 0) + $p;
                    }
                }
            }
        }

        return $resultado;
    }

    /**
     * Probabilidad de que el local, el visitante o ninguno tenga más de una estadística.
     * @return array<string,float>
     */
    private function comparacion(float $local, float $visitante): array
    {
        $resultado = ['1' => 0.0, 'X' => 0.0, '2' => 0.0];
        for ($i = 0; $i <= 30; $i++) {
            for ($j = 0; $j <= 30; $j++) {
                $resultado[$i > $j ? '1' : ($i < $j ? '2' : 'X')] += self::poisson($local, $i) * self::poisson($visitante, $j);
            }
        }

        return $resultado;
    }

    /**
     * Probabilidad de más/menos de una línea .5 para un total con distribución de Poisson.
     * @return array{OVER:float,UNDER:float}
     */
    private function masMenos(float $esperado, float $linea): array
    {
        $menos = 0.0;
        for ($k = 0; $k <= (int) floor($linea); $k++) {
            $menos += self::poisson($esperado, $k);
        }

        return ['OVER' => 1 - $menos, 'UNDER' => $menos];
    }

    /**
     * Valor esperado del total de una estadística.
     */
    private function esperado(string $estadistica): float
    {
        return match ($estadistica) {
            'CORNERS' => $this->cornersLocal + $this->cornersVisitante,
            'CARDS' => $this->tarjetas,
            'SHOTS' => $this->tirosLocal + $this->tirosVisitante,
            default => ($this->tirosLocal + $this->tirosVisitante) * 0.36,
        };
    }

    private static function poisson(float $lambda, int $k): float
    {
        return exp(-$lambda + $k * log($lambda) - self::logFactorial($k));
    }

    private static function logFactorial(int $n): float
    {
        $suma = 0.0;
        for ($i = 2; $i <= $n; $i++) {
            $suma += log($i);
        }

        return $suma;
    }

    /**
     * Muestra de una Poisson (algoritmo de Knuth) usando mt_rand para poder fijar la semilla.
     */
    private static function muestraPoisson(float $lambda): int
    {
        $limite = exp(-$lambda);
        $k = 0;
        $p = 1.0;
        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $limite);

        return $k - 1;
    }

    private static function muestraBinomial(int $n, float $p): int
    {
        $exitos = 0;
        for ($i = 0; $i < $n; $i++) {
            $exitos += (mt_rand() / mt_getrandmax()) < $p ? 1 : 0;
        }

        return $exitos;
    }
}
