<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta database/sql/seeds/02_empresa.sql (la fila única del operador).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/seeds/02_empresa.sql')));
    }

    public function down(): void
    {
        DB::table('empresa')->where('nit', '900123456-7')->delete();
    }
};
