<?php

namespace App\Service\Payment;

use App\Contracts\CheckoutDataProvider;
use App\Contracts\ConsultableGateway;
use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Wompi (Colombia). Modo sandbox con llaves pub_test_/prv_test_.
 * Docs: https://docs.wompi.co/docs/colombia/widget-checkout-web/ y /eventos/
 */
class WompiPaymentGateway implements PaymentGateway, CheckoutDataProvider, ConsultableGateway
{
    private const STATUS_MAP = [
        'APPROVED' => 'APPROVED',
        'DECLINED' => 'DECLINED',
        'VOIDED' => 'CANCELLED',
        'ERROR' => 'FAILED',
        'PENDING' => 'PENDING',
    ];

    public function crearIntento(array $payload, string $claveIdempotencia): array
    {
        $reference = 'PB-' . $payload['transaction_id'] . '-' . strtoupper(Str::random(8));
        $cents = $this->aCentavos((string) $payload['amount']);
        $checkout = $this->datosCheckout($reference, $cents, (string) $payload['currency']);

        return ['reference' => $reference, 'status' => 'PENDING', 'checkout_url' => $checkout['checkout_url']];
    }

    public function datosCheckout(string $reference, int $amountInCents, string $currency): array
    {
        $publicKey = $this->config('public_key');
        $signature = $this->firmaIntegridad($reference, $amountInCents, $currency);
        $redirect = config('services.wompi.redirect_url');

        $query = [
            'public-key' => $publicKey,
            'currency' => $currency,
            'amount-in-cents' => $amountInCents,
            'reference' => $reference,
            'signature:integrity' => $signature,
        ];
        if (is_string($redirect) && $redirect !== '') {
            $query['redirect-url'] = $redirect;
        }

        return [
            'provider' => 'wompi',
            'public_key' => $publicKey,
            'reference' => $reference,
            'amount_in_cents' => $amountInCents,
            'currency' => $currency,
            'signature_integrity' => $signature,
            'redirect_url' => $redirect ?: null,
            'checkout_url' => rtrim((string) config('services.wompi.checkout_url', 'https://checkout.wompi.co/p/'), '?') . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986),
        ];
    }

    /** SHA256(referencia + monto en centavos + moneda + secreto de integridad). */
    public function firmaIntegridad(string $reference, int $amountInCents, string $currency): string
    {
        return hash('sha256', $reference . $amountInCents . $currency . $this->config('integrity_secret'));
    }

    /**
     * Valida el checksum del evento y confirma el estado real contra la API de Wompi;
     * nunca se confía en el estado/monto que trae el cuerpo del webhook.
     */
    public function verificarEvento(array $event): array
    {
        $vacio = ['verified' => false, 'event_id' => '', 'reference' => '', 'status' => '', 'amount' => '0.00', 'currency' => ''];
        if (!$this->checksumValido($event)) {
            return $vacio;
        }

        $tx = $event['data']['transaction'] ?? null;
        if (!is_array($tx) || empty($tx['id'])) {
            return $vacio;
        }

        $remota = $this->obtenerTransaccion((string) $tx['id']);
        if ($remota === null || (string) ($remota['reference'] ?? '') !== (string) ($tx['reference'] ?? '')) {
            return $vacio;
        }

        return $this->normalizar($remota);
    }

    public function consultarPorReferencia(string $reference): ?array
    {
        $response = $this->http()->get('/transactions', ['reference' => $reference]);
        $response->throw();
        $lista = $response->json('data');
        if (!is_array($lista) || $lista === []) {
            return null;
        }
        // Prioriza una transacción aprobada; si no, la más reciente.
        usort($lista, fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        $elegida = collect($lista)->firstWhere('status', 'APPROVED') ?? $lista[0];

        return $this->normalizar($elegida);
    }

    public function checksumValido(array $event): bool
    {
        $signature = $event['signature'] ?? null;
        // Wompi envía `timestamp` en la raíz del evento; `signature.timestamp` se acepta solo por compatibilidad.
        $timestamp = $event['timestamp'] ?? (is_array($signature) ? ($signature['timestamp'] ?? null) : null);
        if (!is_array($signature) || !isset($signature['checksum'], $signature['properties']) || !is_array($signature['properties']) || !is_scalar($timestamp) || $timestamp === '') {
            return false;
        }

        $concat = '';
        foreach ($signature['properties'] as $path) {
            $value = data_get($event['data'] ?? [], (string) $path);
            if ($value === null || is_array($value)) {
                return false;
            }
            $concat .= is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }
        $calculado = hash('sha256', $concat . $timestamp . $this->config('events_secret'));

        return hash_equals($calculado, strtolower((string) $signature['checksum']));
    }

    private function obtenerTransaccion(string $id): ?array
    {
        $response = $this->http()->get('/transactions/' . rawurlencode($id));
        if ($response->status() === 404) {
            return null;
        }
        $response->throw();
        $data = $response->json('data');

        return is_array($data) ? $data : null;
    }

    private function normalizar(array $tx): array
    {
        $status = self::STATUS_MAP[strtoupper((string) ($tx['status'] ?? ''))] ?? '';
        $cents = (int) ($tx['amount_in_cents'] ?? 0);

        return [
            'verified' => $status !== '',
            'event_id' => 'wompi:' . $tx['id'] . ':' . strtoupper((string) $tx['status']),
            'reference' => (string) ($tx['reference'] ?? ''),
            'status' => $status,
            'amount' => intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT),
            'currency' => strtoupper((string) ($tx['currency'] ?? '')),
        ];
    }

    private function aCentavos(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function http()
    {
        $base = (string) config('services.wompi.base_url', 'https://sandbox.wompi.co/v1');

        return Http::baseUrl(rtrim($base, '/'))->acceptJson()
            ->withToken($this->config('private_key'))
            ->connectTimeout(5)->timeout(15);
    }

    private function config(string $key): string
    {
        $value = config('services.wompi.' . $key);
        if (!is_string($value) || $value === '') {
            throw new RuntimeException('Configura WOMPI_' . strtoupper($key) . ' en el .env.');
        }

        return $value;
    }
}
