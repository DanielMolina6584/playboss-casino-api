<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureMfaRol;
use App\Models\Usuario\PersonalAccessToken;
use App\Service\Auth\Mfa\MfaAlmacen;
use App\Service\Auth\Mfa\SvcMfa;
use App\Service\Auth\Mfa\Totp;
use App\Service\Storage\AuditLogService;
use App\Service\SvcUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AuditoriaEnMemoria;
use Tests\Support\MfaAlmacenEnMemoria;
use Tests\Support\SvcUsuarioEnMemoria;
use Tests\Support\UsuarioFalso;
use Tests\TestCase;

require_once __DIR__ . '/../Support/MfaFakes.php';

/** 2FA TOTP: la lógica corre contra un almacén en memoria (el entorno de pruebas no tiene SQL Server). */
class MfaTest extends TestCase
{
    private MfaAlmacenEnMemoria $db;
    private AuditoriaEnMemoria $audit;
    private const T0 = '2026-11-10 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['mfa.enabled' => true, 'mfa.required_roles' => ['ADMIN'], 'mfa.issuer' => 'PlayBoss']);
        Carbon::setTestNow(self::T0);
        $this->db = new MfaAlmacenEnMemoria();
        $this->audit = new AuditoriaEnMemoria();
        $this->app->instance(MfaAlmacen::class, $this->db);
        $this->app->instance(AuditLogService::class, $this->audit);
        $this->app->instance(SvcUsuario::class, new SvcUsuarioEnMemoria($this->db, $this->audit));
        $this->app->forgetInstance(SvcMfa::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function usuario(int $id = 7, string $rol = 'CLIENTE', ?string $contrasena = 'Clave-segura-1'): UsuarioFalso
    {
        return $this->db->agregar(UsuarioFalso::crear($id, $rol, $contrasena));
    }

    private function codigo(string $secreto, int $offsetSegundos = 0): string
    {
        return Totp::codigo($secreto, now()->timestamp + $offsetSegundos);
    }

    /** Activa el 2FA vía API y devuelve [secreto, códigos de recuperación]. Deja el reloj 60 s adelante (ventana nueva). */
    private function activar(UsuarioFalso $u): array
    {
        Sanctum::actingAs($u);
        $secreto = $this->postJson('/api/auth/mfa/configurar')->assertOk()->json('data.secreto');
        $r = $this->postJson('/api/auth/mfa/activar', ['codigo' => $this->codigo($secreto)])->assertOk();
        Carbon::setTestNow(now()->addSeconds(90));

        return [$secreto, $r->json('data.recuperacion_codigos')];
    }

    // ------------------------------------------------------------------ configuración y activación

    public function test_estado_y_configurar_guardan_el_secreto_cifrado_y_devuelven_uri(): void
    {
        $u = $this->usuario();
        Sanctum::actingAs($u);
        $this->getJson('/api/auth/mfa')->assertOk()->assertJsonPath('data.activo', false)->assertJsonPath('data.habilitado', true)->assertJsonPath('data.obligatorio', false)->assertJsonPath('data.tiene_contrasena', true);

        $r = $this->postJson('/api/auth/mfa/configurar')->assertOk();
        $secreto = $r->json('data.secreto');
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secreto);
        $this->assertStringStartsWith('otpauth://totp/PlayBoss%3Au7%40example.com?secret=' . $secreto . '&issuer=PlayBoss', $r->json('data.otpauth_uri'));

        $guardado = $this->db->obtener(7)['secreto_cifrado'];
        $this->assertStringNotContainsString($secreto, $guardado);
        $this->assertSame($secreto, Crypt::decryptString($guardado));
        $this->getJson('/api/auth/mfa')->assertJsonPath('data.activo', false)->assertJsonPath('data.configuracion_pendiente', true);
    }

    public function test_activar_exige_codigo_correcto_y_devuelve_diez_recovery_una_vez(): void
    {
        $u = $this->usuario();
        Sanctum::actingAs($u);
        $secreto = $this->postJson('/api/auth/mfa/configurar')->json('data.secreto');

        $this->postJson('/api/auth/mfa/activar', [])->assertStatus(422);
        $this->postJson('/api/auth/mfa/activar', ['codigo' => '000000'])->assertStatus(422)->assertJson(['error' => 1]);
        $this->assertNull($this->db->obtener(7)['confirmado_en']);

        $r = $this->postJson('/api/auth/mfa/activar', ['codigo' => $this->codigo($secreto)])->assertOk()->assertJsonPath('data.activo', true);
        $codigos = $r->json('data.recuperacion_codigos');
        $this->assertCount(10, $codigos);
        $this->assertCount(10, array_unique($codigos));
        foreach ($codigos as $c) {
            $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{5}-[A-HJ-NP-Z2-9]{5}$/', $c);
            $this->assertArrayNotHasKey($c, $this->db->recuperacion[7], 'se guardan solo hasheados');
        }
        $this->assertContains('mfa.enabled', $this->audit->nombres());
        $this->assertNotEmpty($this->db->sesionesRevocadas, 'al activar se cierran las demás sesiones');

        // Ya activo: no se reconfigura ni se reactiva, y el estado no vuelve a mostrar códigos.
        $this->postJson('/api/auth/mfa/configurar')->assertStatus(409);
        $this->getJson('/api/auth/mfa')->assertJsonPath('data.recuperacion_restantes', 10)->assertJsonMissingPath('data.recuperacion_codigos');
    }

    public function test_el_mismo_codigo_no_se_puede_reutilizar(): void
    {
        $u = $this->usuario();
        Sanctum::actingAs($u);
        $secreto = $this->postJson('/api/auth/mfa/configurar')->json('data.secreto');
        $codigo = $this->codigo($secreto);
        $this->postJson('/api/auth/mfa/activar', ['codigo' => $codigo])->assertOk();

        // Mismo código, misma ventana: rechazado (aunque la contraseña sea correcta).
        $this->postJson('/api/auth/mfa/recovery-codes', ['codigo' => $codigo])->assertStatus(422);
        // Un código de la ventana anterior (más viejo que el último usado) tampoco.
        $this->postJson('/api/auth/mfa/recovery-codes', ['codigo' => $this->codigo($secreto, -30)])->assertStatus(422);
        // Uno de la ventana siguiente sí.
        $this->postJson('/api/auth/mfa/recovery-codes', ['codigo' => $this->codigo($secreto, 30)])->assertOk();
    }

    public function test_tolerancia_de_una_ventana_pero_no_dos(): void
    {
        $u = $this->usuario();
        Sanctum::actingAs($u);
        $secreto = $this->postJson('/api/auth/mfa/configurar')->json('data.secreto');
        $this->postJson('/api/auth/mfa/activar', ['codigo' => $this->codigo($secreto, -60)])->assertStatus(422);
        $this->postJson('/api/auth/mfa/activar', ['codigo' => $this->codigo($secreto, 60)])->assertStatus(422);
        $this->postJson('/api/auth/mfa/activar', ['codigo' => $this->codigo($secreto, -30)])->assertOk();
    }

    // ------------------------------------------------------------------ login con reto

    private function login(string $correo = 'u7@example.com', string $clave = 'Clave-segura-1')
    {
        return $this->postJson('/api/auth/login', ['correo' => $correo, 'contrasena' => $clave]);
    }

    public function test_login_con_mfa_no_entrega_token_de_api_y_se_canjea_en_verificar(): void
    {
        [$secreto] = $this->activar($this->usuario());
        $this->app['auth']->forgetGuards();

        $r = $this->login()->assertOk()->assertJsonPath('data.mfa_required', true)->assertJsonMissingPath('data.token')->assertJsonMissingPath('data.usuario');
        $mfa = $r->json('data.mfa_token');
        $this->assertSame(300, $r->json('data.expira_en'));
        $this->assertSame(0, $this->db->tokens, 'no se emitió ningún token Sanctum');

        $this->postJson('/api/auth/mfa/verificar', ['mfa_token' => $mfa, 'codigo' => $this->codigo($secreto)])
            ->assertOk()->assertJsonPath('data.token', '1|token-real-7')->assertJsonPath('data.usuario.id_usuario', 7)->assertJsonPath('data.factor', 'totp');
        // Un solo uso.
        $this->postJson('/api/auth/mfa/verificar', ['mfa_token' => $mfa, 'codigo' => $this->codigo($secreto, 30)])->assertStatus(401);
    }

    public function test_credenciales_malas_siguen_dando_el_mismo_401_y_no_revelan_mfa(): void
    {
        $this->activar($this->usuario());
        $this->login('u7@example.com', 'mala')->assertStatus(401)->assertJsonPath('mensaje.0', 'Correo o contraseña incorrectos.')->assertJsonMissingPath('data.mfa_required');
        $this->login('nadie@example.com')->assertStatus(401)->assertJsonPath('mensaje.0', 'Correo o contraseña incorrectos.');
    }

    public function test_mfa_token_vencido_o_inventado_responde_401_generico(): void
    {
        [$secreto] = $this->activar($this->usuario());
        $mfa = $this->login()->json('data.mfa_token');

        $this->postJson('/api/auth/mfa/verificar', ['mfa_token' => 'inventado', 'codigo' => '123456'])->assertStatus(401);
        Carbon::setTestNow(now()->addSeconds(301));
        Cache::flush(); // el array store no expira solo con setTestNow; el TTL real lo hace el driver
        $this->postJson('/api/auth/mfa/verificar', ['mfa_token' => $mfa, 'codigo' => $this->codigo($secreto)])->assertStatus(401);
        $this->postJson('/api/auth/mfa/verificar', ['codigo' => '123456'])->assertStatus(422);
    }

    public function test_mfa_token_no_sirve_como_bearer_en_rutas_normales(): void
    {
        [$secreto] = $this->activar($this->usuario());
        $mfa = $this->login()->json('data.mfa_token');

        // El reto vive solo en caché: no es un token Sanctum, así que ningún guard auth:sanctum lo reconoce.
        Sanctum::usePersonalAccessTokenModel(TokenSinFilas::class);
        $this->app['auth']->forgetGuards();
        foreach (['/api/auth/me', '/api/perfil', '/api/cuenta', '/api/auth/mfa'] as $ruta) {
            $this->withToken($mfa)->getJson($ruta)->assertStatus(401);
        }
        $this->withToken($mfa)->postJson('/api/pagos/retiros', [])->assertStatus(401);
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    public function test_codigo_de_recuperacion_funciona_una_sola_vez(): void
    {
        [$secreto, $codigos] = $this->activar($this->usuario());

        $mfa = $this->login()->json('data.mfa_token');
        $this->postJson('/api/auth/mfa/verificar', ['mfa_token' => $mfa, 'codigo_recuperacion' => strtolower($codigos[0])])
            ->assertOk()->assertJsonPath('data.factor', 'recuperacion')->assertJsonPath('data.recuperacion_restantes', 9);
        $this->assertContains('mfa.recovery_used', $this->audit->nombres());

        $mfa = $this->login()->json('data.mfa_token');
        $this->postJson('/api/auth/mfa/verificar', ['mfa_token' => $mfa, 'codigo_recuperacion' => $codigos[0]])->assertStatus(401);
        $this->postJson('/api/auth/mfa/verificar', ['mfa_token' => $mfa, 'codigo_recuperacion' => str_replace('-', ' ', $codigos[1])])->assertOk()->assertJsonPath('data.recuperacion_restantes', 8);
    }

    public function test_regenerar_codigos_invalida_los_anteriores(): void
    {
        [$secreto, $viejos] = $this->activar($u = $this->usuario());
        Sanctum::actingAs($u);
        $this->postJson('/api/auth/mfa/recovery-codes', [])->assertStatus(422);
        $nuevos = $this->postJson('/api/auth/mfa/recovery-codes', ['codigo' => $this->codigo($secreto)])->assertOk()->json('data.recuperacion_codigos');
        $this->assertCount(10, $nuevos);

        $this->app['auth']->forgetGuards();
        $mfa = $this->login()->json('data.mfa_token');
        $this->postJson('/api/auth/mfa/verificar', ['mfa_token' => $mfa, 'codigo_recuperacion' => $viejos[0]])->assertStatus(401);
        $this->postJson('/api/auth/mfa/verificar', ['mfa_token' => $mfa, 'codigo_recuperacion' => $nuevos[0]])->assertOk();
    }

    // ------------------------------------------------------------------ rate limit

    public function test_cinco_fallos_bloquean_aunque_el_codigo_despues_sea_correcto(): void
    {
        [$secreto] = $this->activar($this->usuario());
        $mfa = $this->login()->json('data.mfa_token');

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/mfa/verificar', ['mfa_token' => $mfa, 'codigo' => '000000'])->assertStatus(401);
        }
        // El 5.º fallo también es 401, pero quema el reto (límite por token) y activa el bloqueo por usuario+IP.
        $this->postJson('/api/auth/mfa/verificar', ['mfa_token' => $mfa, 'codigo' => '000000'])->assertStatus(401);

        $nuevo = $this->login()->json('data.mfa_token');
        $r = $this->postJson('/api/auth/mfa/verificar', ['mfa_token' => $nuevo, 'codigo' => $this->codigo($secreto)])->assertStatus(429);
        $this->assertGreaterThan(0, $r->json('data.retry_after'));
        $this->assertContains('mfa.locked', $this->audit->nombres());

        // Pasados 5 minutos vuelve a funcionar (el bloqueo por usuario+IP expiró).
        Carbon::setTestNow(now()->addSeconds(301));
        Cache::flush();
        $nuevo = $this->login()->json('data.mfa_token');
        $this->postJson('/api/auth/mfa/verificar', ['mfa_token' => $nuevo, 'codigo' => $this->codigo($secreto)])->assertOk();
    }

    public function test_el_reto_se_quema_tras_cinco_fallos(): void
    {
        [$secreto] = $this->activar($this->usuario());
        $mfa = $this->login()->json('data.mfa_token');
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/mfa/verificar', ['mfa_token' => $mfa, 'codigo' => '000000']);
        }
        \Illuminate\Support\Facades\RateLimiter::clear('mfa:7:127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear('mfa:7');
        $this->postJson('/api/auth/mfa/verificar', ['mfa_token' => $mfa, 'codigo' => $this->codigo($secreto)])->assertStatus(401);
    }

    public function test_fallos_quedan_auditados(): void
    {
        $this->activar($this->usuario());
        $mfa = $this->login()->json('data.mfa_token');
        $this->postJson('/api/auth/mfa/verificar', ['mfa_token' => $mfa, 'codigo' => '000000'])->assertStatus(401);
        $fallos = array_filter($this->audit->autenticaciones, fn ($a) => $a['exitoso'] === false && $a['usuarioId'] === 7);
        $this->assertNotEmpty($fallos);
    }

    // ------------------------------------------------------------------ desactivar

    public function test_desactivar_exige_contrasena_y_codigo(): void
    {
        [$secreto] = $this->activar($u = $this->usuario());
        Sanctum::actingAs($u);

        $this->postJson('/api/auth/mfa/desactivar', ['codigo' => $this->codigo($secreto)])->assertStatus(422)->assertJsonPath('mensaje.0', 'La contraseña no es correcta.');
        $this->postJson('/api/auth/mfa/desactivar', ['contrasena' => 'mala', 'codigo' => $this->codigo($secreto)])->assertStatus(422);
        $this->postJson('/api/auth/mfa/desactivar', ['contrasena' => 'Clave-segura-1', 'codigo' => '000000'])->assertStatus(422);
        $this->assertNotNull($this->db->obtener(7), 'sigue activo');

        $this->postJson('/api/auth/mfa/desactivar', ['contrasena' => 'Clave-segura-1', 'codigo' => $this->codigo($secreto)])->assertOk()->assertJsonPath('data.activo', false);
        $this->assertNull($this->db->obtener(7));
        $this->assertContains('mfa.disabled', $this->audit->nombres());
    }

    public function test_usuario_de_google_sin_contrasena_desactiva_solo_reautenticando_con_google(): void
    {
        config(['services.google_auth.client_id' => 'cliente-test']);
        $clave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $d = openssl_pkey_get_details($clave);
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        Http::fake(['www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => [['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'k1', 'n' => $b64($d['rsa']['n']), 'e' => $b64($d['rsa']['e'])]]])]);
        $jwt = fn (string $sub) => JWT::encode(['iss' => 'https://accounts.google.com', 'aud' => 'cliente-test', 'sub' => $sub, 'email' => 'u8@example.com', 'email_verified' => true, 'iat' => time() - 10, 'exp' => time() + 3600], $clave, 'RS256', 'k1');

        [$secreto] = $this->activar($u = $this->usuario(8, 'CLIENTE', null));
        Sanctum::actingAs($u);
        $this->postJson('/api/auth/mfa/desactivar', ['credential' => $jwt('otra-persona'), 'codigo' => $this->codigo($secreto)])->assertStatus(422);
        $this->assertNotNull($this->db->obtener(8));
        $this->postJson('/api/auth/mfa/desactivar', ['credential' => $jwt('sub-8'), 'codigo' => $this->codigo($secreto)])->assertOk()->assertJsonPath('data.activo', false);
        $this->assertNull($this->db->obtener(8));
    }

    public function test_usuario_de_google_sin_contrasena_no_puede_desactivar_con_contrasena_ni_jwt_falso(): void
    {
        config(['services.google_auth.client_id' => 'cliente-test']);
        Http::fake(['www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => []])]);
        [$secreto] = $this->activar($u = $this->usuario(8, 'CLIENTE', null));
        Sanctum::actingAs($u);
        $this->getJson('/api/auth/mfa')->assertJsonPath('data.tiene_contrasena', false);
        $this->postJson('/api/auth/mfa/desactivar', ['contrasena' => 'lo-que-sea', 'codigo' => $this->codigo($secreto)])->assertStatus(422);
        $this->postJson('/api/auth/mfa/desactivar', ['credential' => 'jwt-falso', 'codigo' => $this->codigo($secreto)])->assertStatus(422);
        $this->assertNotNull($this->db->obtener(8));
    }

    public function test_un_rol_obligatorio_no_puede_desactivar(): void
    {
        [$secreto] = $this->activar($u = $this->usuario(1, 'ADMIN'));
        Sanctum::actingAs($u);
        $this->postJson('/api/auth/mfa/desactivar', ['contrasena' => 'Clave-segura-1', 'codigo' => $this->codigo($secreto)])->assertStatus(403);
        $this->assertNotNull($this->db->obtener(1));
    }

    // ------------------------------------------------------------------ política

    public function test_admin_sin_mfa_recibe_403_con_mfa_requerido_en_admin(): void
    {
        Sanctum::actingAs($this->usuario(1, 'ADMIN'));
        $this->getJson('/api/admin/retiros')->assertStatus(403)->assertJsonPath('data.mfa_requerido', true);
        $this->getJson('/api/admin/usuarios')->assertStatus(403)->assertJsonPath('data.mfa_requerido', true);
        // Puede llegar a configurar el 2FA.
        $this->getJson('/api/auth/mfa')->assertOk()->assertJsonPath('data.obligatorio', true);
        $this->postJson('/api/auth/mfa/configurar')->assertOk();
    }

    public function test_admin_con_mfa_pasa_el_middleware(): void
    {
        $this->activar($admin = $this->usuario(1, 'ADMIN'));
        $req = Request::create('/api/admin/x');
        $req->setUserResolver(fn () => $admin);
        $respuesta = app(EnsureMfaRol::class)->handle($req, fn () => response('pasó', 200));
        $this->assertSame('pasó', $respuesta->getContent());

        // Un cliente nunca queda bloqueado por la política de ADMIN.
        $cliente = $this->usuario(9);
        $req->setUserResolver(fn () => $cliente);
        $this->assertSame('pasó', app(EnsureMfaRol::class)->handle($req, fn () => response('pasó'))->getContent());
    }

    public function test_politica_configurable_por_env_roles_y_apagado_total(): void
    {
        config(['mfa.required_roles' => ['CLIENTE']]);
        $c = $this->usuario(9);
        Sanctum::actingAs($c);
        $this->getJson('/api/auth/mfa')->assertJsonPath('data.obligatorio', true);
        $this->postJson('/api/pagos/retiros', $this->cuerpoRetiro())->assertStatus(403)->assertJsonPath('data.mfa_requerido', true);

        config(['mfa.enabled' => false]);
        $this->getJson('/api/auth/mfa')->assertOk()->assertJsonPath('data.habilitado', false)->assertJsonPath('data.activo', false);
        $this->postJson('/api/auth/mfa/configurar')->assertStatus(404);
        $this->assertFalse(app(SvcMfa::class)->activo(9));
    }

    // ------------------------------------------------------------------ retiros

    private function cuerpoRetiro(array $extra = []): array
    {
        return $extra + ['monto' => '20000.00', 'banco' => 'Bancolombia', 'tipo_cuenta' => 'AHORROS', 'numero_cuenta' => '12345678901', 'titular' => 'Laura Gomez', 'documento_titular' => '1020304050'];
    }

    public function test_retiro_con_mfa_activo_exige_codigo_vigente(): void
    {
        [$secreto] = $this->activar($u = $this->usuario());
        Sanctum::actingAs($u);

        $this->postJson('/api/pagos/retiros', $this->cuerpoRetiro())->assertStatus(403)->assertJsonPath('data.mfa_codigo_requerido', true);
        $this->postJson('/api/pagos/retiros', $this->cuerpoRetiro(['codigo_mfa' => '000000']))->assertStatus(422)->assertJsonPath('mensaje.0', 'Código incorrecto o vencido.');
    }

    public function test_retiro_sin_mfa_activo_se_permite_para_jugadores_y_con_codigo_correcto_para_quien_lo_tiene(): void
    {
        $svc = app(SvcMfa::class);
        $sin = $this->usuario(20);
        $svc->exigirParaRetiro($sin, null, null, '1.1.1.1', null); // no lanza
        $this->assertTrue(true);

        [$secreto] = $this->activar($con = $this->usuario(21));
        $svc->exigirParaRetiro($con, $this->codigo($secreto), null, '1.1.1.1', null);
        $this->expectException(\App\Exceptions\ReglaNegocioException::class); // el mismo código ya se gastó
        $svc->exigirParaRetiro($con, $this->codigo($secreto), null, '1.1.1.1', null);
    }

    // ------------------------------------------------------------------ restablecimiento por admin

    public function test_admin_restablece_el_mfa_de_un_usuario_con_auditoria(): void
    {
        $this->activar($usuario = $this->usuario(7));
        [$secretoAdmin] = $this->activar($admin = $this->usuario(1, 'ADMIN'));
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/usuarios/7/mfa/restablecer', [])->assertStatus(422);
        $this->postJson('/api/admin/usuarios/1/mfa/restablecer', ['motivo' => 'perdí mi teléfono'])->assertStatus(422); // no a sí mismo
        $this->postJson('/api/admin/usuarios/999/mfa/restablecer', ['motivo' => 'usuario inexistente'])->assertStatus(404);

        $this->postJson('/api/admin/usuarios/7/mfa/restablecer', ['motivo' => 'Validado por soporte, perdió el teléfono'])->assertOk()->assertJsonPath('data.tenia_mfa', true);
        $this->assertNull($this->db->obtener(7));
        $this->assertContains([7, null], $this->db->sesionesRevocadas);
        $evento = collect($this->audit->eventos)->firstWhere(0, 'mfa.admin_reset');
        $this->assertSame(1, $evento[1]);
        $this->assertSame(7, $evento[2]['usuario_id']);
        $this->assertSame('Validado por soporte, perdió el teléfono', $evento[2]['motivo']);

        // Un cliente no puede usar el endpoint.
        Sanctum::actingAs($this->usuario(30));
        $this->postJson('/api/admin/usuarios/7/mfa/restablecer', ['motivo' => 'intento indebido'])->assertStatus(403);
    }

    public function test_tras_el_restablecimiento_el_login_vuelve_a_ser_normal_para_el_estado_mfa(): void
    {
        $this->activar($this->usuario(7));
        app(SvcMfa::class)->restablecer(7, 1, 'prueba', '127.0.0.1');
        $this->assertFalse(app(SvcMfa::class)->activo(7));
    }
}

/** Modelo de token sin filas: simula una tabla personal_access_tokens donde el mfa_token no existe. */
class TokenSinFilas extends PersonalAccessToken
{
    public static function findToken($token)
    {
        return null;
    }
}
