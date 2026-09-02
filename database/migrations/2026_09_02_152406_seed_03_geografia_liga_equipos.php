<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta database/sql/seeds/03_geografia_liga_equipos.sql — ciudades,
 * estadios, la liga colombiana, la temporada 2026-I y sus equipos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/seeds/03_geografia_liga_equipos.sql')));
    }

    public function down(): void
    {
        // Datos de referencia deportiva: no se revierten automáticamente,
        // pueden tener jugadores/partidos/cuotas dependientes para cuando
        // se ejecute el rollback.
    }
};
