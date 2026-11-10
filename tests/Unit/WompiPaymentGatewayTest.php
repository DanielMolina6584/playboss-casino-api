<?php

namespace Tests\Unit;

use App\Service\Payment\WompiPaymentGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WompiPaymentGatewayTest extends TestCase
{
    private const EVENTS = 'test_events_secret';
    private const INTEGRITY = 'test_integrity_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.wompi' => [
            'public_key' => 'pub_test_x', 'private_key' => 'prv_test_x',
            'events_secret' => self::EVENTS, 'integrity_secret' => self::INTEGRITY,
            'base_url' => 'https://sandbox.wompi.co/v1', 'redirect_url' => 'http://localhost:5173/pagos/resultado',
            'checkout_url' => 'https://checkout.wompi.co/p/',
        ]]);
    }

    private function evento(array $tx = [], ?string $secret = null): array
    {
        $tx = $tx + ['id' => '1234-1610641025-49201', 'status' => 'APPROVED', 'amount_in_cents' => 4490000, 'reference' => 'PB-7-ABCD1234', 'currency' => 'COP'];
        $ts = 1530291411;
        $checksum = hash('sha256', $tx['id'] . $tx['status'] . $tx['amount_in_cents'] . $ts . ($secret ?? self::EVENTS));

        return [
            'event' => 'transaction.updated',
            'data' => ['transaction' => $tx],
            'signature' => ['properties' => ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'], 'checksum' => $checksum],
            'timestamp' => $ts,
            'sent_at' => '2026-10-30T12:00:00.000Z',
        ];
    }


    public function test_firma_de_integridad_es_sha256_de_referencia_monto_moneda_secreto(): void
    {
        $g = new WompiPaymentGateway();
        $this->assertSame(
            hash('sha256', 'PB-7-ABCD1234' . 1500000 . 'COP' . self::INTEGRITY),
            $g->firmaIntegridad('PB-7-ABCD1234', 1500000, 'COP')
        );
    }

    public function test_firma_con_expiration_time_lo_intercala_antes_del_secreto(): void
    {
        $g = new WompiPaymentGateway();
        $this->assertSame(
            hash('sha256', 'R1' . 100 . 'COP' . '2030-01-01T00:00:00.000Z' . self::INTEGRITY),
            $g->firmaIntegridad('R1', 100, 'COP', '2030-01-01T00:00:00.000Z')
        );
    }

    public function test_monto_a_centavos_es_exacto_y_rechaza_invalidos(): void
    {
        $g = new WompiPaymentGateway();
        foreach (['0.29' => '29', '19.99' => '1999', '15000' => '1500000', '15000.5' => '1500050'] as $monto => $esperado) {
            $r = $g->crearIntento(['transaction_id' => 1, 'amount' => (string) $monto, 'currency' => 'COP'], 'k');
            parse_str(parse_url($r['checkout_url'], PHP_URL_QUERY), $q);
            $this->assertSame($esperado, $q['amount-in-cents']);
        }
        $this->expectException(\RuntimeException::class);
        $g->crearIntento(['transaction_id' => 1, 'amount' => '1.234', 'currency' => 'COP'], 'k');
    }

    public function test_crear_intento_devuelve_referencia_y_url_de_checkout_con_firma(): void
    {
        $g = new WompiPaymentGateway();
        $r = $g->crearIntento(['transaction_id' => 7, 'user_id' => 1, 'amount' => '15000.00', 'currency' => 'COP'], 'key-12345678');

        $this->assertSame('PENDING', $r['status']);
        $this->assertMatchesRegularExpression('/^PB-7-[A-Z0-9]{8}$/', $r['reference']);
        parse_str(parse_url($r['checkout_url'], PHP_URL_QUERY), $q);
        $this->assertSame('pub_test_x', $q['public-key']);
        $this->assertSame('1500000', $q['amount-in-cents']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.000Z$/', $q['expiration-time']);
        $this->assertSame($g->firmaIntegridad($r['reference'], 1500000, 'COP', $q['expiration-time']), $q['signature:integrity']);

        $d = $g->datosCheckout($r['reference'], 1500000, 'COP');
        $this->assertSame('COP', $d['currency']);
        $this->assertArrayNotHasKey('integrity_secret', $d);
    }

    public function test_checksum_valido_e_invalido(): void
    {
        $g = new WompiPaymentGateway();
        $this->assertTrue($g->checksumValido($this->evento()));
        $this->assertFalse($g->checksumValido($this->evento([], 'otro_secreto')));

        $manipulado = $this->evento();
        $manipulado['data']['transaction']['amount_in_cents'] = 100;
        $this->assertFalse($g->checksumValido($manipulado));
    }

    public function test_checksum_acepta_timestamp_legado_en_signature_y_rechaza_si_falta_o_difiere(): void
    {
        $g = new WompiPaymentGateway();
        $legado = $this->evento();
        $legado['signature']['timestamp'] = $legado['timestamp'];
        unset($legado['timestamp']);
        $this->assertTrue($g->checksumValido($legado));

        $sin = $this->evento();
        unset($sin['timestamp']);
        $this->assertFalse($g->checksumValido($sin));

        $otro = $this->evento();
        $otro['timestamp'] = 1;
        $this->assertFalse($g->checksumValido($otro));
    }

    public function test_verificar_evento_consulta_a_wompi_y_normaliza(): void
    {
        Http::fake(['sandbox.wompi.co/v1/transactions/*' => Http::response(['data' => [
            'id' => '1234-1610641025-49201', 'status' => 'APPROVED', 'amount_in_cents' => 4490050, 'currency' => 'COP', 'reference' => 'PB-7-ABCD1234',
        ]])]);

        $r = (new WompiPaymentGateway())->verificarEvento($this->evento());

        $this->assertTrue($r['verified']);
        $this->assertSame('44900.50', $r['amount']);
        $this->assertSame('COP', $r['currency']);
        $this->assertSame('APPROVED', $r['status']);
        $this->assertSame('PB-7-ABCD1234', $r['reference']);
        $this->assertSame('wompi:1234-1610641025-49201:APPROVED', $r['event_id']);
        Http::assertSentCount(1);
    }

    public function test_evento_con_firma_falsa_no_llama_a_wompi_y_no_se_verifica(): void
    {
        Http::fake();
        $r = (new WompiPaymentGateway())->verificarEvento($this->evento([], 'falso'));

        $this->assertFalse($r['verified']);
        Http::assertNothingSent();
    }

    public function test_se_rechaza_si_la_referencia_remota_no_coincide(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => '1234-1610641025-49201', 'status' => 'APPROVED', 'amount_in_cents' => 4490000, 'currency' => 'COP', 'reference' => 'OTRA']])]);

        $this->assertFalse((new WompiPaymentGateway())->verificarEvento($this->evento())['verified']);
    }

    public function test_estados_se_mapean(): void
    {
        $mapa = ["VOIDED" => "CANCELLED", "ERROR" => "FAILED", "DECLINED" => "DECLINED", "PENDING" => "PENDING"];
        $seq = Http::sequence();
        foreach (array_keys($mapa) as $wompi) {
            $seq->push(["data" => [["id" => "T", "status" => $wompi, "amount_in_cents" => 100, "currency" => "COP", "reference" => "R", "created_at" => "2026-01-01"]]]);
        }
        Http::fake(["*" => $seq]);
        foreach ($mapa as $interno) {
            $this->assertSame($interno, (new WompiPaymentGateway())->consultarPorReferencia("R")["status"]);
        }
    }
}
