<?php

namespace Tests\Unit;

use App\Service\Bet\BetMath;
use PHPUnit\Framework\TestCase;

class BetMathTest extends TestCase
{
    public function test_cuota_total_es_el_producto_de_las_cuotas(): void
    {
        $this->assertSame(2.0, BetMath::cuotaTotal([2.0]));
        $this->assertSame(6.0, BetMath::cuotaTotal([2.0, 3.0]));
        $this->assertSame(4.2524, BetMath::cuotaTotal([1.5, 1.85, 1.5324]));
    }

    public function test_pago_potencial_incluye_el_monto_y_se_calcula_en_centavos(): void
    {
        $this->assertSame(250000, BetMath::pagoPotencialCentavos(100000, 2.5));      // 1000.00 x 2.5 = 2500.00
        $this->assertSame(1067, BetMath::pagoPotencialCentavos(1000, 1.0666));       // 10.00 x 1.0666 = 10.67
        $this->assertSame(3333, BetMath::pagoPotencialCentavos(1111, 3.0));
    }
}
