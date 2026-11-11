<?php

namespace App\Service\Payment;

use App\Contracts\ConsultableGateway;
use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Mercado Pago (Checkout Pro). Se usa con las credenciales y el usuario comprador de prueba.
 * Docs: https://www.mercadopago.com.co/developers/es/docs/checkout-pro/overview
 *
 * El webhook nunca se cree por su contenido: solo aporta el id del pago; el estado, el monto y la
 * referencia salen de GET /v1/payments/{id} con el Access Token. La firma x-signature se valida
 * además cuando MERCADOPAGO_WEBHOOK_SECRET está configurado.
 */
class MercadoPagoPaymentGateway implements PaymentGateway, ConsultableGateway
{
    private const STATUS_MAP = [
        'approved' => 'APPROVED',
        'rejected' => 'DECLINED',
        'cancelled' => 'CANCELLED',
        'pending' => 'PENDING',
        'in_process' => 'PENDING',
        'in_mediation' => 'PENDING',
        'authorized' => 'PENDING',
    ];

    public function crearIntento(array $payload, string $claveIdempotencia): array
    {
        $reference = 'PB-' . $payload['transaction_id'] . '-' . strtoupper(Str::random(8));
        $minutos = (int) config('services.mercadopago.expiration_minutes', 60);

        $body = [
            'items' => [[
                'id' => $reference,
                'title' => 'Recarga de saldo PlayBoss',
                'quantity' => 1,
                'currency_id' => (string) $payload['currency'],
                'unit_price' => (float) $payload['amount'],
            ]],
            'external_reference' => $reference,
            'notification_url' => $this->notificationUrl(),
            'statement_descriptor' => 'PLAYBOSS',
        ];
        $redirect = config('services.mercadopago.redirect_url');
        if (is_string($redirect) && $redirect !== '') {
            $body['back_urls'] = ['success' => $redirect, 'failure' => $redirect, 'pending' => $redirect];
            if (str_starts_with($redirect, 'https://')) {
                $body['auto_return'] = 'approved'; // Mercado Pago solo lo acepta con back_urls https
            }
        }
        if ($minutos > 0) {
            $body['expires'] = true;
            $body['expiration_date_to'] = gmdate('Y-m-d\TH:i:s.000-00:00', time() + $minutos * 60);
        }

        $response = $this->http()->withHeaders(['X-Idempotency-Key' => $claveIdempotencia])->post('/checkout/preferences', $body);
        $response->throw();
        $sandbox = (bool) config('services.mercadopago.use_sandbox_url', false);
        $url = $response->json($sandbox ? 'sandbox_init_point' : 'init_point');
        if (!is_string($url) || $url === '') {
            throw new RuntimeException('Mercado Pago no devolvió la URL de pago.');
        }

        return ['reference' => $reference, 'status' => 'PENDING', 'checkout_url' => $url];
    }

    /**
     * @param array{data_id?:string,x_signature?:string,x_request_id?:string} $event
     * @return array{verified:bool,event_id:string,reference:string,status:string,amount:string,currency:string}
     */
    public function verificarEvento(array $event): array
    {
        $vacio = ['verified' => false, 'event_id' => '', 'reference' => '', 'status' => '', 'amount' => '0.00', 'currency' => ''];
        $paymentId = (string) ($event['data_id'] ?? '');
        if ($paymentId === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $paymentId) || !$this->firmaValida($event, $paymentId)) {
            return $vacio;
        }

        $pago = $this->obtenerPago($paymentId);

        return $pago === null ? $vacio : $this->normalizar($pago);
    }

    public function consultarPorReferencia(string $reference): ?array
    {
        $response = $this->http()->get('/v1/payments/search', [
            'external_reference' => $reference, 'sort' => 'date_created', 'criteria' => 'desc',
        ]);
        $response->throw();
        $lista = $response->json('results');
        if (!is_array($lista) || $lista === []) {
            return null;
        }
        $elegido = collect($lista)->firstWhere('status', 'approved') ?? $lista[0];

        return $this->normalizar($elegido);
    }

    /**
     * Manifiesto: "id:<data.id en minúscula>;request-id:<x-request-id>;ts:<ts>;" firmado con HMAC-SHA256.
     * Si no hay secreto configurado se omite (la confirmación contra la API sigue siendo obligatoria).
     */
    public function firmaValida(array $event, string $paymentId): bool
    {
        $secreto = config('services.mercadopago.webhook_secret');
        if (!is_string($secreto) || $secreto === '') {
            return true;
        }
        $header = (string) ($event['x_signature'] ?? '');
        $partes = [];
        foreach (explode(',', $header) as $parte) {
            [$k, $v] = array_pad(array_map('trim', explode('=', $parte, 2)), 2, '');
            $partes[$k] = $v;
        }
        if (empty($partes['ts']) || empty($partes['v1'])) {
            return false;
        }
        $manifiesto = 'id:' . strtolower($paymentId) . ';request-id:' . ($event['x_request_id'] ?? '') . ';ts:' . $partes['ts'] . ';';

        return hash_equals(hash_hmac('sha256', $manifiesto, $secreto), strtolower($partes['v1']));
    }

    public function notificationUrl(): string
    {
        $url = config('services.mercadopago.notification_url');

        return is_string($url) && $url !== '' ? $url : url('/api/pagos/mercadopago/webhook');
    }

    private function obtenerPago(string $id): ?array
    {
        $response = $this->http()->get('/v1/payments/' . rawurlencode($id));
        if ($response->status() === 404) {
            return null;
        }
        $response->throw();
        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    private function normalizar(array $pago): array
    {
        $status = self::STATUS_MAP[strtolower((string) ($pago['status'] ?? ''))] ?? '';
        $cents = (int) round(((float) ($pago['transaction_amount'] ?? 0)) * 100);

        return [
            'verified' => $status !== '',
            'event_id' => 'mercadopago:' . ($pago['id'] ?? '') . ':' . $status,
            'reference' => (string) ($pago['external_reference'] ?? ''),
            'status' => $status,
            'amount' => intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT),
            'currency' => strtoupper((string) ($pago['currency_id'] ?? '')),
        ];
    }

    private function http()
    {
        $token = config('services.mercadopago.access_token');
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Configura MERCADOPAGO_ACCESS_TOKEN en el .env.');
        }

        return Http::baseUrl(rtrim((string) config('services.mercadopago.base_url', 'https://api.mercadopago.com'), '/'))
            ->acceptJson()->withToken($token)->connectTimeout(5)->timeout(15);
    }
}
