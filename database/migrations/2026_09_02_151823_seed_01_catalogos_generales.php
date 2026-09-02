<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta database/sql/seeds/01_catalogos_generales.sql (país, posición,
 * estado_partido, estado_apuesta, servicio, mercado, metodo_pago,
 * regla_apuesta). El .sql usa INSERT IGNORE, así que reejecutarlo a mano
 * es seguro; esta migración corre una sola vez vía el tracking normal
 * de Laravel.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/seeds/01_catalogos_generales.sql')));
    }

    public function down(): void
    {
        // Datos de catálogo: no se revierten automáticamente porque para
        // cuando se ejecute el rollback pueden existir filas dependientes
        // (equipo, jugador, partido, apuesta...) que los referencian.
    }
};
