<?php

namespace Tests\Feature;

use App\Models\Usuario\Usuario;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** reCAPTCHA v3 y login con Google. Google se simula con Http::fake. */
class RecaptchaGoogleAuthTest extends TestCase
{
    private const CLIENT = 'cliente-test.apps.googleusercontent.com';
    private $clave;
    private array $jwks;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.google_auth.client_id' => self::CLIENT, 'services.recaptcha.secret' => 'secreto-test']);

        $this->clave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $d = openssl_pkey_get_details($this->clave);
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $this->jwks = ['keys' => [['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'k1', 'n' => $b64($d['rsa']['n']), 'e' => $b64($d['rsa']['e'])]]];
    }

    private function jwt(array $extra = [], $clave = null): string
    {
        $claims = array_merge([
            'iss' => 'https://accounts.google.com', 'aud' => self::CLIENT, 'sub' => '1122334455', 'email' => 'Ana@Example.com',
            'email_verified' => true, 'given_name' => 'Ana', 'family_name' => 'Lopez', 'picture' => 'https://lh3.googleusercontent.com/a/x',
            'iat' => time() - 10, 'exp' => time() + 3600,
        ], $extra);

        return JWT::encode($claims, $clave ?? $this->clave, 'RS256', 'k1');
    }

    private function fakeGoogle(): void
    {
        Http::fake(['www.googleapis.com/oauth2/v3/certs' => Http::response($this->jwks)]);
    }

    // ------------------------------------------------------------------ reCAPTCHA

    public function test_recaptcha_deshabilitado_no_bloquea(): void
    {
        Http::fake();
        $this->postJson('/api/auth/login', [])->assertStatus(422);
        Http::assertNothingSent();
    }

    private function conRecaptcha(array $respuestaGoogle, int $status = 200): void
    {
        config(['services.recaptcha.enabled' => true, 'services.recaptcha.min_score' => 0.5]);
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response($respuestaGoogle, $status)]);
    }

    public function test_recaptcha_sin_token_es_422(): void
    {
        $this->conRecaptcha(['success' => true, 'score' => 0.9, 'action' => 'login']);
        $this->postJson('/api/auth/login', ['correo' => 'a@b.co', 'contrasena' => 'x'])->assertStatus(422)->assertJson(['error' => 1]);
        Http::assertNothingSent();
    }

    public function test_recaptcha_valido_deja_pasar_a_la_validacion_normal(): void
    {
        $this->conRecaptcha(['success' => true, 'score' => 0.9, 'action' => 'login']);
        $this->postJson('/api/auth/login', ['recaptcha_token' => 't'])->assertStatus(422)->assertJsonValidationErrorFor('correo', 'data.errores');
        Http::assertSent(fn ($r) => $r['secret'] === 'secreto-test' && $r['response'] === 't');
    }

    public function test_recaptcha_acepta_header(): void
    {
        $this->conRecaptcha(['success' => true, 'score' => 0.9, 'action' => 'recuperar']);
        $this->postJson('/api/auth/recuperar', [], ['X-Recaptcha-Token' => 't'])->assertStatus(422)->assertJsonValidationErrorFor('correo', 'data.errores');
    }

    public function test_recaptcha_score_bajo_o_accion_distinta_es_403(): void
    {
        $this->conRecaptcha(['success' => true, 'score' => 0.1, 'action' => 'login']);
        $this->postJson('/api/auth/login', ['recaptcha_token' => 't'])->assertStatus(403);

        $this->conRecaptcha(['success' => true, 'score' => 0.9, 'action' => 'registro']);
        $this->postJson('/api/auth/login', ['recaptcha_token' => 't'])->assertStatus(403);
    }

    public function test_recaptcha_rechazado_por_google_es_422_en_cada_endpoint_protegido(): void
    {
        $this->conRecaptcha(['success' => false, 'error-codes' => ['timeout-or-duplicate']]);
        foreach (['registro', 'login', 'recuperar', 'restablecer'] as $ruta) {
            $this->postJson("/api/auth/$ruta", ['recaptcha_token' => 't'])->assertStatus(422)->assertJson(['mensaje' => ['Verificación reCAPTCHA inválida o vencida.']]);
        }
    }

    public function test_recaptcha_falla_cerrada_si_google_no_responde(): void
    {
        config(['services.recaptcha.enabled' => true]);
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->postJson('/api/auth/login', ['recaptcha_token' => 't'])->assertStatus(502)->assertJson(['error' => 1]);

        $this->conRecaptcha([], 500);
        $this->postJson('/api/auth/login', ['recaptcha_token' => 't'])->assertStatus(502);
    }

    public function test_recaptcha_habilitado_sin_secreto_es_502(): void
    {
        $this->conRecaptcha(['success' => true]);
        config(['services.recaptcha.secret' => '']);
        $this->postJson('/api/auth/login', ['recaptcha_token' => 't'])->assertStatus(502);
    }

    // ------------------------------------------------------------------ /auth/google (token)

    public function test_google_exige_credential(): void
    {
        $this->postJson('/api/auth/google', [])->assertStatus(422)->assertJsonValidationErrorFor('credential', 'data.errores');
    }

    public function test_google_token_basura_o_firma_ajena_o_audiencia_distinta_o_vencido_es_401(): void
    {
        $this->fakeGoogle();
        $otra = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        foreach ([
            'basura',
            $this->jwt([], $otra),
            $this->jwt(['aud' => 'otro-cliente']),
            $this->jwt(['iss' => 'https://evil.example']),
            $this->jwt(['exp' => time() - 3600, 'iat' => time() - 7200]),
        ] as $token) {
            $this->postJson('/api/auth/google', ['credential' => $token])->assertStatus(401)->assertJson(['error' => 1]);
        }
    }

    public function test_google_sin_client_id_es_503_y_sin_conexion_a_google_es_502(): void
    {
        config(['services.google_auth.client_id' => null]);
        $this->postJson('/api/auth/google', ['credential' => 'x'])->assertStatus(503);

        config(['services.google_auth.client_id' => self::CLIENT]);
        Http::fake(fn () => throw new ConnectionException('caído'));
        $this->postJson('/api/auth/google', ['credential' => $this->jwt()])->assertStatus(502);
    }

    public function test_completar_perfil_exige_autenticacion_y_campos(): void
    {
        $this->putJson('/api/auth/google/completar', [])->assertStatus(401);

        $u = new Usuario();
        $u->forceFill(['id_usuario' => 5, 'rol_codigo' => 'CLIENTE', 'estado_codigo' => 'ACT']);
        Sanctum::actingAs($u);
        $r = $this->putJson('/api/auth/google/completar', []);
        $r->assertStatus(422);
        foreach (['primer_apellido', 'tipo_documento_codigo', 'numero_documento', 'fecha_nacimiento'] as $campo) {
            $this->assertArrayHasKey($campo, $r->json('data.errores'));
        }
    }

    // Nota: el flujo con base de datos (crear/vincular usuario, perfil incompleto) no se prueba aquí porque el entorno de
    // pruebas no tiene ningún driver PDO; se verifica contra SQL Server real.
}
