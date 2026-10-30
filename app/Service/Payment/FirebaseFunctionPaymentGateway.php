<?php

namespace App\Service\Payment;

use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Firebase Cloud Function actúa como adaptador seguro hacia el proveedor real
 * de pagos. Firebase no captura ni liquida dinero por sí mismo.
 */
class FirebaseFunctionPaymentGateway implements PaymentGateway
{
    public function crearIntento(array $payload, string $claveIdempotencia): array
    {
        $url = config('services.payment_gateway.firebase_function_url');
        $token = config('services.payment_gateway.firebase_function_token');
        if (!is_string($url) || $url === '' || !is_string($token) || $token === '') {
            throw new RuntimeException('Configura FIREBASE_PAYMENT_FUNCTION_URL y FIREBASE_PAYMENT_FUNCTION_TOKEN.');
        }

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->withHeaders(['Idempotency-Key' => $claveIdempotencia])
            ->connectTimeout(5)
            ->timeout(20)
            ->post($url, ['operation' => 'create_payment_intent', 'payment' => $payload]);

        $response->throw();
        $data = $response->json();
        if (!is_array($data) || !isset($data['reference'], $data['status'])) {
            throw new RuntimeException('La Cloud Function devolvió una respuesta de pago incompleta.');
        }

        return [
            'reference' => (string) $data['reference'],
            'status' => strtoupper((string) $data['status']),
            'checkout_url' => isset($data['checkout_url']) ? (string) $data['checkout_url'] : null,
        ];
    }

    public function verificarEvento(array $event): array
    {
        $url = config('services.payment_gateway.firebase_function_url');
        $token = config('services.payment_gateway.firebase_function_token');
        if (!is_string($url) || $url === '' || !is_string($token) || $token === '') {
            throw new RuntimeException('Configura FIREBASE_PAYMENT_FUNCTION_URL y FIREBASE_PAYMENT_FUNCTION_TOKEN.');
        }

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->connectTimeout(5)
            ->timeout(20)
            ->post($url, ['operation' => 'verify_payment_event', 'event' => $event]);
        $response->throw();
        $data = $response->json();

        if (!is_array($data) || !array_key_exists('verified', $data)) {
            throw new RuntimeException('La Cloud Function no devolvió el resultado de verificación del evento.');
        }

        return [
            'verified' => (bool) $data['verified'],
            'event_id' => (string) ($data['event_id'] ?? ''),
            'reference' => (string) ($data['reference'] ?? ''),
            'status' => strtoupper((string) ($data['status'] ?? '')),
            'amount' => (string) ($data['amount'] ?? ''),
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
        ];
    }
}
