<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->create('rol', function (Blueprint $table) {
            $table->string('codigo', 15)->primary();
            $table->string('nombre', 50);
            $table->string('descripcion', 150)->nullable();
        });

        Schema::connection('sqlsrv')->create('estado_usuario', function (Blueprint $table) {
            $table->string('codigo', 5)->primary();
            $table->string('nombre', 40);
            $table->string('descripcion', 150)->nullable();
        });

        Schema::connection('sqlsrv')->create('tipo_documento', function (Blueprint $table) {
            $table->string('codigo', 5)->primary();
            $table->string('nombre', 60);
        });

        Schema::connection('sqlsrv')->create('usuario', function (Blueprint $table) {
            $table->bigIncrements('id_usuario');
            $table->string('rol_codigo', 15);
            $table->string('estado_codigo', 5);
            $table->timestamp('fecha_registro')->useCurrent();
            $table->rememberToken();
            $table->foreign('rol_codigo')->references('codigo')->on('rol')->restrictOnDelete();
            $table->foreign('estado_codigo')->references('codigo')->on('estado_usuario')->restrictOnDelete();
            $table->index('rol_codigo');
            $table->index('estado_codigo');
        });

        Schema::connection('sqlsrv')->create('usuario_credencial', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_id')->primary();
            $table->string('correo', 120)->unique();
            $table->string('contrasena_hash', 255);
            $table->timestamp('correo_verificado_en')->nullable();
            $table->foreign('usuario_id')->references('id_usuario')->on('usuario')->cascadeOnDelete();
        });

        Schema::connection('sqlsrv')->create('usuario_perfil', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_id')->primary();
            $table->string('primer_nombre', 50);
            $table->string('segundo_nombre', 50)->nullable();
            $table->string('primer_apellido', 50);
            $table->string('segundo_apellido', 50)->nullable();
            $table->string('tipo_documento_codigo', 5);
            $table->string('numero_documento', 20);
            $table->string('celular', 20)->nullable();
            $table->date('fecha_nacimiento');
            $table->unique(['tipo_documento_codigo', 'numero_documento'], 'uq_perfil_documento');
            $table->foreign('usuario_id')->references('id_usuario')->on('usuario')->cascadeOnDelete();
            $table->foreign('tipo_documento_codigo')->references('codigo')->on('tipo_documento')->restrictOnDelete();
        });

        Schema::connection('sqlsrv')->create('codigo_recuperacion', function (Blueprint $table) {
            $table->bigIncrements('id_codigo');
            $table->unsignedBigInteger('usuario_id');
            $table->string('codigo_hash', 255);
            $table->timestamp('fecha_generacion')->useCurrent();
            $table->timestamp('fecha_expiracion');
            $table->timestamp('usado_en')->nullable();
            $table->foreign('usuario_id')->references('id_usuario')->on('usuario')->cascadeOnDelete();
            $table->index(['usuario_id', 'fecha_expiracion']);
        });

        Schema::connection('sqlsrv')->create('personal_access_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        foreach ([
            ['rol', ['codigo' => 'CLIENTE'], ['nombre' => 'Cliente', 'descripcion' => 'Usuario cliente']],
            ['rol', ['codigo' => 'ADMIN'], ['nombre' => 'Administrador', 'descripcion' => 'Rol administrativo']],
            ['estado_usuario', ['codigo' => 'ACT'], ['nombre' => 'Activo', 'descripcion' => 'Cuenta habilitada']],
            ['tipo_documento', ['codigo' => 'CC'], ['nombre' => 'Cédula de ciudadanía']],
            ['tipo_documento', ['codigo' => 'CE'], ['nombre' => 'Cédula de extranjería']],
            ['tipo_documento', ['codigo' => 'TI'], ['nombre' => 'Tarjeta de identidad']],
            ['tipo_documento', ['codigo' => 'PA'], ['nombre' => 'Pasaporte']],
            ['tipo_documento', ['codigo' => 'NIT'], ['nombre' => 'NIT']],
        ] as [$table, $key, $values]) {
            DB::connection('sqlsrv')->table($table)->updateOrInsert($key, $values);
        }
    }

    public function down(): void
    {
        foreach (['personal_access_tokens', 'codigo_recuperacion', 'usuario_perfil', 'usuario_credencial', 'usuario', 'tipo_documento', 'estado_usuario', 'rol'] as $table) {
            Schema::connection('sqlsrv')->dropIfExists($table);
        }
    }
};
