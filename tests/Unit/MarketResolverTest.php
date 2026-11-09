<?php

namespace Tests\Unit;

use App\Service\Bet\MarketResolver;
use PHPUnit\Framework\TestCase;

class MarketResolverTest extends TestCase
{
    public function test_1x2(): void
    {
        $this->assertSame('1', MarketResolver::ganadora('1X2', 2, 1));
        $this->assertSame('X', MarketResolver::ganadora('1X2', 1, 1));
        $this->assertSame('2', MarketResolver::ganadora('1X2', 0, 3));
    }

    public function test_over_under_y_btts(): void
    {
        $this->assertSame('OVER', MarketResolver::ganadora('OU_2_5', 2, 1));
        $this->assertSame('UNDER', MarketResolver::ganadora('OU_2_5', 1, 1));
        $this->assertNull(MarketResolver::ganadora('OU_2_0', 1, 1));
        $this->assertSame('YES', MarketResolver::ganadora('BTTS', 1, 1));
        $this->assertSame('NO', MarketResolver::ganadora('BTTS', 2, 0));
        $this->assertNull(MarketResolver::ganadora('CORNERS', 2, 0));
    }

    public function test_doble_oportunidad_tiene_dos_ganadoras(): void
    {
        $this->assertSame(['1X', '12'], MarketResolver::ganadoras('DC', 2, 0));
        $this->assertSame(['1X', 'X2'], MarketResolver::ganadoras('DC', 1, 1));
        $this->assertSame(['X2', '12'], MarketResolver::ganadoras('DC', 0, 1));
        $this->assertNull(MarketResolver::ganadora('DC', 2, 0));
    }

    public function test_empate_no_valido_anula_en_empate(): void
    {
        $this->assertSame('1', MarketResolver::ganadora('DNB', 1, 0));
        $this->assertSame(MarketResolver::VOID, MarketResolver::ganadora('DNB', 2, 2));
    }

    public function test_mercados_por_marcador(): void
    {
        $this->assertSame('ODD', MarketResolver::ganadora('ODD_EVEN', 2, 1));
        $this->assertSame('EVEN', MarketResolver::ganadora('ODD_EVEN', 0, 0));
        $this->assertSame('2-1', MarketResolver::ganadora('CS', 2, 1));
        $this->assertSame('OTHER', MarketResolver::ganadora('CS', 4, 1));
        $this->assertSame('X', MarketResolver::ganadora('HCP_M1', 2, 1));
        $this->assertSame('1', MarketResolver::ganadora('HCP_P1', 0, 0));
        $this->assertSame('1_YES', MarketResolver::ganadora('RES_BTTS', 2, 1));
        $this->assertSame('X_NO', MarketResolver::ganadora('RES_BTTS', 0, 0));
        $this->assertSame('OVER', MarketResolver::ganadora('HOME_OU_1_5', 2, 0));
        $this->assertSame('UNDER', MarketResolver::ganadora('AWAY_OU_0_5', 2, 0));
    }

    public function test_mercados_por_estadisticas(): void
    {
        $stats = [
            'ht_local' => 0, 'ht_visitante' => 1, 'corners_local' => 7, 'corners_visitante' => 4,
            'tarjetas_local' => 3, 'tarjetas_visitante' => 2, 'tiros_local' => 15, 'tiros_visitante' => 8,
            'tiros_puerta_local' => 6, 'tiros_puerta_visitante' => 2,
        ];

        $this->assertSame('2', MarketResolver::ganadora('HT_1X2', 2, 1, $stats));
        $this->assertSame('2/1', MarketResolver::ganadora('HTFT', 2, 1, $stats));
        $this->assertSame('1', MarketResolver::ganadora('CORNERS_1X2', 2, 1, $stats));
        $this->assertSame('OVER', MarketResolver::ganadora('CORNERS_OU_10_5', 2, 1, $stats));
        $this->assertSame('OVER', MarketResolver::ganadora('CARDS_OU_4_5', 2, 1, $stats));
        $this->assertSame('OVER', MarketResolver::ganadora('SHOTS_OU_21_5', 2, 1, $stats));
        $this->assertSame('UNDER', MarketResolver::ganadora('SOT_OU_8_5', 2, 1, $stats));
    }

    public function test_mercados_por_estadisticas_sin_datos_quedan_pendientes(): void
    {
        $this->assertNull(MarketResolver::ganadoras('CORNERS_OU_9_5', 2, 1));
        $this->assertNull(MarketResolver::ganadoras('HTFT', 2, 1));
    }
}
