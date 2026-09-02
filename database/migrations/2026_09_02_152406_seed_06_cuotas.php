<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta database/sql/seeds/06_cuotas.sql — cuotas (1X2, doble oportunidad,
 * más/menos 2.5, ambos anotan) para los partidos sembrados en
 * 05_calendario.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/seeds/06_cuotas.sql')));
    }

    public function down(): void
    {
        // No se revierte automáticamente (apuesta_detalle depende de esto).
    }
};
