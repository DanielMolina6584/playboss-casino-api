<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta database/sql/seeds/04_jugadores.sql — planteles de los equipos
 * sembrados en 03_geografia_liga_equipos.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/seeds/04_jugadores.sql')));
    }

    public function down(): void
    {
        // No se revierte automáticamente (alineaciones/histórico dependen de esto).
    }
};
