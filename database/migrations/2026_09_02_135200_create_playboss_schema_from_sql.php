<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fuente de verdad del esquema de negocio de playboss (33 tablas): ejecuta
 * la variante SQL del motor configurado. En un entorno nuevo esto crea el
 * esquema completo; la carga de Liga BetPlay se hace en una migracion aparte.
 * Si usuario ya existe, se omite el DDL para no recrear ni borrar datos.
 *
 * Cambios futuros al negocio van en migraciones complementarias aparte
 * (ver database/migrations/*_add_*.php); este archivo no se debe editar.
 */
return new class extends Migration
{
    /**
     * Tablas creadas por playboss-schema.sql, en orden seguro para drop
     * (hijas antes que padres) — usado únicamente por down().
     */
    private const TABLAS = [
        'log_pago', 'apuesta_detalle', 'cuota', 'apuesta', 'estado_apuesta',
        'regla_apuesta', 'mercado', 'alineacion', 'partido', 'estado_partido',
        'jornada', 'codigo_recuperacion', 'logs', 'usuario_perfil',
        'usuario_credencial', 'cuenta', 'transaccion', 'metodo_pago',
        'usuario', 'estado_usuario', 'rol', 'tipo_documento', 'jugador', 'posicion',
        'equipo_liga_temporada', 'equipo', 'estadio', 'temporada', 'liga',
        'servicio', 'ciudad', 'pais', 'empresa',
    ];

    public function up(): void
    {
        if (Schema::hasTable('usuario')) {
            return;
        }

        $schemaPath = match (DB::getDriverName()) {
            'mysql', 'mariadb' => database_path('sql/playboss-schema.sql'),
            'sqlsrv' => database_path('sql/playboss-schema-sqlserver.sql'),
            default => throw new RuntimeException(
                'El esquema de PlayBoss solo está disponible para MySQL/MariaDB y SQL Server.'
            ),
        };

        DB::unprepared(file_get_contents($schemaPath));
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (self::TABLAS as $tabla) {
            Schema::dropIfExists($tabla);
        }
        Schema::enableForeignKeyConstraints();
    }
};
