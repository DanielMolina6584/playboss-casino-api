<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inicio de sesión con Google: google_sub (único, filtrado), proveedor de auth, contraseña opcional
 * y perfil incompleto (fecha de nacimiento / documento se completan después). Los datos existentes no cambian.
 *
 * Funciona sobre la base creada por la migración de identidad (índice `uq_perfil_documento`) y sobre la creada con
 * database/sql/playboss-schema-sqlserver.sql (restricción UNIQUE `uq_usuario_perfil_documento`, sin `correo_verificado_en`).
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        $db = DB::connection('sqlsrv');

        Schema::connection('sqlsrv')->table('usuario_credencial', function (Blueprint $table) {
            // Ausente cuando la base salió del script SQL en vez de la migración de identidad.
            if (!Schema::connection('sqlsrv')->hasColumn('usuario_credencial', 'correo_verificado_en')) {
                $table->timestamp('correo_verificado_en')->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('usuario_credencial', 'google_sub')) {
                $table->string('google_sub', 255)->nullable(); // el `sub` de Google puede llegar a 255 caracteres ASCII
            }
            if (!Schema::connection('sqlsrv')->hasColumn('usuario_credencial', 'auth_proveedor')) {
                $table->string('auth_proveedor', 20)->default('LOCAL'); // NOT NULL + DEFAULT: SQL Server rellena las filas existentes
            }
        });
        // Índice único filtrado: permite muchos NULL (usuarios sin Google) pero no repetir google_sub.
        // Los índices filtrados exigen QUOTED_IDENTIFIER y ANSI_NULLS ON (en la misma sesión que crea el índice y que escribe en la tabla).
        $db->unprepared("SET QUOTED_IDENTIFIER ON; SET ANSI_NULLS ON; IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'uq_credencial_google_sub' AND object_id = OBJECT_ID(N'usuario_credencial')) CREATE UNIQUE INDEX uq_credencial_google_sub ON usuario_credencial (google_sub) WHERE google_sub IS NOT NULL");
        $db->statement('ALTER TABLE usuario_credencial ALTER COLUMN contrasena_hash NVARCHAR(255) NULL'); // sin índices ni constraints sobre la columna

        Schema::connection('sqlsrv')->table('usuario_perfil', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('usuario_perfil', 'foto_url')) {
                $table->string('foto_url', 500)->nullable();
            }
        });
        // Cualquier unicidad sobre (tipo_documento_codigo, numero_documento) bloquea el ALTER COLUMN y, sin filtro,
        // solo admitiría una fila con NULL: se suelta sea índice (`uq_perfil_documento`) o restricción UNIQUE
        // (`uq_usuario_perfil_documento` del script SQL) y se recrea filtrada. El FK a tipo_documento no se toca:
        // cambiar solo la nulabilidad de una columna con FK es válido en SQL Server.
        $db->unprepared(<<<'SQL'
DECLARE @sql NVARCHAR(MAX) = N'';
SELECT @sql += CASE WHEN i.is_unique_constraint = 1
        THEN N'ALTER TABLE ' + QUOTENAME(OBJECT_SCHEMA_NAME(i.object_id)) + N'.usuario_perfil DROP CONSTRAINT ' + QUOTENAME(i.name) + N'; '
        ELSE N'DROP INDEX ' + QUOTENAME(i.name) + N' ON ' + QUOTENAME(OBJECT_SCHEMA_NAME(i.object_id)) + N'.usuario_perfil; ' END
FROM sys.indexes i
WHERE i.object_id = OBJECT_ID(N'usuario_perfil') AND i.is_unique = 1 AND i.is_primary_key = 0
  AND EXISTS (SELECT 1 FROM sys.index_columns ic JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
              WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id AND c.name IN (N'tipo_documento_codigo', N'numero_documento'));
EXEC sp_executesql @sql;
SQL);
        $db->statement('ALTER TABLE usuario_perfil ALTER COLUMN primer_apellido NVARCHAR(50) NULL');
        $db->statement('ALTER TABLE usuario_perfil ALTER COLUMN tipo_documento_codigo NVARCHAR(5) NULL');
        $db->statement('ALTER TABLE usuario_perfil ALTER COLUMN numero_documento NVARCHAR(20) NULL');
        $db->statement('ALTER TABLE usuario_perfil ALTER COLUMN fecha_nacimiento DATE NULL');
        $db->unprepared('SET QUOTED_IDENTIFIER ON; SET ANSI_NULLS ON; CREATE UNIQUE INDEX uq_perfil_documento ON usuario_perfil (tipo_documento_codigo, numero_documento) WHERE numero_documento IS NOT NULL');
    }

    public function down(): void
    {
        $db = DB::connection('sqlsrv');

        // Los usuarios creados con Google no cumplen las restricciones antiguas: hay que completarlos o borrarlos antes de revertir.
        $incompletos = $db->table('usuario_perfil')->where(fn ($q) => $q->whereNull('tipo_documento_codigo')->orWhereNull('numero_documento')->orWhereNull('fecha_nacimiento'))->count();
        if ($incompletos > 0) {
            throw new RuntimeException("No se puede revertir: hay $incompletos perfil(es) sin documento o fecha de nacimiento (usuarios de Google). Complétalos o elimínalos y reintenta.");
        }

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
        // dropColumn de Laravel suelta antes el DEFAULT (nombre autogenerado) de auth_proveedor. correo_verificado_en se conserva.
        Schema::connection('sqlsrv')->table('usuario_credencial', function (Blueprint $table) {
            $table->dropColumn('auth_proveedor');
            $table->dropColumn('google_sub');
        });
    }
};
