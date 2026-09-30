<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jugador') || !Schema::hasTable('apuesta')) {
            throw new RuntimeException(
                'Primero ejecuta la migracion de esquema PlayBoss antes de cargar los datos demo completos.'
            );
        }

        if (DB::table('apuesta')->exists()) {
            throw new RuntimeException(
                'La carga completa reemplaza partidos y plantillas; no se ejecuta mientras existan apuestas.'
            );
        }

        if (
            DB::table('usuario_credencial')->where('correo', 'like', 'demo%@playboss.test')->exists()
            || DB::table('usuario')->whereBetween('id_usuario', [900001, 900300])->exists()
            || DB::table('empresa')->where('nit', '900999888-1')->exists()
        ) {
            throw new RuntimeException('Ya existen datos demo PlayBoss; la carga completa no se debe ejecutar otra vez.');
        }

        foreach (['primer_apellido', 'fecha_nacimiento', 'pais_codigo', 'posicion_codigo'] as $column) {
            if (!Schema::hasColumn('jugador', $column)) {
                throw new RuntimeException('Falta la columna jugador.' . $column . ' requerida para importar los rosters.');
            }
        }

        $driver = DB::getDriverName();
        $dataPath = match ($driver) {
            'mysql', 'mariadb' => database_path('sql/data/playboss-complete-demo-mysql.sql'),
            'sqlsrv' => database_path('sql/data/playboss-complete-demo-sqlserver.sql'),
            default => throw new RuntimeException(
                'La carga de la Liga BetPlay solo esta disponible para MySQL/MariaDB y SQL Server.'
            ),
        };

        $sql = file_get_contents($dataPath);
        if ($sql === false) {
            throw new RuntimeException('No se pudo leer la carga completa de datos PlayBoss: ' . $dataPath);
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement(
                'ALTER TABLE jugador MODIFY primer_apellido VARCHAR(50) NULL, MODIFY fecha_nacimiento DATE NULL, MODIFY pais_codigo CHAR(2) NULL, MODIFY posicion_codigo VARCHAR(3) NULL'
            );
        } else {
            DB::statement('ALTER TABLE jugador ALTER COLUMN primer_apellido NVARCHAR(50) NULL');
            DB::statement('ALTER TABLE jugador ALTER COLUMN fecha_nacimiento DATE NULL');
            DB::statement('ALTER TABLE jugador ALTER COLUMN pais_codigo NCHAR(2) NULL');
            DB::statement('ALTER TABLE jugador ALTER COLUMN posicion_codigo NVARCHAR(3) NULL');
        }

        DB::unprepared($sql);
    }

    public function down(): void
    {
        throw new RuntimeException(
            'La carga completa de datos demo no tiene rollback automatico porque reemplaza plantillas y calendarios.'
        );
    }
};
