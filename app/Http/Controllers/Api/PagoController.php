<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Http\Requests\DepositoRequest;
use App\Models\Payment\Transaccion;
use App\Service\Payment\PagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PagoController extends Controller
{
    use RespondsJson;

    public function __construct(private readonly PagoService $pagos)
    {
    }

    public function depositar(DepositoRequest $r): JsonResponse
    {
        \App\Service\Auth\PerfilApostador::exigir((int) $r->user()->getAuthIdentifier());
        $transaccion = $this->pagos->iniciarDeposito(
            (int) $r->user()->getAuthIdentifier(),
            (string) $r->input('monto'),
            (string) $r->input('idempotency_key')
        );

        return $this->ok($this->presentar($transaccion, true), 201);
    }

    public function estado(Request $r, string $referencia): JsonResponse
    {
        $transaccion = Transaccion::query()
            ->where('referencia_externa', $referencia)
            ->where('usuario_id', $r->user()->getAuthIdentifier())
            ->first();
        if (!$transaccion) {
            throw new ReglaNegocioException('Transacción no encontrada.', 404);
        }

        // Si el webhook aún no llegó, consulta a la pasarela (sin fiarse del navegador).
        return $this->ok($this->presentar($this->pagos->sincronizar($transaccion), false));
    }

    /** Público: la autenticidad se valida con el checksum de Wompi y consultando su API. */
    public function webhookWompi(Request $r): JsonResponse
    {
        $evento = $r->json()->all();
        if (($evento['event'] ?? null) !== 'transaction.updated') {
            return $this->ok([], 200, ['Evento ignorado.']);
        }
        // El header X-Event-Checksum debe coincidir con el checksum del cuerpo si se envía.
        $header = $r->header('X-Event-Checksum');
        if ($header && isset($evento['signature']['checksum']) && !hash_equals(strtolower((string) $evento['signature']['checksum']), strtolower($header))) {
            return $this->fail(['Firma inválida.'], 401);
        }

        $this->pagos->confirmarEvento($evento);

        return $this->ok([], 200, ['Evento procesado.']);
    }

    private function presentar(Transaccion $t, bool $conCheckout): array
    {
        $data = [
            'referencia' => $t->referencia_externa,
            'estado' => $t->estado,
            'monto' => $t->monto,
            'moneda' => $t->moneda,
            'tipo' => $t->tipo,
            'creada_en' => $t->fecha_hora,
            'confirmada_en' => $t->confirmada_en,
        ];
        if ($conCheckout) {
            $data['checkout'] = $this->pagos->datosCheckout($t);
            $data['checkout_url'] = $t->url_redireccion;
        }

        return $data;
    }
}
