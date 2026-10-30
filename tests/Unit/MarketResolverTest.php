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
}
