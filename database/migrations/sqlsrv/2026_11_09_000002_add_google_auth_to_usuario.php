<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inicio de sesión con Google: google_sub (único, filtrado), proveedor de auth, contraseña opcional
 * y perfil incompleto (fecha de nacimiento / documento se completan después). Los datos existentes no cambian.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        $db = DB::connection('sqlsrv');

        Schema::connection('sqlsrv')->table('usuario_credencial', function (Blueprint $table) {
            $table->string('google_sub', 64)->nullable();
            $table->string('auth_proveedor', 20)->default('LOCAL');
        });
        // Índice único filtrado: permite muchos NULL (usuarios sin Google) pero no repetir google_sub.
        $db->statement('CREATE UNIQUE INDEX uq_credencial_google_sub ON usuario_credencial (google_sub) WHERE google_sub IS NOT NULL');
        $db->statement('ALTER TABLE usuario_credencial ALTER COLUMN contrasena_hash NVARCHAR(255) NULL');

        Schema::connection('sqlsrv')->table('usuario_perfil', function (Blueprint $table) {
            $table->string('foto_url', 500)->nullable();
        });
        // El índice único (tipo, número) bloquea ALTER COLUMN: se suelta y se recrea filtrado.
        $db->statement('DROP INDEX IF EXISTS uq_perfil_documento ON usuario_perfil');
        $db->statement('ALTER TABLE usuario_perfil DROP CONSTRAINT IF EXISTS uq_perfil_documento');
        $db->statement('ALTER TABLE usuario_perfil ALTER COLUMN primer_apellido NVARCHAR(50) NULL');
        $db->statement('ALTER TABLE usuario_perfil ALTER COLUMN tipo_documento_codigo NVARCHAR(5) NULL');
        $db->statement('ALTER TABLE usuario_perfil ALTER COLUMN numero_documento NVARCHAR(20) NULL');
        $db->statement('ALTER TABLE usuario_perfil ALTER COLUMN fecha_nacimiento DATE NULL');
        $db->statement('CREATE UNIQUE INDEX uq_perfil_documento ON usuario_perfil (tipo_documento_codigo, numero_documento) WHERE numero_documento IS NOT NULL');
    }

    public function down(): void
    {
        $db = DB::connection('sqlsrv');

        // Los usuarios creados con Google no cumplen las restricciones antiguas: se completan o se borran antes.
        $db->statement("UPDATE usuario_perfil SET primer_apellido = '-' WHERE primer_apellido IS NULL");
        $db->statement('DROP INDEX IF EXISTS uq_perfil_documento ON usuario_perfil');
        $db->statement('ALTER TABLE usuario_perfil ALTER COLUMN primer_apellido NVARCHAR(50) NOT NULL');
        $db->statement('ALTER TABLE usuario_perfil ALTER COLUMN tipo_documento_codigo NVARCHAR(5) NOT NULL');
        $db->statement('ALTER TABLE usuario_perfil ALTER COLUMN numero_documento NVARCHAR(20) NOT NULL');
        $db->statement('ALTER TABLE usuario_perfil ALTER COLUMN fecha_nacimiento DATE NOT NULL');
        $db->statement('CREATE UNIQUE INDEX uq_perfil_documento ON usuario_perfil (tipo_documento_codigo, numero_documento)');
        Schema::connection('sqlsrv')->table('usuario_perfil', fn (Blueprint $t) => $t->dropColumn('foto_url'));

        $db->statement('DROP INDEX IF EXISTS uq_credencial_google_sub ON usuario_credencial');
        $db->statement("UPDATE usuario_credencial SET contrasena_hash = '' WHERE contrasena_hash IS NULL");
        $db->statement('ALTER TABLE usuario_credencial ALTER COLUMN contrasena_hash NVARCHAR(255) NOT NULL');
        Schema::connection('sqlsrv')->table('usuario_credencial', function (Blueprint $table) {
            $table->dropColumn('auth_proveedor');
            $table->dropColumn('google_sub');
        });
    }
};
