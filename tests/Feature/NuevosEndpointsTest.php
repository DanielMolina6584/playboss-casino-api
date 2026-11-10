<?php

namespace Tests\Feature;

use App\Models\Usuario\Usuario;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Validaciones, roles y contratos que no necesitan bases de datos. */
class NuevosEndpointsTest extends TestCase
{
    private function actuar(string $rol = 'CLIENTE'): void
    {
        $u = new Usuario();
        $u->forceFill(['id_usuario' => 99, 'rol_codigo' => $rol, 'estado_codigo' => 'ACT']);
        Sanctum::actingAs($u);
    }

    public function test_configuracion_limites_es_publica_y_expone_rangos(): void
    {
        $this->getJson('/api/configuracion/limites')->assertOk()
            ->assertJsonPath('data.moneda', 'COP')
            ->assertJsonPath('data.apuestas.max_selecciones', 10)
            ->assertJsonPath('data.recarga.monto_minimo', '1.00')
            ->assertJsonPath('data.retiro.monto_minimo', '10000.00')
            ->assertJsonStructure(['data' => ['apuestas' => ['monto_minimo', 'monto_maximo'], 'retiro' => ['monto_maximo', 'max_pendientes']]]);
    }

    public function test_retiros_exigen_autenticacion_y_validan_cuerpo(): void
    {
        $this->postJson('/api/pagos/retiros', [])->assertStatus(401);
        $this->getJson('/api/pagos/retiros')->assertStatus(401);

        $this->actuar();
        $r = $this->postJson('/api/pagos/retiros', ['monto' => '10.555', 'tipo_cuenta' => 'X', 'numero_cuenta' => 'abc']);
        $r->assertStatus(422);
        foreach (['monto', 'banco', 'tipo_cuenta', 'numero_cuenta', 'titular', 'documento_titular'] as $campo) {
            $this->assertArrayHasKey($campo, $r->json('data.errores'));
        }
    }

    public function test_admin_retiros_y_usuarios_solo_para_admin(): void
    {
        $this->actuar('CLIENTE');
        $this->getJson('/api/admin/retiros')->assertStatus(403);
        $this->postJson('/api/admin/retiros/1/aprobar')->assertStatus(403);
        $this->postJson('/api/admin/retiros/1/rechazar', ['motivo' => 'x'])->assertStatus(403);
        $this->getJson('/api/admin/usuarios')->assertStatus(403);
        $this->putJson('/api/admin/usuarios/1/estado', ['estado' => 'BLQ'])->assertStatus(403);
    }

    public function test_admin_valida_estado_y_motivo(): void
    {
        $this->actuar('ADMIN');
        $this->putJson('/api/admin/usuarios/1/estado', ['estado' => 'XX'])->assertStatus(422);
        $this->postJson('/api/admin/retiros/1/rechazar', [])->assertStatus(422);
        $this->getJson('/api/admin/usuarios?estado=ZZZ')->assertStatus(422);
        // No puede cambiar su propia cuenta (antes de tocar la base).
        $this->putJson('/api/admin/usuarios/99/estado', ['estado' => 'BLQ'])->assertStatus(422);
    }

    public function test_limites_de_juego_validan_formato(): void
    {
        $this->getJson('/api/perfil/limites')->assertStatus(401);
        $this->actuar();
        $this->putJson('/api/perfil/limites', ['apuesta_diario' => '-5', 'autoexclusion_dias' => 3])
            ->assertStatus(422)->assertJsonValidationErrorFor('apuesta_diario', 'data.errores')->assertJsonValidationErrorFor('autoexclusion_dias', 'data.errores');
    }

    public function test_contacto_valida_campos(): void
    {
        $this->postJson('/api/contacto', ['correo' => 'no-es-correo', 'mensaje' => 'corto'])->assertStatus(422)
            ->assertJsonValidationErrorFor('correo', 'data.errores')->assertJsonValidationErrorFor('nombre', 'data.errores');
    }

    public function test_contacto_envia_correo_aunque_mongo_no_responda(): void
    {
        config([
            'database.connections.mongodb.dsn' => 'mongodb://127.0.0.1:1',
            'database.connections.mongodb.options.serverSelectionTimeoutMS' => 200,
            'database.connections.mongodb.options.connectTimeoutMS' => 200,
            'playboss.contact.to' => 'soporte@example.com',
        ]);
        Mail::fake();

        $this->postJson('/api/contacto', ['nombre' => 'Laura', 'correo' => 'laura@example.com', 'asunto' => "Hola\r\nBcc: x@y.z", 'mensaje' => 'Mensaje de prueba largo.'])
            ->assertStatus(201)->assertJson(['error' => 0]);
    }

    public function test_contacto_responde_503_si_no_se_puede_guardar_ni_enviar(): void
    {
        config([
            'database.connections.mongodb.dsn' => 'mongodb://127.0.0.1:1',
            'database.connections.mongodb.options.serverSelectionTimeoutMS' => 200,
            'database.connections.mongodb.options.connectTimeoutMS' => 200,
            'playboss.contact.to' => null,
        ]);

        $this->postJson('/api/contacto', ['nombre' => 'Laura', 'correo' => 'laura@example.com', 'asunto' => 'Hola', 'mensaje' => 'Mensaje de prueba largo.'])
            ->assertStatus(503)->assertJson(['error' => 1]);
    }

    public function test_contacto_tiene_rate_limit(): void
    {
        config(['playboss.contact.to' => null, 'database.connections.mongodb.dsn' => 'mongodb://127.0.0.1:1', 'database.connections.mongodb.options.serverSelectionTimeoutMS' => 100]);
        $status = [];
        for ($i = 0; $i < 7; $i++) {
            $status[] = $this->postJson('/api/contacto', [])->status();
        }
        $this->assertContains(429, $status);
    }
}
