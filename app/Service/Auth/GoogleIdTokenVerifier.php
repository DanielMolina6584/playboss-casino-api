<?php

namespace App\Service\Auth;

use App\Exceptions\ReglaNegocioException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Valida un id_token de Google en local: firma RS256 con las llaves públicas de Google (JWKS en caché),
 * exp, iss y aud (= GOOGLE_CLIENT_ID). Usa firebase/php-jwt, que ya está instalado.
 */
class GoogleIdTokenVerifier
{
    private const EMISORES = ['https://accounts.google.com', 'accounts.google.com'];

    /** @return array{sub:string,email:string,email_verified:bool,name:?string,given_name:?string,family_name:?string,picture:?string} */
    public function verificar(string $credential): array
    {
        $clientId = (string) config('services.google_auth.client_id');
        if ($clientId === '') {
            throw new ReglaNegocioException('El inicio de sesión con Google no está configurado.', 503);
        }

        try {
            JWT::$leeway = 60;
            $claims = (array) JWT::decode($credential, JWK::parseKeySet($this->llaves(), 'RS256'));
        } catch (ReglaNegocioException $e) {
            throw $e;
        } catch (Throwable) {
            throw new ReglaNegocioException('Token de Google inválido o vencido.', 401);
        }

        $aud = $claims['aud'] ?? null;
        if (!in_array($claims['iss'] ?? '', self::EMISORES, true) || !(is_array($aud) ? in_array($clientId, $aud, true) : $aud === $clientId) || empty($claims['sub']) || empty($claims['email'])) {
            throw new ReglaNegocioException('Token de Google inválido.', 401);
        }

        return [
            'sub' => (string) $claims['sub'],
            'email' => mb_strtolower((string) $claims['email']),
            'email_verified' => filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'name' => $claims['name'] ?? null,
            'given_name' => $claims['given_name'] ?? null,
            'family_name' => $claims['family_name'] ?? null,
            'picture' => $claims['picture'] ?? null,
        ];
    }

    private function llaves(): array
    {
        $url = (string) config('services.google_auth.jwks_url');

        return Cache::remember('google_auth.jwks', 3600, function () use ($url): array {
            try {
                $r = Http::timeout(5)->get($url);
            } catch (ConnectionException) {
                throw new ReglaNegocioException('No se pudo contactar a Google. Intenta nuevamente.', 502);
            }
            if (!$r->successful() || !isset($r->json()['keys'])) {
                throw new ReglaNegocioException('No se pudo contactar a Google. Intenta nuevamente.', 502);
            }

            return $r->json();
        });
    }
}
