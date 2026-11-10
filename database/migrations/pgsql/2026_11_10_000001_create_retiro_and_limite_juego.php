<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('retiro', function (Blueprint $table) {
            $table->bigIncrements('id_retiro');
            $table->bigInteger('usuario_id');
            $table->decimal('monto', 14, 2);
            $table->string('moneda', 3)->default('COP');
            $table->string('estado', 12)->default('PENDIENTE'); // PENDIENTE / PAGADO / RECHAZADO
            $table->string('clave_idempotencia', 100)->unique();
            $table->string('banco', 60);
            $table->string('tipo_cuenta', 15);
            $table->string('numero_cuenta', 30);
            $table->string('titular', 120);
            $table->string('documento_titular', 20);
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('motivo_rechazo', 200)->nullable();
            $table->timestampTz('solicitado_en')->useCurrent();
            $table->timestampTz('resuelto_en')->nullable();
            $table->foreign('usuario_id')->references('usuario_id')->on('cuenta')->restrictOnDelete();
            $table->index(['usuario_id', 'estado']);
            $table->index(['estado', 'solicitado_en']);
        });

        Schema::connection('pgsql')->create('limite_juego', function (Blueprint $table) {
            $table->bigInteger('usuario_id')->primary();
            $table->decimal('deposito_diario', 14, 2)->nullable();
            $table->decimal('deposito_semanal', 14, 2)->nullable();
            $table->decimal('deposito_mensual', 14, 2)->nullable();
            $table->decimal('apuesta_diario', 14, 2)->nullable();
            $table->decimal('apuesta_semanal', 14, 2)->nullable();
            $table->decimal('apuesta_mensual', 14, 2)->nullable();
            $table->timestampTz('autoexcluido_hasta')->nullable();
            $table->timestampTz('actualizado_en')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('limite_juego');
        Schema::connection('pgsql')->dropIfExists('retiro');
    }
};
