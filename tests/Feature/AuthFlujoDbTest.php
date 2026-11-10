<?php

namespace Tests\Feature;

use App\Models\Usuario\PerfilUsuario;
use App\Service\Auth\Mfa\Totp;
use App\Service\Storage\AuditLogService;
use App\Service\SvcFotoPerfil;
use Firebase\JWT\JWT;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * reCAPTCHA v3, login con Google y 2FA TOTP de punta a punta por HTTP contra una base relacional real.
 * La conexión sqlsrv apunta a MariaDB/MySQL temporal; las tablas salen de las migraciones sqlsrv reales
 * (Blueprint) y solo el T-SQL de google_auth se traduce a MariaDB aquí.
 * Opt-in: `PLAYBOSS_TEST_STANDIN=mysql php vendor/bin/phpunit` (base `playboss_phpunit`; sin eso se omite).
 */
class AuthFlujoDbTest extends TestCase
{
    private const CLIENT = 'cliente-test.apps.googleusercontent.com';
    private $clave;
    private array $jwks;
    private MockInterface $audit;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('PLAYBOSS_TEST_STANDIN') !== 'mysql') {
            $this->markTestSkipped('Opt-in: PLAYBOSS_TEST_STANDIN=mysql usa la base `playboss_phpunit` (MySQL/MariaDB local) en lugar de sqlsrv.');
        }
        $base = config('database.connections.mysql');
        foreach (['pgsql', 'sqlsrv', 'mysql'] as $c) {
            config(["database.connections.$c" => ['database' => 'playboss_phpunit'] + $base]);
            DB::purge($c);
        }
        Schema::connection('sqlsrv')->dropAllTables();
        $this->crearEsquema();

        $this->audit = Mockery::mock(AuditLogService::class)->shouldIgnoreMissing();
        $this->app->instance(AuditLogService::class, $this->audit);
        $this->app->instance(SvcFotoPerfil::class, Mockery::mock(SvcFotoPerfil::class, ['guardar' => 'drive-id']));

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); // los topes por minuto no son lo que se prueba aquí
        Cache::flush();
        config([
            'services.google_auth.client_id' => self::CLIENT,
            'services.recaptcha.enabled' => false,
            'services.recaptcha.secret' => 'secreto-test',
            'mfa.enabled' => true,
        ]);
        $this->clave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $d = openssl_pkey_get_details($this->clave);
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $this->jwks = ['keys' => [['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'k1', 'n' => $b64($d['rsa']['n']), 'e' => $b64($d['rsa']['e'])]]];
        Http::fake(['www.googleapis.com/oauth2/v3/certs' => Http::response($this->jwks)]);
    }

    protected function tearDown(): void
    {
        if (getenv('PLAYBOSS_TEST_STANDIN') === 'mysql') {
            Schema::connection('sqlsrv')->dropAllTables();
        }
        parent::tearDown();
    }

    private function crearEsquema(): void
    {
        $dir = database_path('migrations/sqlsrv/');
        (require $dir . '2026_10_28_000001_create_identity_schema.php')->up();
        (require $dir . '2026_11_09_000001_add_foto_drive_id_to_usuario_perfil.php')->up();

        // 2026_11_09_000002_add_google_auth_to_usuario: T-SQL (índices filtrados, ALTER COLUMN) traducido a MariaDB.
        // En MariaDB un UNIQUE ya admite varios NULL, que es lo que el índice filtrado logra en SQL Server.
        $db = DB::connection('sqlsrv');
        $db->statement("ALTER TABLE usuario_credencial ADD google_sub VARCHAR(255) NULL, ADD auth_proveedor VARCHAR(20) NOT NULL DEFAULT 'LOCAL', MODIFY contrasena_hash VARCHAR(255) NULL, ADD UNIQUE uq_credencial_google_sub (google_sub)");
        $db->statement('ALTER TABLE usuario_perfil ADD foto_url VARCHAR(500) NULL, MODIFY primer_apellido VARCHAR(50) NULL, MODIFY tipo_documento_codigo VARCHAR(5) NULL, MODIFY numero_documento VARCHAR(20) NULL, MODIFY fecha_nacimiento DATE NULL');

        (require $dir . '2026_11_10_000001_create_usuario_mfa.php')->up();
        $db->table('estado_usuario')->insert(['codigo' => 'INA', 'nombre' => 'Inactivo']);
    }

    // ------------------------------------------------------------------ utilidades

    private function jwt(array $extra = []): string
    {
        return JWT::encode(array_merge([
            'iss' => 'https://accounts.google.com', 'aud' => self::CLIENT, 'sub' => '1122334455', 'email' => 'Ana@Example.com',
            'email_verified' => true, 'given_name' => 'Ana', 'family_name' => 'Lopez', 'picture' => 'https://lh3.googleusercontent.com/a/foto',
            'iat' => time() - 10, 'exp' => time() + 3600,
        ], $extra), $this->clave, 'RS256', 'k1');
    }

    /** El guard de auth cachea al usuario entre peticiones de una misma prueba (los controladores se reinician en TestCase::call). */
    private function reiniciar(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /** Petición sin estado compartido entre llamadas (el guard de auth cachea al usuario). */
    private function api(string $metodo, string $uri, array $datos = [], ?string $token = null, array $headers = [])
    {
        $this->reiniciar();
        if ($token) {
            $headers['Authorization'] = "Bearer $token";
        }

        return $this->withHeaders($headers)->json($metodo, $uri, $datos);
    }

    private function registrarLocal(string $correo = 'ana@example.com', string $doc = '1020304050', string $clave = 'Clave-segura-1'): int
    {
        $this->reiniciar();
        $r = $this->post('/api/auth/registro', [
            'primer_nombre' => 'Ana', 'primer_apellido' => 'Lopez', 'tipo_documento_codigo' => 'CC', 'numero_documento' => $doc,
            'correo' => $correo, 'fecha_nacimiento' => now()->subYears(30)->toDateString(), 'contrasena' => $clave, 'contrasena_confirmation' => $clave,
            'foto' => UploadedFile::fake()->image('f.jpg'),
        ], ['Accept' => 'application/json']);
        $r->assertStatus(201);

        return (int) $r->json('data.usuario.id_usuario');
    }

    private function usuarioDirecto(string $rol = 'CLIENTE', string $correo = 'admin@example.com', string $clave = 'Clave-segura-1'): int
    {
        $db = DB::connection('sqlsrv');
        $id = $db->table('usuario')->insertGetId(['rol_codigo' => $rol, 'estado_codigo' => 'ACT']);
        $db->table('usuario_credencial')->insert(['usuario_id' => $id, 'correo' => $correo, 'contrasena_hash' => Hash::make($clave)]);
        $db->table('usuario_perfil')->insert(['usuario_id' => $id, 'primer_nombre' => 'X', 'primer_apellido' => 'Y', 'tipo_documento_codigo' => 'CC', 'numero_documento' => (string) (900000 + $id), 'fecha_nacimiento' => '1990-01-01']);

        return $id;
    }

    private function recaptcha(array $respuesta): void
    {
        config(['services.recaptcha.enabled' => true, 'services.recaptcha.min_score' => 0.5]);
        Http::swap(new \Illuminate\Http\Client\Factory()); // Http::fake apila stubs: se parte de cero
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response($respuesta),
            'www.googleapis.com/oauth2/v3/certs' => Http::response($this->jwks),
        ]);
    }

    private function codigo(string $secreto): string
    {
        return Totp::codigo($secreto, now()->timestamp);
    }

    private function avanzarVentana(): void
    {
        $this->travelTo(now()->addSeconds(30));
    }

    /** Configura y activa el 2FA por HTTP con un token real. @return array{0:string,1:array,2:string} secreto, recovery, token */
    private function activarMfa(string $token): array
    {
        $secreto = $this->api('POST', '/api/auth/mfa/configurar', [], $token)->assertOk()->json('data.secreto');
        $r = $this->api('POST', '/api/auth/mfa/activar', ['codigo' => $this->codigo($secreto)], $token)->assertOk();
        $this->assertTrue($r->json('data.activo'));

        return [$secreto, $r->json('data.recuperacion_codigos'), $token];
    }

    private function loginGoogle(array $claims = [])
    {
        return $this->api('POST', '/api/auth/google', ['credential' => $this->jwt($claims)]);
    }

    // ------------------------------------------------------------------ (a) registro / login con reCAPTCHA

    public function test_registro_y_login_con_recaptcha_valido_escriben_en_las_tablas(): void
    {
        $this->recaptcha(['success' => true, 'score' => 0.9, 'action' => 'registro']);
        $id = $this->registrarLocalConRecaptcha();

        $db = DB::connection('sqlsrv');
        $cred = $db->table('usuario_credencial')->where('usuario_id', $id)->first();
        $this->assertTrue(Hash::check('Clave-segura-1', $cred->contrasena_hash));
        $this->assertNull($cred->google_sub);
        $this->assertSame('LOCAL', $cred->auth_proveedor);
        $this->assertSame('CLIENTE', $db->table('usuario')->where('id_usuario', $id)->value('rol_codigo'));
        $this->assertNotNull($db->table('usuario')->where('id_usuario', $id)->value('fecha_registro'));
        $this->assertSame(1, $db->table('personal_access_tokens')->where('tokenable_id', $id)->count());

        $this->recaptcha(['success' => true, 'score' => 0.9, 'action' => 'login']);
        $r = $this->api('POST', '/api/auth/login', ['correo' => 'ana@example.com', 'contrasena' => 'Clave-segura-1'], null, ['X-Recaptcha-Token' => 'tok'])->assertOk();
        $this->assertNotEmpty($r->json('data.token'));
        $this->assertTrue($r->json('data.usuario.perfil_completo'));
        $this->api('GET', '/api/auth/me', [], $r->json('data.token'))->assertOk()->assertJsonPath('data.usuario.correo', 'ana@example.com');
        Http::assertSent(fn ($req) => str_contains($req->url(), 'siteverify') && $req['response'] === 'tok' && $req['secret'] === 'secreto-test');
    }

    private function registrarLocalConRecaptcha(): int
    {
        $this->reiniciar();
        $r = $this->withHeaders(['Accept' => 'application/json', 'X-Recaptcha-Token' => 'tok'])->post('/api/auth/registro', [
            'primer_nombre' => 'Ana', 'primer_apellido' => 'Lopez', 'tipo_documento_codigo' => 'CC', 'numero_documento' => '1020304050',
            'correo' => 'ana@example.com', 'fecha_nacimiento' => now()->subYears(30)->toDateString(), 'contrasena' => 'Clave-segura-1', 'contrasena_confirmation' => 'Clave-segura-1',
            'foto' => UploadedFile::fake()->image('f.jpg'),
        ]);
        $r->assertStatus(201);

        return (int) $r->json('data.usuario.id_usuario');
    }

    public function test_login_con_recaptcha_ausente_score_bajo_o_accion_incorrecta_no_llega_a_la_base(): void
    {
        $this->registrarLocal();
        $cuerpo = ['correo' => 'ana@example.com', 'contrasena' => 'Clave-segura-1'];
        $tokens = fn () => DB::connection('sqlsrv')->table('personal_access_tokens')->count();
        $antes = $tokens();

        $this->recaptcha(['success' => true, 'score' => 0.9, 'action' => 'login']);
        $this->api('POST', '/api/auth/login', $cuerpo)->assertStatus(422)->assertJsonPath('mensaje.0', 'Falta la verificación reCAPTCHA.');

        $this->recaptcha(['success' => true, 'score' => 0.2, 'action' => 'login']);
        $this->api('POST', '/api/auth/login', $cuerpo, null, ['X-Recaptcha-Token' => 't'])->assertStatus(403);

        $this->recaptcha(['success' => true, 'score' => 0.9, 'action' => 'registro']);
        $this->api('POST', '/api/auth/login', $cuerpo, null, ['X-Recaptcha-Token' => 't'])->assertStatus(403);

        $this->recaptcha(['success' => false, 'error-codes' => ['timeout-or-duplicate']]);
        $this->api('POST', '/api/auth/login', $cuerpo, null, ['X-Recaptcha-Token' => 't'])->assertStatus(422);

        $this->assertSame($antes, $tokens()); // ningún intento emitió token

        $this->recaptcha(['success' => true, 'score' => 0.9, 'action' => 'login']);
        $this->api('POST', '/api/auth/login', $cuerpo + ['recaptcha_token' => 't'])->assertOk();
        $this->api('POST', '/api/auth/login', ['correo' => 'ana@example.com', 'contrasena' => 'mala', 'recaptcha_token' => 't'])->assertStatus(401);
    }

    // ------------------------------------------------------------------ (b) Google -> tablas de usuario

    public function test_google_usuario_nuevo_inserta_usuario_perfil_y_credencial_con_perfil_incompleto(): void
    {
        $this->audit->shouldReceive('registrarEvento')->with('google_account_created', Mockery::type('int'), Mockery::any())->once();
        $r = $this->loginGoogle()->assertStatus(201);

        $r->assertJsonPath('data.estado', 'perfil_incompleto')->assertJsonPath('data.perfil_incompleto', true)->assertJsonPath('data.nuevo', true);
        $this->assertNotEmpty($r->json('data.token'));
        $this->assertArrayNotHasKey('google_sub', $r->json('data.usuario'));
        $this->assertArrayNotHasKey('contrasena_hash', $r->json('data.usuario'));
        $this->assertSame('GOOGLE', $r->json('data.usuario.auth_proveedor'));

        $db = DB::connection('sqlsrv');
        $id = (int) $r->json('data.usuario.id_usuario');
        $u = $db->table('usuario')->where('id_usuario', $id)->first();
        $this->assertSame(['CLIENTE', 'ACT'], [$u->rol_codigo, $u->estado_codigo]);
        $this->assertNotNull($u->fecha_registro);

        $c = $db->table('usuario_credencial')->where('usuario_id', $id)->first();
        $this->assertSame('ana@example.com', $c->correo);
        $this->assertNull($c->contrasena_hash);
        $this->assertSame('1122334455', $c->google_sub);
        $this->assertSame('GOOGLE', $c->auth_proveedor);
        $this->assertNotNull($c->correo_verificado_en);

        $p = $db->table('usuario_perfil')->where('usuario_id', $id)->first();
        $this->assertSame(['Ana', 'Lopez', 'https://lh3.googleusercontent.com/a/foto'], [$p->primer_nombre, $p->primer_apellido, $p->foto_url]);
        foreach (['tipo_documento_codigo', 'numero_documento', 'fecha_nacimiento', 'celular', 'segundo_nombre', 'foto_drive_id'] as $campo) {
            $this->assertNull($p->$campo, $campo);
        }
        $this->assertSame(1, $db->table('personal_access_tokens')->where('tokenable_id', $id)->count());

        // El token emitido funciona contra la base real.
        $this->api('GET', '/api/auth/me', [], $r->json('data.token'))->assertOk()->assertJsonPath('data.usuario.perfil_completo', false);
    }

    public function test_google_usuario_sin_apellido_ni_foto_se_crea_igual(): void
    {
        $r = $this->loginGoogle(['sub' => '77', 'email' => 'solo@example.com', 'family_name' => null, 'picture' => null, 'given_name' => null, 'name' => 'Solo Nombre'])->assertStatus(201);
        $p = DB::connection('sqlsrv')->table('usuario_perfil')->where('usuario_id', $r->json('data.usuario.id_usuario'))->first();
        $this->assertSame('Solo Nombre', $p->primer_nombre);
        $this->assertNull($p->primer_apellido);
        $this->assertNull($p->foto_url);
    }

    public function test_google_segundo_login_del_mismo_sub_no_duplica(): void
    {
        $id = $this->loginGoogle()->assertStatus(201)->json('data.usuario.id_usuario');
        Cache::flush();
        $r = $this->loginGoogle()->assertOk();
        $this->assertSame($id, $r->json('data.usuario.id_usuario'));
        $this->assertFalse($r->json('data.nuevo'));

        $db = DB::connection('sqlsrv');
        $this->assertSame(1, $db->table('usuario')->count());
        $this->assertSame(1, $db->table('usuario_credencial')->count());
        $this->assertSame(1, $db->table('usuario_perfil')->count());
        $this->assertSame(2, $db->table('personal_access_tokens')->count());

        // Dos cuentas de Google distintas conviven (varios NULL en contraseña, sub distinto).
        $this->loginGoogle(['sub' => '999', 'email' => 'otra@example.com'])->assertStatus(201);
        $this->assertSame(2, $db->table('usuario_perfil')->whereNull('numero_documento')->count());
    }

    public function test_google_con_correo_de_usuario_existente_vincula_google_sub_sin_tocar_la_contrasena(): void
    {
        $id = $this->registrarLocal();
        $hash = DB::connection('sqlsrv')->table('usuario_credencial')->where('usuario_id', $id)->value('contrasena_hash');

        $r = $this->loginGoogle()->assertOk();
        $this->assertSame($id, $r->json('data.usuario.id_usuario'));
        $this->assertFalse($r->json('data.nuevo'));
        $this->assertFalse($r->json('data.perfil_incompleto')); // ya tenía perfil completo

        $db = DB::connection('sqlsrv');
        $c = $db->table('usuario_credencial')->where('usuario_id', $id)->first();
        $this->assertSame('1122334455', $c->google_sub);
        $this->assertSame($hash, $c->contrasena_hash);
        $this->assertNotNull($c->correo_verificado_en);
        $this->assertSame(1, $db->table('usuario')->count());

        // Y sigue pudiendo entrar con contraseña.
        $this->api('POST', '/api/auth/login', ['correo' => 'ana@example.com', 'contrasena' => 'Clave-segura-1'])->assertOk();
    }

    public function test_google_rechaza_correo_no_verificado_cuenta_ligada_a_otro_sub_e_inactiva(): void
    {
        $this->loginGoogle(['email_verified' => false])->assertStatus(403);
        $this->assertSame(0, DB::connection('sqlsrv')->table('usuario')->count());

        $this->loginGoogle()->assertStatus(201); // liga ana@example.com al sub 1122334455
        $this->loginGoogle(['sub' => '55555'])->assertStatus(409); // mismo correo, otra identidad de Google

        DB::connection('sqlsrv')->table('usuario')->update(['estado_codigo' => 'INA']);
        $this->loginGoogle()->assertStatus(401);
    }

    public function test_google_completar_perfil_y_bloqueo_de_menores(): void
    {
        $token = $this->loginGoogle()->json('data.token');
        $id = (int) $this->api('GET', '/api/auth/me', [], $token)->json('data.usuario.id_usuario');
        $db = DB::connection('sqlsrv');
        $datos = ['primer_apellido' => 'Lopez', 'tipo_documento_codigo' => 'CC', 'numero_documento' => '1020304050', 'fecha_nacimiento' => now()->subYears(30)->toDateString()];

        $this->api('PUT', '/api/auth/google/completar', [], $token)->assertStatus(422);
        $this->api('PUT', '/api/auth/google/completar', ['fecha_nacimiento' => now()->subYears(15)->toDateString()] + $datos, $token)->assertStatus(422)->assertJsonValidationErrorFor('fecha_nacimiento', 'data.errores');
        $this->assertNull($db->table('usuario_perfil')->where('usuario_id', $id)->value('numero_documento')); // el menor no se guardó

        // Documento ya usado por otro usuario.
        $otro = $this->usuarioDirecto('CLIENTE', 'otro@example.com');
        $db->table('usuario_perfil')->where('usuario_id', $otro)->update(['numero_documento' => '1020304050']);
        $this->api('PUT', '/api/auth/google/completar', $datos, $token)->assertStatus(422)->assertJsonValidationErrorFor('numero_documento', 'data.errores');
        $db->table('usuario_perfil')->where('usuario_id', $otro)->update(['numero_documento' => '900001']);

        $this->api('PUT', '/api/auth/google/completar', $datos, $token)->assertOk()->assertJsonPath('data.perfil_incompleto', false);
        $p = $db->table('usuario_perfil')->where('usuario_id', $id)->first();
        $this->assertSame(['CC', '1020304050'], [$p->tipo_documento_codigo, $p->numero_documento]);
        $this->assertSame(now()->subYears(30)->toDateString(), substr((string) $p->fecha_nacimiento, 0, 10));
        $this->api('GET', '/api/auth/me', [], $token)->assertJsonPath('data.usuario.perfil_completo', true);
        $this->api('PUT', '/api/auth/google/completar', $datos, $token)->assertStatus(422);

        // Siguiente login con Google ya no es incompleto.
        Cache::flush();
        $this->loginGoogle()->assertOk()->assertJsonPath('data.estado', 'ok');
    }

    public function test_perfil_incompleto_o_menor_bloquea_depositar_y_apostar(): void
    {
        $token = $this->loginGoogle()->json('data.token');
        $id = (int) $this->api('GET', '/api/auth/me', [], $token)->json('data.usuario.id_usuario');

        $this->api('POST', '/api/pagos/depositos', ['monto' => '50000'], $token)->assertStatus(403)->assertJsonPath('data.perfil_incompleto', true);

        // BetService::crear llama exactamente a este guard antes de tocar saldo o apuesta.
        $this->assertBloqueado($id, fn ($e) => $this->assertTrue($e->errors['perfil_incompleto'] ?? false));

        // Perfil con datos pero menor de edad (p. ej. alterado en base): sigue bloqueado.
        DB::connection('sqlsrv')->table('usuario_perfil')->where('usuario_id', $id)->update(['tipo_documento_codigo' => 'TI', 'numero_documento' => '123', 'fecha_nacimiento' => now()->subYears(15)->toDateString()]);
        $this->api('POST', '/api/pagos/depositos', ['monto' => '50000'], $token)->assertStatus(403)->assertJsonPath('mensaje.0', 'Debes ser mayor de edad para realizar esta operación.');
        $this->assertBloqueado($id);

        DB::connection('sqlsrv')->table('usuario_perfil')->where('usuario_id', $id)->update(['fecha_nacimiento' => now()->subYears(30)->toDateString()]);
        \App\Service\Auth\PerfilApostador::exigir($id);
        $this->assertTrue(true);
    }

    private function assertBloqueado(int $id, ?callable $extra = null): void
    {
        try {
            \App\Service\Auth\PerfilApostador::exigir($id);
            $this->fail('Debió bloquear');
        } catch (\App\Exceptions\ReglaNegocioException $e) {
            $this->assertSame(403, $e->status);
            $extra && $extra($e);
        }
    }

    // ------------------------------------------------------------------ (c) 2FA con base real

    public function test_mfa_configurar_activar_y_login_con_reto(): void
    {
        $id = $this->registrarLocal();
        $token = $this->api('POST', '/api/auth/login', ['correo' => 'ana@example.com', 'contrasena' => 'Clave-segura-1'])->json('data.token');

        $this->api('POST', '/api/auth/mfa/activar', ['codigo' => '123456'], $token)->assertStatus(409); // sin configurar
        $secreto = $this->api('POST', '/api/auth/mfa/configurar', [], $token)->assertOk()->json('data.secreto');
        $db = DB::connection('sqlsrv');
        $fila = $db->table('usuario_mfa')->where('usuario_id', $id)->first();
        $this->assertNull($fila->confirmado_en);
        $this->assertNotSame($secreto, $fila->secreto_cifrado); // cifrado en reposo
        $this->assertSame($secreto, \Illuminate\Support\Facades\Crypt::decryptString($fila->secreto_cifrado));

        // Pendiente: el login todavía entrega token directo.
        $this->api('POST', '/api/auth/login', ['correo' => 'ana@example.com', 'contrasena' => 'Clave-segura-1'])->assertOk()->assertJsonStructure(['data' => ['token']]);

        $this->api('POST', '/api/auth/mfa/activar', ['codigo' => '000000'], $token)->assertStatus(422);
        $r = $this->api('POST', '/api/auth/mfa/activar', ['codigo' => $this->codigo($secreto)], $token)->assertOk();
        $this->assertCount(10, $r->json('data.recuperacion_codigos'));
        $fila = $db->table('usuario_mfa')->where('usuario_id', $id)->first();
        $this->assertNotNull($fila->confirmado_en);
        $this->assertNotNull($fila->ultimo_contador);
        $rec = $db->table('usuario_mfa_recuperacion')->where('usuario_id', $id)->get();
        $this->assertCount(10, $rec);
        $this->assertNotContains(str_replace('-', '', $r->json('data.recuperacion_codigos')[0]), $rec->pluck('codigo_hash')->all()); // solo hash
        $this->assertSame(1, $db->table('personal_access_tokens')->where('tokenable_id', $id)->count()); // sesiones previas revocadas salvo la actual

        // Login: ahora solo entrega reto, sin token ni fila nueva en personal_access_tokens.
        $this->avanzarVentana();
        $login = $this->api('POST', '/api/auth/login', ['correo' => 'ana@example.com', 'contrasena' => 'Clave-segura-1'])->assertOk();
        $this->assertTrue($login->json('data.mfa_required'));
        $this->assertNull($login->json('data.token'));
        $this->assertSame(1, $db->table('personal_access_tokens')->where('tokenable_id', $id)->count());
        $reto = $login->json('data.mfa_token');

        // El reto no sirve como Bearer.
        $this->api('GET', '/api/auth/me', [], $reto)->assertStatus(401);
        $this->api('POST', '/api/auth/mfa/verificar', ['mfa_token' => $reto, 'codigo' => '000000'])->assertStatus(401);
        $ok = $this->api('POST', '/api/auth/mfa/verificar', ['mfa_token' => $reto, 'codigo' => $this->codigo($secreto)])->assertOk();
        $this->assertSame('totp', $ok->json('data.factor'));
        $this->api('GET', '/api/auth/me', [], $ok->json('data.token'))->assertOk()->assertJsonPath('data.usuario.mfa_activo', true);
        // El reto es de un solo uso.
        $this->api('POST', '/api/auth/mfa/verificar', ['mfa_token' => $reto, 'codigo' => $this->codigo($secreto)])->assertStatus(401);
    }

    public function test_mfa_codigo_de_recuperacion_de_un_solo_uso_y_anti_reutilizacion_totp(): void
    {
        $id = $this->registrarLocal();
        $token = $this->api('POST', '/api/auth/login', ['correo' => 'ana@example.com', 'contrasena' => 'Clave-segura-1'])->json('data.token');
        [$secreto, $codigos] = $this->activarMfa($token);
        $db = DB::connection('sqlsrv');
        $credenciales = ['correo' => 'ana@example.com', 'contrasena' => 'Clave-segura-1'];

        // Recovery code: sirve una vez.
        $reto = $this->api('POST', '/api/auth/login', $credenciales)->json('data.mfa_token');
        $r = $this->api('POST', '/api/auth/mfa/verificar', ['mfa_token' => $reto, 'codigo_recuperacion' => strtolower($codigos[0])])->assertOk();
        $this->assertSame('recuperacion', $r->json('data.factor'));
        $this->assertSame(9, $r->json('data.recuperacion_restantes'));
        $this->assertSame(1, $db->table('usuario_mfa_recuperacion')->where('usuario_id', $id)->whereNotNull('usado_en')->count());

        $reto = $this->api('POST', '/api/auth/login', $credenciales)->json('data.mfa_token');
        $this->api('POST', '/api/auth/mfa/verificar', ['mfa_token' => $reto, 'codigo_recuperacion' => $codigos[0]])->assertStatus(401);
        $this->api('POST', '/api/auth/mfa/verificar', ['mfa_token' => $reto, 'codigo_recuperacion' => $codigos[1]])->assertOk();
        $this->assertSame(8, $db->table('usuario_mfa_recuperacion')->where('usuario_id', $id)->whereNull('usado_en')->count());

        // Anti-reutilización: el código de la activación (ventana actual) ya está gastado.
        $reto = $this->api('POST', '/api/auth/login', $credenciales)->json('data.mfa_token');
        $this->api('POST', '/api/auth/mfa/verificar', ['mfa_token' => $reto, 'codigo' => $this->codigo($secreto)])->assertStatus(401);

        $this->avanzarVentana();
        $reto = $this->api('POST', '/api/auth/login', $credenciales)->json('data.mfa_token');
        $c = $this->codigo($secreto);
        $this->api('POST', '/api/auth/mfa/verificar', ['mfa_token' => $reto, 'codigo' => $c])->assertOk();
        $reto = $this->api('POST', '/api/auth/login', $credenciales)->json('data.mfa_token');
        $this->api('POST', '/api/auth/mfa/verificar', ['mfa_token' => $reto, 'codigo' => $c])->assertStatus(401); // mismo código otra vez
    }

    public function test_mfa_bloquea_tras_cinco_fallos(): void
    {
        $this->registrarLocal();
        $token = $this->api('POST', '/api/auth/login', ['correo' => 'ana@example.com', 'contrasena' => 'Clave-segura-1'])->json('data.token');
        [$secreto] = $this->activarMfa($token);
        $this->avanzarVentana();
        $credenciales = ['correo' => 'ana@example.com', 'contrasena' => 'Clave-segura-1'];

        $reto = $this->api('POST', '/api/auth/login', $credenciales)->json('data.mfa_token');
        for ($i = 0; $i < 5; $i++) {
            $this->api('POST', '/api/auth/mfa/verificar', ['mfa_token' => $reto, 'codigo' => '000000'])->assertStatus(401);
        }
        // El reto se quemó y, aun con un reto nuevo y el código correcto, el usuario+IP queda bloqueado.
        $this->api('POST', '/api/auth/mfa/verificar', ['mfa_token' => $reto, 'codigo' => $this->codigo($secreto)])->assertStatus(401);
        $reto2 = $this->api('POST', '/api/auth/login', $credenciales)->json('data.mfa_token');
        $this->api('POST', '/api/auth/mfa/verificar', ['mfa_token' => $reto2, 'codigo' => $this->codigo($secreto)])->assertStatus(429);
        $this->assertSame(1, DB::connection('sqlsrv')->table('personal_access_tokens')->count()); // nunca se emitió otro token

        // Pasado el bloqueo (5 min) vuelve a funcionar.
        $this->travelTo(now()->addSeconds(301));
        $reto3 = $this->api('POST', '/api/auth/login', $credenciales)->json('data.mfa_token');
        $this->api('POST', '/api/auth/mfa/verificar', ['mfa_token' => $reto3, 'codigo' => $this->codigo($secreto)])->assertOk();
    }

    public function test_admin_sin_mfa_queda_bloqueado_y_con_mfa_pasa(): void
    {
        $this->usuarioDirecto('ADMIN');
        $r = $this->api('POST', '/api/auth/login', ['correo' => 'admin@example.com', 'contrasena' => 'Clave-segura-1'])->assertOk();
        $this->assertNotEmpty($r->json('data.token')); // aún sin 2FA puede entrar a configurarlo
        $token = $r->json('data.token');
        $this->assertTrue($r->json('data.usuario.mfa_obligatorio'));

        $this->api('POST', '/api/admin/usuarios/99/mfa/restablecer', ['motivo' => 'prueba de bloqueo'], $token)->assertStatus(403)->assertJsonPath('data.mfa_requerido', true);
        $this->api('POST', '/api/mfa-inexistente', [], $token)->assertStatus(404);

        $this->activarMfa($token);
        $this->api('POST', '/api/admin/usuarios/99/mfa/restablecer', ['motivo' => 'prueba de acceso permitido'], $token)->assertStatus(404); // pasó el middleware; usuario 99 no existe

        // Un administrador no puede desactivar su 2FA.
        $this->avanzarVentana();
        $this->api('POST', '/api/auth/mfa/desactivar', ['contrasena' => 'Clave-segura-1', 'codigo' => '000000'], $token)->assertStatus(403);
    }

    public function test_google_con_mfa_activo_devuelve_reto_y_no_token(): void
    {
        $token = $this->loginGoogle()->json('data.token');
        $id = (int) $this->api('GET', '/api/auth/me', [], $token)->json('data.usuario.id_usuario');
        [$secreto] = $this->activarMfa($token);
        $db = DB::connection('sqlsrv');
        $this->assertSame(1, $db->table('personal_access_tokens')->where('tokenable_id', $id)->count());
        $this->avanzarVentana();

        $r = $this->loginGoogle()->assertOk();
        $this->assertTrue($r->json('data.mfa_required'));
        $this->assertNull($r->json('data.token'));
        $this->assertArrayNotHasKey('usuario', $r->json('data'));
        $this->assertSame(1, $db->table('personal_access_tokens')->where('tokenable_id', $id)->count());
        $this->assertSame(1, $db->table('usuario')->count());

        $ok = $this->api('POST', '/api/auth/mfa/verificar', ['mfa_token' => $r->json('data.mfa_token'), 'codigo' => $this->codigo($secreto)])->assertOk();
        $this->assertSame('perfil_incompleto', $ok->json('data.estado'));
        $this->assertFalse($ok->json('data.nuevo'));
        $this->api('GET', '/api/auth/me', [], $ok->json('data.token'))->assertOk();

        // Desactivar con 2FA en cuenta solo-Google reautentica con un id_token de Google.
        $this->avanzarVentana();
        $tk = $ok->json('data.token');
        $this->api('POST', '/api/auth/mfa/desactivar', ['codigo' => $this->codigo($secreto)], $tk)->assertStatus(422);
        $this->avanzarVentana();
        $this->api('POST', '/api/auth/mfa/desactivar', ['codigo' => $this->codigo($secreto), 'credential' => $this->jwt()], $tk)->assertOk()->assertJsonPath('data.activo', false);
        $this->assertSame(0, $db->table('usuario_mfa')->count());
        $this->assertSame(0, $db->table('usuario_mfa_recuperacion')->count());
    }

    public function test_el_esquema_mfa_cascada_al_borrar_el_usuario(): void
    {
        $id = $this->registrarLocal();
        $token = $this->api('POST', '/api/auth/login', ['correo' => 'ana@example.com', 'contrasena' => 'Clave-segura-1'])->json('data.token');
        $this->activarMfa($token);
        DB::connection('sqlsrv')->table('usuario')->where('id_usuario', $id)->delete();
        foreach (['usuario_mfa', 'usuario_mfa_recuperacion', 'usuario_credencial', 'usuario_perfil'] as $t) {
            $this->assertSame(0, DB::connection('sqlsrv')->table($t)->count(), $t);
        }
        $this->assertNull(PerfilUsuario::query()->find($id));
    }
}
