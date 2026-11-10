<?php

namespace Tests\Feature;

use App\Exceptions\ReglaNegocioException;
use App\Models\Account\Cuenta;
use App\Models\Account\Movimiento;
use App\Models\Bet\Apuesta;
use App\Service\Account\LimiteJuegoService;
use App\Service\Payment\RetiroService;
use App\Service\Storage\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * Retiros, límites de juego y admin de usuarios contra una base relacional real
 * (pgsql y sqlsrv apuntan a una base MySQL temporal; se ejecuta la migración real de pgsql).
 * Opt-in: `PLAYBOSS_TEST_STANDIN=mysql php vendor/bin/phpunit` (requiere la base `playboss_phpunit`; sin eso se omite).
 */
class RetirosLimitesDbTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('PLAYBOSS_TEST_STANDIN') !== 'mysql') {
            $this->markTestSkipped('Opt-in: PLAYBOSS_TEST_STANDIN=mysql usa la base `playboss_phpunit` (MySQL/MariaDB local) en lugar de pgsql/sqlsrv.');
        }
        $base = config('database.connections.mysql');
        foreach (['pgsql', 'sqlsrv', 'mysql'] as $c) {
            config(["database.connections.$c" => ['database' => 'playboss_phpunit'] + $base]);
            DB::purge($c);
        }
        Schema::connection('pgsql')->dropAllTables();
        $this->app->instance(AuditLogService::class, Mockery::mock(AuditLogService::class)->shouldIgnoreMissing());
        $this->crearEsquema();
        $this->cuenta(7, '100000.00');
        DB::connection('sqlsrv')->table('usuario')->insert(['id_usuario' => 7, 'rol_codigo' => 'CLIENTE', 'estado_codigo' => 'ACT', 'fecha_registro' => now()]);
        DB::connection('sqlsrv')->table('usuario_perfil')->insert(['usuario_id' => 7, 'primer_nombre' => 'Laura', 'primer_apellido' => 'Gómez', 'tipo_documento_codigo' => 'CC', 'numero_documento' => '1020304050', 'fecha_nacimiento' => now()->subYears(30)->toDateString()]);
        DB::connection('sqlsrv')->table('usuario_credencial')->insert(['usuario_id' => 7, 'correo' => 'laura@example.com', 'contrasena_hash' => 'hash-secreto']);
    }

    protected function tearDown(): void
    {
        if (getenv('PLAYBOSS_TEST_STANDIN') === 'mysql') {
            Schema::connection('pgsql')->dropAllTables();
        }
        parent::tearDown();
    }

    private function crearEsquema(): void
    {
        $pg = Schema::connection('pgsql');
        $pg->create('cuenta', function ($t) {
            $t->bigInteger('usuario_id')->primary();
            $t->decimal('saldo_disponible', 14, 2)->default(0);
            $t->decimal('saldo_retenido', 14, 2)->default(0);
            $t->string('moneda', 3)->default('COP');
        });
        $pg->create('movimiento', function ($t) {
            $t->bigIncrements('id_movimiento');
            $t->bigInteger('usuario_id');
            $t->string('tipo', 30);
            $t->decimal('monto', 14, 2);
            $t->decimal('saldo_disponible_resultante', 14, 2);
            $t->decimal('saldo_retenido_resultante', 14, 2);
            $t->string('referencia_idempotencia', 120)->unique();
            $t->unsignedBigInteger('transaccion_id')->nullable();
            $t->unsignedBigInteger('apuesta_id')->nullable();
            $t->string('descripcion', 200)->nullable();
            $t->timestamp('creado_en')->nullable();
        });
        $pg->create('apuesta', function ($t) {
            $t->bigIncrements('id_apuesta');
            $t->bigInteger('usuario_id');
            $t->decimal('monto', 14, 2);
            $t->string('estado_codigo', 5);
            $t->timestamp('creada_en')->nullable();
        });
        $pg->create('transaccion', function ($t) {
            $t->bigIncrements('id_transaccion');
            $t->bigInteger('usuario_id');
            $t->string('tipo', 20);
            $t->decimal('monto', 14, 2);
            $t->string('estado', 20);
            $t->timestamp('fecha_hora')->nullable();
        });
        (require database_path('migrations/pgsql/2026_11_10_000001_create_retiro_and_limite_juego.php'))->up();

        $ss = Schema::connection('sqlsrv');
        $ss->create('usuario', function ($t) {
            $t->bigInteger('id_usuario')->primary();
            $t->string('rol_codigo');
            $t->string('estado_codigo');
            $t->timestamp('fecha_registro')->nullable();
        });
        $ss->create('usuario_perfil', function ($t) {
            $t->bigInteger('usuario_id')->primary();
            $t->string('primer_nombre')->nullable();
            $t->string('segundo_nombre')->nullable();
            $t->string('primer_apellido')->nullable();
            $t->string('segundo_apellido')->nullable();
            $t->string('tipo_documento_codigo')->nullable();
            $t->string('numero_documento')->nullable();
            $t->date('fecha_nacimiento')->nullable();
        });
        $ss->create('usuario_credencial', function ($t) {
            $t->bigInteger('usuario_id')->primary();
            $t->string('correo');
            $t->string('contrasena_hash');
        });
        $ss->create('estado_usuario', function ($t) {
            $t->string('codigo')->primary();
            $t->string('nombre');
            $t->string('descripcion')->nullable();
        });
        $ss->create('personal_access_tokens', function ($t) {
            $t->id();
            $t->morphs('tokenable');
            $t->text('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
    }

    private function cuenta(int $id, string $disponible, string $retenido = '0.00'): void
    {
        Cuenta::query()->create(['usuario_id' => $id, 'saldo_disponible' => $disponible, 'saldo_retenido' => $retenido, 'moneda' => 'COP']);
    }

    private function destino(string $documento = '1020304050'): array
    {
        return ['banco' => 'Bancolombia', 'tipo_cuenta' => 'AHORROS', 'numero_cuenta' => '12345677890', 'titular' => 'Laura Gómez', 'documento_titular' => $documento];
    }

    private function saldos(): array
    {
        $c = Cuenta::query()->find(7);

        return [(string) $c->saldo_disponible, (string) $c->saldo_retenido];
    }

    public function test_solicitar_retiro_reserva_saldo_y_es_idempotente(): void
    {
        $svc = app(RetiroService::class);
        $retiro = $svc->solicitar(7, '40000', $this->destino(), 'clave-retiro-1');

        $this->assertSame('PENDIENTE', $retiro->estado);
        $this->assertSame(['60000.00', '40000.00'], $this->saldos());
        $this->assertSame('RETIRO_RESERVADO', Movimiento::query()->first()->tipo);

        $otra = $svc->solicitar(7, '40000', $this->destino(), 'clave-retiro-1');
        $this->assertSame($retiro->id_retiro, $otra->id_retiro);
        $this->assertSame(['60000.00', '40000.00'], $this->saldos()); // no se reserva dos veces
        $this->assertSame(1, Movimiento::query()->count());
    }

    public function test_retiro_valida_monto_saldo_documento_y_clave_ajena(): void
    {
        $svc = app(RetiroService::class);
        $casos = [
            'monto bajo' => ['5000', $this->destino()],
            'monto alto' => ['9000000', $this->destino()],
            'saldo insuficiente' => ['200000', $this->destino()],
            'documento ajeno' => ['20000', $this->destino('999999')],
        ];
        foreach ($casos as $nombre => [$monto, $destino]) {
            try {
                $svc->solicitar(7, $monto, $destino, 'clave-' . md5($nombre));
                $this->fail("Debió rechazar: $nombre");
            } catch (ValidationException) {
                $this->assertSame(['100000.00', '0.00'], $this->saldos(), $nombre);
            }
        }

        $svc->solicitar(7, '20000', $this->destino(), 'clave-compartida');
        $this->cuenta(8, '50000.00');
        $this->expectException(ReglaNegocioException::class);
        $svc->solicitar(8, '20000', $this->destino(), 'clave-compartida');
    }

    public function test_usuario_bloqueado_o_menor_no_puede_retirar(): void
    {
        DB::connection('sqlsrv')->table('usuario')->where('id_usuario', 7)->update(['estado_codigo' => 'BLQ']);
        try {
            app(RetiroService::class)->solicitar(7, '20000', $this->destino(), 'clave-bloqueado');
            $this->fail('Debió rechazar al usuario bloqueado');
        } catch (ReglaNegocioException $e) {
            $this->assertSame(403, $e->status);
        }

        DB::connection('sqlsrv')->table('usuario')->where('id_usuario', 7)->update(['estado_codigo' => 'ACT']);
        DB::connection('sqlsrv')->table('usuario_perfil')->where('usuario_id', 7)->update(['fecha_nacimiento' => now()->subYears(15)->toDateString()]);
        $this->expectException(ValidationException::class);
        app(RetiroService::class)->solicitar(7, '20000', $this->destino(), 'clave-menor-edad');
    }

    public function test_aprobar_consume_retenido_y_rechazar_libera_saldo(): void
    {
        $svc = app(RetiroService::class);
        $a = $svc->solicitar(7, '30000', $this->destino(), 'clave-aprobar-1');
        $b = $svc->solicitar(7, '20000', $this->destino(), 'clave-rechazar-1');
        $this->assertSame(['50000.00', '50000.00'], $this->saldos());

        $this->assertSame('PAGADO', $svc->aprobar(1, $a->id_retiro)->estado);
        $this->assertSame(['50000.00', '20000.00'], $this->saldos());
        $svc->aprobar(1, $a->id_retiro); // idempotente
        $this->assertSame(['50000.00', '20000.00'], $this->saldos());

        $rechazado = $svc->rechazar(1, $b->id_retiro, 'Datos inválidos');
        $this->assertSame('RECHAZADO', $rechazado->estado);
        $this->assertSame('Datos inválidos', $rechazado->motivo_rechazo);
        $this->assertSame(['70000.00', '0.00'], $this->saldos());
        $svc->rechazar(1, $b->id_retiro, 'Datos inválidos'); // idempotente
        $this->assertSame(['70000.00', '0.00'], $this->saldos());

        foreach ([fn () => $svc->rechazar(1, $a->id_retiro, 'x'), fn () => $svc->aprobar(1, $b->id_retiro)] as $contradictorio) {
            try {
                $contradictorio();
                $this->fail('Debió responder 409');
            } catch (ReglaNegocioException $e) {
                $this->assertSame(409, $e->status);
            }
        }
        $this->expectException(ReglaNegocioException::class);
        $svc->aprobar(1, 999);
    }

    public function test_maximo_de_pendientes(): void
    {
        config(['playboss.withdrawals.max_pending' => 2]);
        $svc = app(RetiroService::class);
        $svc->solicitar(7, '10000', $this->destino(), 'clave-pend-0001');
        $svc->solicitar(7, '10000', $this->destino(), 'clave-pend-0002');
        $this->expectException(ValidationException::class);
        $svc->solicitar(7, '10000', $this->destino(), 'clave-pend-0003');
    }

    public function test_limite_de_apuesta_diario_y_mensual(): void
    {
        $svc = app(LimiteJuegoService::class);
        $svc->actualizar(7, ['apuesta_diario' => '50000', 'apuesta_mensual' => '80000']);
        Apuesta::query()->create(['usuario_id' => 7, 'monto' => '30000.00', 'estado_codigo' => 'PEND', 'creada_en' => now()]);
        Apuesta::query()->create(['usuario_id' => 7, 'monto' => '90000.00', 'estado_codigo' => 'ANU', 'creada_en' => now()]); // anulada no cuenta

        $svc->validarApuesta(7, 20000_00); // 30k + 20k = 50k, justo en el tope
        try {
            $svc->validarApuesta(7, 20001_00);
            $this->fail('Debió superar el límite diario');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('diario', $e->errors()['monto'][0]);
        }

        $this->assertSame('20000.00', $svc->consumo(7)['apuesta_diario']['disponible']);
    }

    public function test_limite_de_deposito_cuenta_aprobados_y_pendientes_recientes(): void
    {
        $svc = app(LimiteJuegoService::class);
        $svc->actualizar(7, ['deposito_diario' => '100000']);
        DB::connection('pgsql')->table('transaccion')->insert([
            ['usuario_id' => 7, 'tipo' => 'DEPOSITO', 'monto' => '60000.00', 'estado' => 'APROBADA', 'fecha_hora' => now()],
            ['usuario_id' => 7, 'tipo' => 'DEPOSITO', 'monto' => '500000.00', 'estado' => 'RECHAZADA', 'fecha_hora' => now()],
            ['usuario_id' => 7, 'tipo' => 'DEPOSITO', 'monto' => '500000.00', 'estado' => 'PENDIENTE', 'fecha_hora' => now()->subHours(3)],
        ]);

        $svc->validarDeposito(7, 40000_00);
        $this->expectException(ValidationException::class);
        $svc->validarDeposito(7, 40001_00);
    }

    public function test_limites_validan_escalera_y_permiten_quitar(): void
    {
        $svc = app(LimiteJuegoService::class);
        try {
            $svc->actualizar(7, ['apuesta_diario' => '90000', 'apuesta_semanal' => '50000']);
            $this->fail('semanal < diario debe fallar');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('apuesta_semanal', $e->errors());
        }
        $svc->actualizar(7, ['apuesta_diario' => '90000']);
        $svc->actualizar(7, ['apuesta_diario' => null]);
        $this->assertNull($svc->obtener(7)->apuesta_diario);
    }

    public function test_autoexclusion_bloquea_y_no_se_acorta(): void
    {
        $svc = app(LimiteJuegoService::class);
        $svc->actualizar(7, ['autoexclusion_dias' => 30]);

        foreach ([fn () => $svc->validarApuesta(7, 1000_00), fn () => $svc->validarDeposito(7, 1000_00)] as $accion) {
            try {
                $accion();
                $this->fail('Autoexcluido no puede apostar ni depositar');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('autoexcluido', $e->errors()['usuario'][0]);
            }
        }

        $this->expectException(ValidationException::class);
        $svc->actualizar(7, ['autoexclusion_dias' => 7]);
    }

    public function test_api_limites_y_retiros_extremo_a_extremo(): void
    {
        $u = \App\Models\Usuario\Usuario::query()->find(7);
        Sanctum::actingAs($u);

        $this->putJson('/api/perfil/limites', ['apuesta_diario' => 50000])->assertOk()->assertJsonPath('data.limites.apuesta_diario', '50000.00');
        $this->getJson('/api/perfil/limites')->assertOk()->assertJsonPath('data.autoexclusion.activa', false);

        $r = $this->postJson('/api/pagos/retiros', $this->destino() + ['monto' => '30000', 'idempotency_key' => 'clave-http-0001']);
        $r->assertStatus(201)->assertJsonPath('data.estado', 'PENDIENTE')->assertJsonPath('data.destino.numero_cuenta', '****7890');
        $this->postJson('/api/pagos/retiros', $this->destino() + ['monto' => '30000', 'idempotency_key' => 'clave-http-0001'])->assertStatus(200);
        $this->getJson('/api/pagos/retiros')->assertOk()->assertJsonPath('data.meta.total', 1);
        $this->getJson('/api/cuenta/movimientos')->assertOk();
    }

    public function test_admin_aprueba_rechaza_y_gestiona_usuarios(): void
    {
        DB::connection('sqlsrv')->table('usuario')->insert(['id_usuario' => 1, 'rol_codigo' => 'ADMIN', 'estado_codigo' => 'ACT', 'fecha_registro' => now()]);
        $retiro = app(RetiroService::class)->solicitar(7, '30000', $this->destino(), 'clave-admin-0001');
        Sanctum::actingAs(\App\Models\Usuario\Usuario::query()->find(1));

        $this->getJson('/api/admin/retiros?estado=PENDIENTE')->assertOk()
            ->assertJsonPath('data.items.0.destino.numero_cuenta', '12345677890')->assertJsonPath('data.items.0.usuario_id', 7);
        $this->postJson("/api/admin/retiros/{$retiro->id_retiro}/rechazar", ['motivo' => 'Cuenta inválida'])->assertOk()->assertJsonPath('data.estado', 'RECHAZADO');
        $this->postJson("/api/admin/retiros/{$retiro->id_retiro}/aprobar")->assertStatus(409);
        $this->postJson('/api/admin/retiros/999/aprobar')->assertStatus(404);

        $lista = $this->getJson('/api/admin/usuarios?q=laura')->assertOk();
        $lista->assertJsonPath('data.items.0.correo', 'laura@example.com')->assertJsonPath('data.items.0.numero_documento', '*******050');
        $this->assertStringNotContainsString('hash-secreto', $lista->getContent());
        $this->assertStringNotContainsString('contrasena', $lista->getContent());
        $this->getJson('/api/admin/usuarios?estado=BLQ')->assertOk()->assertJsonPath('data.meta.total', 0);

        $this->putJson('/api/admin/usuarios/7/estado', ['estado' => 'BLQ', 'motivo' => 'prueba'])->assertOk()->assertJsonPath('data.estado_codigo', 'BLQ');
        $this->assertSame('BLQ', DB::connection('sqlsrv')->table('usuario')->where('id_usuario', 7)->value('estado_codigo'));
        $this->putJson('/api/admin/usuarios/7/estado', ['estado' => 'ACT'])->assertOk()->assertJsonPath('data.estado_codigo', 'ACT');
        $this->putJson('/api/admin/usuarios/404/estado', ['estado' => 'ACT'])->assertStatus(404);
    }

    public function test_catalogo_filtra_por_q_y_expone_urls_de_imagen(): void
    {
        (require database_path('migrations/mysql/2026_10_28_000001_create_sports_schema.php'))->up();
        $m = DB::connection('mysql');
        $m->table('pais')->insert(['codigo_iso' => 'CO', 'nombre' => 'Colombia']);
        $m->table('ciudad')->insert(['id_ciudad' => 1, 'nombre' => 'Bogotá', 'pais_codigo' => 'CO']);
        $m->table('liga')->insert(['id_liga' => 1, 'servicio_codigo' => 'FUTBOL', 'pais_codigo' => 'CO', 'nombre' => 'Liga BetPlay', 'logo_drive_id' => null, 'activa' => true]);
        $m->table('temporada')->insert(['id_temporada' => 1, 'liga_id' => 1, 'nombre' => '2026', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31']);
        $m->table('jornada')->insert(['id_jornada' => 1, 'temporada_id' => 1, 'numero' => 1, 'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-02']);
        $m->table('equipo')->insert([
            ['id_equipo' => 1, 'nombre' => 'Millonarios', 'escudo_drive_id' => null, 'ciudad_id' => 1],
            ['id_equipo' => 2, 'nombre' => 'Atlético Nacional', 'escudo_drive_id' => 'drive-abc', 'ciudad_id' => 1],
            ['id_equipo' => 3, 'nombre' => 'Junior FC', 'escudo_drive_id' => null, 'ciudad_id' => 1],
        ]);
        $m->table('partido')->insert([
            ['jornada_id' => 1, 'equipo_local_id' => 1, 'equipo_visitante_id' => 2, 'fecha_hora' => '2030-01-01 20:00:00', 'estado_codigo' => 'PROG'],
            ['jornada_id' => 1, 'equipo_local_id' => 3, 'equipo_visitante_id' => 1, 'fecha_hora' => '2030-01-02 20:00:00', 'estado_codigo' => 'PROG'],
        ]);

        $this->getJson('/api/partidos?q=nacional')->assertOk()->assertJsonPath('data.meta.total', 1)->assertJsonPath('data.items.0.visitante.nombre', 'Atlético Nacional');
        $this->getJson('/api/partidos?q=millon')->assertOk()->assertJsonPath('data.meta.total', 2);
        $this->getJson('/api/partidos?q=betplay')->assertOk()->assertJsonPath('data.meta.total', 2);
        $this->getJson('/api/partidos?q=zzz')->assertOk()->assertJsonPath('data.meta.total', 0);
        $this->getJson('/api/partidos?q=%25')->assertOk()->assertJsonPath('data.meta.total', 0); // el comodín se escapa

        $items = $this->getJson('/api/partidos?q=nacional')->json('data.items.0');
        $this->assertStringEndsWith('/api/media/equipos/1/escudo', $items['local']['escudo_url']);
        $this->assertStringContainsString('/api/media/equipos/2/escudo?v=', $items['visitante']['escudo_url']);
        $this->assertStringEndsWith('/api/media/ligas/1/logo', $this->getJson('/api/ligas')->json('data.items.0.logo_url'));

        // Sin archivo en Drive: SVG generado con las siglas y colores del equipo.
        $svg = $this->get('/api/media/equipos/1/escudo');
        $svg->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('aria-label="MIL"', $svg->getContent());
        $this->get('/api/media/ligas/1/logo')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->get('/api/media/equipos/999/escudo')->assertStatus(404);

        // Con archivo en Drive que no responde: también cae al SVG generado (Drive no configurado en tests).
        $this->get('/api/media/equipos/2/escudo')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }
}