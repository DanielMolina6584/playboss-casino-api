<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('login') && !Schema::hasTable('logs')) {
            Schema::rename('login', 'logs');
        }

        if (Schema::hasTable('usuario') && Schema::hasColumn('usuario', 'correo')) {
            Schema::create('usuario_credencial', function (Blueprint $table) {
                $table->bigInteger('usuario_id')->primary();
                $table->string('correo', 120)->unique();
                $table->string('contrasena_hash', 255);
                $table->foreign('usuario_id', 'fk_usuario_credencial_usuario')
                    ->references('id_usuario')->on('usuario')
                    ->cascadeOnUpdate()->cascadeOnDelete();
            });

            Schema::create('usuario_perfil', function (Blueprint $table) {
                $table->bigInteger('usuario_id')->primary();
                $table->string('primer_nombre', 50);
                $table->string('segundo_nombre', 50)->nullable();
                $table->string('primer_apellido', 50);
                $table->string('segundo_apellido', 50)->nullable();
                $table->string('tipo_documento_codigo', 5);
                $table->string('numero_documento', 20);
                $table->string('celular', 20)->nullable();
                $table->date('fecha_nacimiento');
                $table->unique(
                    ['tipo_documento_codigo', 'numero_documento'],
                    'uq_usuario_perfil_documento'
                );
                $table->foreign('usuario_id', 'fk_usuario_perfil_usuario')
                    ->references('id_usuario')->on('usuario')
                    ->cascadeOnUpdate()->cascadeOnDelete();
                $table->foreign('tipo_documento_codigo', 'fk_usuario_perfil_tipo_documento')
                    ->references('codigo')->on('tipo_documento')
                    ->cascadeOnUpdate()->restrictOnDelete();
            });

            DB::table('usuario_credencial')->insertUsing(
                ['usuario_id', 'correo', 'contrasena_hash'],
                DB::table('usuario')->select('id_usuario', 'correo', 'contrasena_hash')
            );
            DB::table('usuario_perfil')->insertUsing(
                [
                    'usuario_id',
                    'primer_nombre',
                    'segundo_nombre',
                    'primer_apellido',
                    'segundo_apellido',
                    'tipo_documento_codigo',
                    'numero_documento',
                    'celular',
                    'fecha_nacimiento',
                ],
                DB::table('usuario')->select(
                    'id_usuario',
                    'primer_nombre',
                    'segundo_nombre',
                    'primer_apellido',
                    'segundo_apellido',
                    'tipo_documento_codigo',
                    'numero_documento',
                    'celular',
                    'fecha_nacimiento'
                )
            );

            Schema::table('usuario', function (Blueprint $table) {
                $table->dropForeign('fk_usuario_tipo_documento');
                $table->dropUnique('uq_usuario_correo');
                $table->dropUnique('uq_usuario_documento');
                $table->dropColumn([
                    'primer_nombre',
                    'segundo_nombre',
                    'primer_apellido',
                    'segundo_apellido',
                    'tipo_documento_codigo',
                    'numero_documento',
                    'correo',
                    'celular',
                    'fecha_nacimiento',
                    'contrasena_hash',
                ]);
            });
        }

        Schema::dropIfExists('jugador_equipo_historico');
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Esta migración transforma información personal y elimina jugador_equipo_historico; no tiene rollback automático.'
        );
    }
};
