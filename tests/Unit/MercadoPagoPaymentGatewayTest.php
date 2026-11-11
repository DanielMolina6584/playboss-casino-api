<?php

namespace Tests\Unit;

use App\Service\Payment\MercadoPagoPaymentGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MercadoPagoPaymentGatewayTest extends TestCase
{
    private const SECRET = 'secreto_webhook';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mercadopago' => [
            'access_token' => 'TEST-token', 'webhook_secret' => self::SECRET,
            'base_url' => 'https://api.mercadopago.com', 'redirect_url' => 'https://front.test/billetera/recarga/resultado',
            'notification_url' => 'https://api.test/api/pagos/mercadopago/webhook',
            'use_sandbox_url' => false, 'expiration_minutes' => 60,
        ]]);
    }

    private function firma(string $id, string $requestId, string $secret = self::SECRET): string
    {
        $ts = '1700000000';

        return 'ts=' . $ts . ',v1=' . hash_hmac('sha256', 'id:' . strtolower($id) . ';request-id:' . $requestId . ';ts:' . $ts . ';', $secret);
    }

    private function pago(array $extra = []): array
    {
        return $extra + ['id' => 987654, 'status' => 'approved', 'external_reference' => 'PB-7-ABCD1234', 'transaction_amount' => 15000, 'currency_id' => 'COP'];
    }

    public function test_crear_intento_crea_la_preferencia_y_devuelve_init_point(): void
    {
        Http::fake(['api.mercadopago.com/checkout/preferences' => Http::response(['init_point' => 'https://www.mercadopago.com.co/checkout/v1/redirect?pref_id=1', 'sandbox_init_point' => 'https://sandbox.x'], 201)]);

        $r = (new MercadoPagoPaymentGateway())->crearIntento(['transaction_id' => 7, 'amount' => '15000.50', 'currency' => 'COP'], 'key-12345678');

        $this->assertSame('PENDING', $r['status']);
        $this->assertMatchesRegularExpression('/^PB-7-[A-Z0-9]{8}$/', $r['reference']);
        $this->assertStringContainsString('pref_id=1', $r['checkout_url']);
        Http::assertSent(function (Request $req) use ($r) {
            $b = $req->data();

            return $req->hasHeader('Authorization', 'Bearer TEST-token')
                && $req->hasHeader('X-Idempotency-Key', 'key-12345678')
                && $b['external_reference'] === $r['reference']
                && $b['items'][0]['unit_price'] === 15000.5
                && $b['items'][0]['currency_id'] === 'COP'
                && $b['notification_url'] === 'https://api.test/api/pagos/mercadopago/webhook'
                && $b['auto_return'] === 'approved';
        });
    }

    public function test_evento_firmado_se_confirma_consultando_el_pago(): void
    {
        Http::fake(['api.mercadopago.com/v1/payments/987654' => Http::response($this->pago())]);

        $e = (new MercadoPagoPaymentGateway())->verificarEvento(['data_id' => '987654', 'x_request_id' => 'req-1', 'x_signature' => $this->firma('987654', 'req-1')]);

        $this->assertTrue($e['verified']);
        $this->assertSame('APPROVED', $e['status']);
        $this->assertSame('PB-7-ABCD1234', $e['reference']);
        $this->assertSame('15000.00', $e['amount']);
        $this->assertSame('COP', $e['currency']);
        $this->assertSame('mercadopago:987654:APPROVED', $e['event_id']);
    }

    public function test_firma_invalida_no_consulta_ni_verifica(): void
    {
        Http::fake();

        $e = (new MercadoPagoPaymentGateway())->verificarEvento(['data_id' => '987654', 'x_request_id' => 'req-1', 'x_signature' => $this->firma('987654', 'req-1', 'otro_secreto')]);

        $this->assertFalse($e['verified']);
        Http::assertNothingSent();
    }

    public function test_sin_cabecera_de_firma_se_rechaza_si_hay_secreto(): void
    {
        Http::fake();
        $this->assertFalse((new MercadoPagoPaymentGateway())->verificarEvento(['data_id' => '1'])['verified']);
    }

    public function test_pago_inexistente_no_se_verifica(): void
    {
        Http::fake(['api.mercadopago.com/v1/payments/*' => Http::response(['message' => 'Payment not found'], 404)]);

        $e = (new MercadoPagoPaymentGateway())->verificarEvento(['data_id' => '1', 'x_request_id' => 'r', 'x_signature' => $this->firma('1', 'r')]);

        $this->assertFalse($e['verified']);
    }

    public function test_mapea_estados_de_mercado_pago(): void
    {
        $g = new MercadoPagoPaymentGateway();
        $casos = ['approved' => 'APPROVED', 'rejected' => 'DECLINED', 'cancelled' => 'CANCELLED', 'in_process' => 'PENDING', 'pending' => 'PENDING'];
        $seq = Http::sequence();
        foreach (array_keys($casos) as $mp) {
            $seq->push($this->pago(['id' => 5, 'status' => $mp]));
        }
        Http::fake(['api.mercadopago.com/v1/payments/5' => $seq]);
        foreach ($casos as $esperado) {
            $this->assertSame($esperado, $g->verificarEvento(['data_id' => '5', 'x_request_id' => 'r', 'x_signature' => $this->firma('5', 'r')])['status']);
        }
    }

    public function test_consultar_por_referencia_prefiere_el_pago_aprobado(): void
    {
        Http::fake(['api.mercadopago.com/v1/payments/search*' => Http::response(['results' => [
            $this->pago(['id' => 2, 'status' => 'rejected']), $this->pago(['id' => 1, 'status' => 'approved']),
        ]])]);

        $e = (new MercadoPagoPaymentGateway())->consultarPorReferencia('PB-7-ABCD1234');

        $this->assertSame('APPROVED', $e['status']);
        $this->assertSame('mercadopago:1:APPROVED', $e['event_id']);
    }

    public function test_consultar_por_referencia_sin_resultados_devuelve_null(): void
    {
        Http::fake(['api.mercadopago.com/v1/payments/search*' => Http::response(['results' => []])]);
        $this->assertNull((new MercadoPagoPaymentGateway())->consultarPorReferencia('PB-0'));
    }
}
