<?php

namespace Tests\Unit;

use App\Service\Auth\Mfa\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    private const SECRETO_RFC = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // base32("12345678901234567890")

    public function test_vectores_rfc_6238_sha1(): void
    {
        foreach ([59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'] as $t => $esperado) {
            $this->assertSame($esperado, Totp::codigo(self::SECRETO_RFC, $t, 30, 8), "t=$t");
        }
        $this->assertSame('287082', Totp::codigo(self::SECRETO_RFC, 59)); // 6 dígitos
    }

    public function test_base32_ida_y_vuelta_y_secreto_de_160_bits(): void
    {
        $this->assertSame('12345678901234567890', Totp::base32Decode(self::SECRETO_RFC));
        $s = Totp::generarSecreto();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $s);
        $this->assertSame($s, Totp::base32Encode(Totp::base32Decode($s)));
        $this->assertNotSame($s, Totp::generarSecreto());
    }

    public function test_ventana_mas_menos_uno_y_no_mas(): void
    {
        $t = 1_800_000_000;
        $c = Totp::contador($t);
        $this->assertSame($c, Totp::verificar(self::SECRETO_RFC, Totp::codigo(self::SECRETO_RFC, $t), $t));
        $this->assertSame($c - 1, Totp::verificar(self::SECRETO_RFC, Totp::codigo(self::SECRETO_RFC, $t - 30), $t));
        $this->assertSame($c + 1, Totp::verificar(self::SECRETO_RFC, Totp::codigo(self::SECRETO_RFC, $t + 30), $t));
        $this->assertNull(Totp::verificar(self::SECRETO_RFC, Totp::codigo(self::SECRETO_RFC, $t - 60), $t));
        $this->assertNull(Totp::verificar(self::SECRETO_RFC, Totp::codigo(self::SECRETO_RFC, $t + 60), $t));
    }

    public function test_no_acepta_contador_ya_usado_ni_anterior(): void
    {
        $t = 1_800_000_000;
        $c = Totp::contador($t);
        $this->assertNull(Totp::verificar(self::SECRETO_RFC, Totp::codigo(self::SECRETO_RFC, $t), $t, $c));
        $this->assertNull(Totp::verificar(self::SECRETO_RFC, Totp::codigo(self::SECRETO_RFC, $t - 30), $t, $c));
        $this->assertSame($c + 1, Totp::verificar(self::SECRETO_RFC, Totp::codigo(self::SECRETO_RFC, $t + 30), $t, $c));
    }

    public function test_formato_invalido_se_rechaza(): void
    {
        foreach (['', '12345', '1234567', 'abcdef', '12 456'] as $x) {
            $this->assertNull(Totp::verificar(self::SECRETO_RFC, $x, 59));
        }
    }

    public function test_uri_otpauth(): void
    {
        $u = Totp::uri('ABC234', 'ana@example.com', 'PlayBoss');
        $this->assertSame('otpauth://totp/PlayBoss%3Aana%40example.com?secret=ABC234&issuer=PlayBoss&algorithm=SHA1&digits=6&period=30', $u);
    }
}
