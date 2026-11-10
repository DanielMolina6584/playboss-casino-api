<?php

namespace App\Service\Auth\Mfa;

/** TOTP RFC 6238 / HOTP RFC 4226 (HMAC-SHA1, sin dependencias externas). */
class Totp
{
    private const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generarSecreto(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function base32Encode(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $trozo) {
            $out .= self::ALFABETO[bindec(str_pad($trozo, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(rtrim(str_replace([' ', '-'], '', $b32), '='));
        $bits = '';
        foreach (str_split($b32) as $c) {
            $v = strpos(self::ALFABETO, $c);
            if ($v === false) {
                throw new \InvalidArgumentException('Base32 inválido.');
            }
            $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }

    public static function hotp(string $secretoBase32, int $contador, int $digitos = 6): string
    {
        $h = hash_hmac('sha1', pack('J', $contador), self::base32Decode($secretoBase32), true);
        $o = ord($h[19]) & 0x0F;
        $n = (unpack('N', substr($h, $o, 4))[1] & 0x7FFFFFFF) % (10 ** $digitos);

        return str_pad((string) $n, $digitos, '0', STR_PAD_LEFT);
    }

    public static function contador(int $timestamp, int $periodo = 30): int
    {
        return intdiv($timestamp, $periodo);
    }

    public static function codigo(string $secretoBase32, int $timestamp, int $periodo = 30, int $digitos = 6): string
    {
        return self::hotp($secretoBase32, self::contador($timestamp, $periodo), $digitos);
    }

    /**
     * Devuelve el contador (ventana) que coincide o null. Acepta ±$ventana y rechaza contadores
     * <= $ultimoContador (un código usado, o más viejo que el último usado, no vuelve a valer).
     */
    public static function verificar(string $secretoBase32, string $codigo, int $timestamp, ?int $ultimoContador = null, int $ventana = 1, int $periodo = 30, int $digitos = 6): ?int
    {
        if (!preg_match('/^\d{' . $digitos . '}$/', $codigo)) {
            return null;
        }
        $actual = self::contador($timestamp, $periodo);
        $acierto = null;
        for ($i = -$ventana; $i <= $ventana; $i++) {
            // Se evalúan todas las ventanas (sin cortocircuito) para no filtrar cuál acertó por tiempo.
            if (hash_equals(self::hotp($secretoBase32, $actual + $i, $digitos), $codigo) && ($ultimoContador === null || $actual + $i > $ultimoContador)) {
                $acierto = $actual + $i;
            }
        }

        return $acierto;
    }

    public static function uri(string $secretoBase32, string $cuenta, string $emisor, int $digitos = 6, int $periodo = 30): string
    {
        return 'otpauth://totp/' . rawurlencode($emisor . ':' . $cuenta)
            . '?secret=' . $secretoBase32 . '&issuer=' . rawurlencode($emisor)
            . '&algorithm=SHA1&digits=' . $digitos . '&period=' . $periodo;
    }
}
