<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta database/sql/seeds/07_historico_alineaciones.sql —
 * jugador_equipo_historico (vínculo jugador/equipo) y alineacion
 * (titulares/suplentes por partido).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/seeds/07_historico_alineaciones.sql')));
    }

    public function down(): void
    {
        // No se revierte automáticamente.
    }
};
