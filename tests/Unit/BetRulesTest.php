<?php

namespace Tests\Unit;

use App\Service\Bet\BetRules;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BetRulesTest extends TestCase
{
    private function sel(int $match, string $market = 'a', string $code = '1'): array
    {
        return ['match_id' => $match, 'market_id' => $market, 'selection_code' => $code];
    }

    public function test_acepta_simple_y_combinada_valida(): void
    {
        BetRules::validarSelecciones([$this->sel(1)], 10);
        BetRules::validarSelecciones([$this->sel(1), $this->sel(2), $this->sel(3, 'b', 'X')], 10);
        $this->assertSame('SIMPLE', BetRules::tipo([$this->sel(1)]));
        $this->assertSame('COMBINADA', BetRules::tipo([$this->sel(1), $this->sel(2)]));
        $this->addToAssertionCount(2);
    }

    public function test_rechaza_vacia(): void
    {
        $this->expectException(ValidationException::class);
        BetRules::validarSelecciones([], 10);
    }

    public function test_rechaza_exceso_de_selecciones(): void
    {
        $this->expectException(ValidationException::class);
        BetRules::validarSelecciones([$this->sel(1), $this->sel(2), $this->sel(3)], 2);
    }

    public function test_rechaza_duplicadas(): void
    {
        $this->expectException(ValidationException::class);
        BetRules::validarSelecciones([$this->sel(1), $this->sel(1)], 10);
    }

    public function test_rechaza_selecciones_excluyentes_del_mismo_partido(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('excluyentes');
        BetRules::validarSelecciones([$this->sel(1, 'a', '1'), $this->sel(1, 'a', '2')], 10);
    }
}
