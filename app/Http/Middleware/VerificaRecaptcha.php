<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica un token reCAPTCHA v3 contra Google. Uso: ->middleware(VerificaRecaptcha::class . ':login').
 * El token viaja en `recaptcha_token` (body) o en el header `X-Recaptcha-Token`.
 * Falla cerrada: si está habilitado y Google no responde, devuelve 502.
 */
class VerificaRecaptcha
{
    public function handle(Request $request, Closure $next, string $accion = ''): Response
    {
        if (!config('services.recaptcha.enabled')) {
            return $next($request);
        }

        $token = (string) ($request->input('recaptcha_token') ?: $request->header('X-Recaptcha-Token', ''));
        if ($token === '') {
            return $this->error('Falta la verificación reCAPTCHA.', 422);
        }

        $secreto = (string) config('services.recaptcha.secret');
        if ($secreto === '') {
            Log::error('RECAPTCHA_ENABLED=true pero RECAPTCHA_SECRET está vacío.');
            return $this->error('La verificación reCAPTCHA no está configurada.', 502);
        }

        try {
            $respuesta = Http::asForm()->timeout(5)->post(config('services.recaptcha.verify_url'), [
                'secret' => $secreto,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);
        } catch (ConnectionException $e) {
            Log::warning('reCAPTCHA: Google no respondió.', ['exception' => $e::class]);
            return $this->error('No se pudo verificar reCAPTCHA. Intenta nuevamente.', 502);
        }

        if (!$respuesta->successful() || !is_array($respuesta->json())) {
            Log::warning('reCAPTCHA: respuesta inválida de Google.', ['status' => $respuesta->status()]);
            return $this->error('No se pudo verificar reCAPTCHA. Intenta nuevamente.', 502);
        }

        $datos = $respuesta->json();
        if (empty($datos['success'])) {
            return $this->error('Verificación reCAPTCHA inválida o vencida.', 422);
        }
        if ($accion !== '' && ($datos['action'] ?? null) !== $accion) {
            return $this->error('Verificación reCAPTCHA inválida para esta acción.', 403);
        }
        if ((float) ($datos['score'] ?? 0) < (float) config('services.recaptcha.min_score')) {
            return $this->error('No pudimos verificar que eres una persona. Intenta nuevamente.', 403);
        }

        return $next($request);
    }

    private function error(string $mensaje, int $status): JsonResponse
    {
        return response()->json(['error' => 1, 'mensaje' => [$mensaje], 'data' => []], $status);
    }
}
