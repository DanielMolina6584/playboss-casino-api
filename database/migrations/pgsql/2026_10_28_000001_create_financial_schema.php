<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('cuenta', function (Blueprint $table) {
            $table->bigInteger('usuario_id')->primary(); // ID externo de SQL Server; sin FK entre motores.
            $table->decimal('saldo_disponible', 14, 2)->default(0);
            $table->decimal('saldo_retenido', 14, 2)->default(0);
            $table->char('moneda', 3)->default('COP');
            $table->timestampTz('fecha_actualizacion')->useCurrent();
        });
        Schema::connection('pgsql')->create('estado_apuesta', function (Blueprint $table) {
            $table->string('codigo', 10)->primary();
            $table->string('nombre', 40);
        });
        Schema::connection('pgsql')->create('metodo_pago', function (Blueprint $table) {
            $table->string('codigo', 30)->primary();
            $table->string('nombre', 80);
            $table->boolean('activo')->default(true);
            $table->jsonb('configuracion_publica')->nullable(); // Nunca guardar claves privadas aquí.
        });
        Schema::connection('pgsql')->create('apuesta', function (Blueprint $table) {
            $table->bigIncrements('id_apuesta');
            $table->bigInteger('usuario_id'); // ID externo de SQL Server.
            $table->decimal('monto', 14, 2);
            $table->decimal('cuota_total', 16, 4);
            $table->decimal('ganancia_potencial', 14, 2);
            $table->string('estado_codigo', 10);
            $table->string('clave_idempotencia', 100)->unique();
            $table->timestampTz('creada_en')->useCurrent();
            $table->foreign('usuario_id')->references('usuario_id')->on('cuenta')->restrictOnDelete();
            $table->foreign('estado_codigo')->references('codigo')->on('estado_apuesta')->restrictOnDelete();
            $table->index(['usuario_id', 'creada_en']);
            $table->index('estado_codigo');
        });
        Schema::connection('pgsql')->create('apuesta_detalle', function (Blueprint $table) {
            $table->bigIncrements('id_detalle');
            $table->unsignedBigInteger('apuesta_id');
            $table->unsignedBigInteger('partido_id_mysql'); // Referencia externa, se valida en el servicio.
            $table->string('mercado_id_mongodb', 64); // ObjectId/ID Mongo como valor escalar.
            $table->string('seleccion_codigo', 40);
            $table->string('seleccion_etiqueta', 120);
            $table->decimal('cuota_congelada', 16, 4);
            $table->foreign('apuesta_id')->references('id_apuesta')->on('apuesta')->cascadeOnDelete();
            $table->unique(['apuesta_id', 'partido_id_mysql', 'mercado_id_mongodb', 'seleccion_codigo'], 'uq_apuesta_seleccion');
            $table->index(['partido_id_mysql', 'mercado_id_mongodb']);
        });
        Schema::connection('pgsql')->create('transaccion', function (Blueprint $table) {
            $table->bigIncrements('id_transaccion');
            $table->bigInteger('usuario_id'); // ID externo de SQL Server.
            $table->string('metodo_pago_codigo', 30);
            $table->string('tipo', 12); // DEPOSITO / RETIRO
            $table->decimal('monto', 14, 2);
            $table->char('moneda', 3)->default('COP');
            $table->string('estado', 20)->default('PENDIENTE');
            $table->string('clave_idempotencia', 100)->unique();
            $table->string('referencia_externa', 150)->nullable()->unique();
            $table->text('url_redireccion')->nullable();
            $table->timestampTz('fecha_hora')->useCurrent();
            $table->timestampTz('confirmada_en')->nullable();
            $table->foreign('usuario_id')->references('usuario_id')->on('cuenta')->restrictOnDelete();
            $table->foreign('metodo_pago_codigo')->references('codigo')->on('metodo_pago')->restrictOnDelete();
            $table->index(['usuario_id', 'fecha_hora']);
            $table->index('estado');
        });
        Schema::connection('pgsql')->create('evento_pago', function (Blueprint $table) {
            $table->bigIncrements('id_evento');
            $table->string('id_evento_proveedor', 150)->unique();
            $table->unsignedBigInteger('transaccion_id');
            $table->string('tipo_evento', 40);
            $table->boolean('verificado')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('recibido_en')->useCurrent();
            $table->foreign('transaccion_id')->references('id_transaccion')->on('transaccion')->restrictOnDelete();
            $table->index(['transaccion_id', 'recibido_en']);
        });
        Schema::connection('pgsql')->create('movimiento', function (Blueprint $table) {
            $table->bigIncrements('id_movimiento');
            $table->bigInteger('usuario_id');
            $table->string('tipo', 30);
            $table->decimal('monto', 14, 2);
            $table->decimal('saldo_disponible_resultante', 14, 2);
            $table->decimal('saldo_retenido_resultante', 14, 2);
            $table->string('referencia_idempotencia', 120)->unique();
            $table->unsignedBigInteger('transaccion_id')->nullable();
            $table->unsignedBigInteger('apuesta_id')->nullable();
            $table->string('descripcion', 200)->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->foreign('usuario_id')->references('usuario_id')->on('cuenta')->restrictOnDelete();
            $table->foreign('transaccion_id')->references('id_transaccion')->on('transaccion')->restrictOnDelete();
            $table->foreign('apuesta_id')->references('id_apuesta')->on('apuesta')->restrictOnDelete();
            $table->index(['usuario_id', 'creado_en']);
        });

        foreach ([
            ['PEND', 'Pendiente'],
            ['GAN', 'Ganada'],
            ['PER', 'Perdida'],
            ['ANU', 'Anulada'],
            ['CASH', 'Cobrada anticipadamente'],
        ] as [$codigo, $nombre]) {
            DB::connection('pgsql')->table('estado_apuesta')->updateOrInsert(['codigo' => $codigo], ['nombre' => $nombre]);
        }
        DB::connection('pgsql')->table('metodo_pago')->updateOrInsert(
            ['codigo' => 'FIREBASE'],
            ['nombre' => 'Cloud Function de pagos', 'activo' => true]
        );

        DB::connection('pgsql')->statement('ALTER TABLE cuenta ADD CONSTRAINT chk_cuenta_saldos_no_negativos CHECK (saldo_disponible >= 0 AND saldo_retenido >= 0)');
        DB::connection('pgsql')->statement('ALTER TABLE apuesta ADD CONSTRAINT chk_apuesta_montos CHECK (monto > 0 AND cuota_total > 1 AND ganancia_potencial > 0)');
        DB::connection('pgsql')->statement("ALTER TABLE transaccion ADD CONSTRAINT chk_transaccion_valida CHECK (monto > 0 AND tipo IN ('DEPOSITO', 'RETIRO'))");
        DB::connection('pgsql')->statement('ALTER TABLE apuesta_detalle ADD CONSTRAINT chk_cuota_congelada CHECK (cuota_congelada > 1)');
    }

    public function down(): void
    {
        foreach (['movimiento', 'evento_pago', 'transaccion', 'apuesta_detalle', 'apuesta', 'metodo_pago', 'estado_apuesta', 'cuenta'] as $table) {
            Schema::connection('pgsql')->dropIfExists($table);
        }
    }
};
