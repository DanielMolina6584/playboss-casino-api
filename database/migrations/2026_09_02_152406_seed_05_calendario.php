<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta database/sql/seeds/05_calendario.sql — jornadas y partidos de
 * la temporada 2026-I.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/seeds/05_calendario.sql')));
    }

    public function down(): void
    {
        // No se revierte automáticamente (cuotas/apuestas dependen de esto).
    }
};
