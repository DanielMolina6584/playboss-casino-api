<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autenticación en dos pasos (TOTP): secreto cifrado con APP_KEY, último contador usado (anti-reutilización)
 * y códigos de recuperación guardados solo como hash. Solo agrega tablas; no toca datos existentes.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->create('usuario_mfa', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_id')->primary();
            $table->text('secreto_cifrado');
            $table->timestamp('confirmado_en')->nullable();   // NULL = configuración pendiente (aún no activa)
            $table->bigInteger('ultimo_contador')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->foreign('usuario_id')->references('id_usuario')->on('usuario')->cascadeOnDelete();
        });

        Schema::connection('sqlsrv')->create('usuario_mfa_recuperacion', function (Blueprint $table) {
            $table->bigIncrements('id_codigo');
            $table->unsignedBigInteger('usuario_id');
            $table->string('codigo_hash', 64);
            $table->timestamp('usado_en')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->unique(['usuario_id', 'codigo_hash'], 'uq_mfa_recuperacion');
            $table->foreign('usuario_id')->references('id_usuario')->on('usuario')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('usuario_mfa_recuperacion');
        Schema::connection('sqlsrv')->dropIfExists('usuario_mfa');
    }
};
