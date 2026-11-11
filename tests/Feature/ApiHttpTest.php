<?php

namespace Tests\Feature;

use App\Models\Usuario\Usuario;
use App\Service\Payment\PagoService;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/** Pruebas HTTP que no necesitan bases de datos: validaciones, roles y webhook. */
class ApiHttpTest extends TestCase
{
    private function actuar(string $rol = 'CLIENTE', string $estado = 'ACT'): void
    {
        $u = new Usuario();
        $u->forceFill(['id_usuario' => 99, 'rol_codigo' => $rol, 'estado_codigo' => $estado]);
        Sanctum::actingAs($u);
    }

    public function test_rutas_protegidas_responden_401_json(): void
    {
        $this->getJson('/api/cuenta')->assertStatus(401)->assertJson(['error' => 1]);
        $this->postJson('/api/apuestas', [])->assertStatus(401);
    }

    public function test_usuario_inactivo_recibe_403(): void
    {
        $this->actuar('CLIENTE', 'BLQ');
        $this->getJson('/api/cuenta')->assertStatus(403);
    }

    public function test_cliente_no_accede_a_administracion(): void
    {
        $this->actuar('CLIENTE');
        $this->postJson('/api/admin/partidos/1/liquidar', ['goles_local' => 1, 'goles_visitante' => 0])->assertStatus(403);
        $this->getJson('/api/admin/equipos')->assertStatus(403);
    }

    public function test_apuesta_sin_selecciones_o_con_monto_invalido_es_422(): void
    {
        $this->actuar();
        $this->postJson('/api/apuestas', ['monto' => '10.555', 'selecciones' => []])
            ->assertStatus(422)->assertJsonValidationErrorFor('monto', 'data.errores')->assertJson(['error' => 1]);
    }

    public function test_apuesta_valida_estructuralmente_exige_campos_por_seleccion(): void
    {
        $this->actuar();
        $r = $this->postJson('/api/apuestas', ['monto' => '5000', 'selecciones' => [['match_id' => 'x']]]);
        $r->assertStatus(422);
        $this->assertArrayHasKey('selecciones.0.match_id', $r->json('data.errores'));
        $this->assertArrayHasKey('selecciones.0.market_id', $r->json('data.errores'));
    }

    public function test_apuesta_simple_con_dos_selecciones_es_rechazada(): void
    {
        $this->actuar();
        $s = fn ($m) => ['match_id' => $m, 'market_id' => 'abc', 'selection_code' => '1'];
        $this->postJson('/api/apuestas', ['monto' => '5000', 'tipo' => 'SIMPLE', 'selecciones' => [$s(1), $s(2)]])
            ->assertStatus(422)->assertJsonValidationErrorFor('tipo', 'data.errores');
    }

    public function test_exceso_de_selecciones_es_422(): void
    {
        $this->actuar();
        config(['playboss.bets.max_selections' => 2]);
        $s = fn ($m) => ['match_id' => $m, 'market_id' => 'abc', 'selection_code' => '1'];
        $this->postJson('/api/apuestas', ['monto' => '5000', 'selecciones' => [$s(1), $s(2), $s(3)]])->assertStatus(422);
    }

    public function test_combinada_con_selecciones_excluyentes_del_mismo_partido_es_422_antes_de_tocar_bd(): void
    {
        $this->actuar();
        $s = fn ($c) => ['match_id' => 1, 'market_id' => 'abc', 'selection_code' => $c];
        $this->postJson('/api/apuestas', ['monto' => '5000', 'idempotency_key' => 'clave-12345678', 'selecciones' => [$s('1'), $s('2')]])
            ->assertStatus(422)->assertJsonFragment(['No se permiten selecciones excluyentes del mismo partido en una combinada.']);
    }

    public function test_deposito_valida_monto(): void
    {
        $this->actuar();
        $this->postJson('/api/pagos/depositos', ['monto' => '-5'])->assertStatus(422);
        $this->postJson('/api/pagos/depositos', ['monto' => 'abc'])->assertStatus(422);
        $this->postJson('/api/pagos/depositos', [])->assertStatus(422);
    }

    public function test_catalogo_valida_filtros(): void
    {
        $this->getJson('/api/partidos?per_page=1000')->assertStatus(422);
        $this->getJson('/api/partidos?hasta=2026-01-01&desde=2026-02-01')->assertStatus(422);
    }

    public function test_registro_y_recuperacion_validan_entrada(): void
    {
        $this->postJson('/api/auth/recuperar', ['correo' => 'no-es-correo'])->assertStatus(422);
        $this->postJson('/api/auth/restablecer', ['correo' => 'a@b.co', 'codigo' => '12', 'contrasena' => 'x'])->assertStatus(422);
    }

    public function test_422_de_registro_y_login_incluyen_errores_por_campo(): void
    {
        $this->postJson('/api/auth/login', ['correo' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrorFor('correo', 'data.errores')->assertJson(['error' => 1]);
        $this->postJson('/api/auth/registro', ['primer_nombre' => 'A', 'celular' => 'abc'])
            ->assertStatus(422)->assertJsonValidationErrorFor('celular', 'data.errores');
    }

    public function test_estado_fuera_del_enum_en_listados_es_422(): void
    {
        $this->getJson('/api/partidos?estado=XXXX')->assertStatus(422)->assertJsonValidationErrorFor('estado', 'data.errores');
    }

    public function test_con_mercados_debe_ser_booleano(): void
    {
        $this->getJson('/api/partidos?con_mercados=quizas')->assertStatus(422)->assertJsonValidationErrorFor('con_mercados', 'data.errores');
    }

    public function test_webhook_ignora_eventos_que_no_son_de_transaccion(): void
    {
        $this->postJson('/api/pagos/wompi/webhook', ['event' => 'nequi_token.updated'])->assertOk();
    }

    public function test_webhook_con_checksum_invalido_en_header_devuelve_401(): void
    {
        $this->postJson('/api/pagos/wompi/webhook', [
            'event' => 'transaction.updated',
            'data' => ['transaction' => ['id' => 'x']],
            'signature' => ['checksum' => 'aaa', 'properties' => []], 'timestamp' => 1,
        ], ['X-Event-Checksum' => 'bbb'])->assertStatus(401);
    }

    public function test_webhook_forjado_no_verificado_devuelve_401_sin_tocar_bd(): void
    {
        config(['services.payment_gateway.driver' => 'wompi', 'services.wompi.events_secret' => 'secreto', 'services.wompi.private_key' => 'prv_test_x']);
        $this->postJson('/api/pagos/wompi/webhook', [
            'event' => 'transaction.updated',
            'data' => ['transaction' => ['id' => 'T1', 'status' => 'APPROVED', 'amount_in_cents' => 100000, 'reference' => 'PB-1-AAAA']],
            'signature' => ['properties' => ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'], 'checksum' => str_repeat('0', 64)], 'timestamp' => 1530291411,
        ])->assertStatus(401);
    }

    public function test_webhook_valido_delega_en_el_servicio_de_pagos(): void
    {
        $mock = Mockery::mock(PagoService::class);
        $mock->shouldReceive('confirmarEvento')->once();
        $this->app->instance(PagoService::class, $mock);

        $this->postJson('/api/pagos/wompi/webhook', ['event' => 'transaction.updated', 'data' => ['transaction' => ['id' => 'T']]])
            ->assertOk()->assertJson(['error' => 0]);
    }

    public function test_webhook_mercadopago_ignora_otros_tipos(): void
    {
        $this->postJson('/api/pagos/mercadopago/webhook', ['type' => 'merchant_order', 'data' => ['id' => '1']])->assertOk();
    }

    public function test_webhook_mercadopago_delega_en_el_servicio_con_el_id_del_pago(): void
    {
        $mock = Mockery::mock(PagoService::class);
        $mock->shouldReceive('confirmarEvento')->once()->withArgs(fn ($e) => $e['data_id'] === '777' && $e['x_signature'] === 'ts=1,v1=a' && $e['x_request_id'] === 'rq');
        $this->app->instance(PagoService::class, $mock);

        $this->postJson('/api/pagos/mercadopago/webhook?data.id=777&type=payment', ['type' => 'payment', 'data' => ['id' => '777']], ['x-signature' => 'ts=1,v1=a', 'x-request-id' => 'rq'])
            ->assertOk()->assertJson(['error' => 0]);
    }

    public function test_cors_permite_el_front_local(): void
    {
        $this->options('/api/partidos', [], [
            'Origin' => 'http://localhost:5173', 'Access-Control-Request-Method' => 'GET',
        ])->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_registro_y_cambio_de_foto_exigen_una_imagen(): void
    {
        $this->postJson('/api/auth/registro', ['primer_nombre' => 'Laura'])
            ->assertStatus(422)->assertJsonValidationErrorFor('foto', 'data.errores');

        $this->actuar();
        $this->postJson('/api/perfil/foto', [])->assertStatus(422);
        $this->post('/api/perfil/foto', ['foto' => \Illuminate\Http\UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }
}
