<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fuente de verdad del esquema de negocio de playboss (32 tablas): ejecuta
 * database/sql/playboss-schema.sql tal cual. En un entorno nuevo (migrate:fresh)
 * esto crea el esquema completo. Aquí ya existe, así que se omite (hasTable)
 * y no se toca ninguna tabla ni dato real.
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
        'jornada', 'codigo_recuperacion', 'login', 'cuenta', 'transaccion',
        'metodo_pago', 'usuario', 'estado_usuario', 'rol', 'tipo_documento',
        'jugador_equipo_historico', 'jugador', 'posicion',
        'equipo_liga_temporada', 'equipo', 'estadio', 'temporada', 'liga',
        'servicio', 'ciudad', 'pais', 'empresa',
    ];

    public function up(): void
    {
        if (Schema::hasTable('usuario')) {
            return;
        }

        DB::unprepared(file_get_contents(database_path('sql/playboss-schema.sql')));
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
