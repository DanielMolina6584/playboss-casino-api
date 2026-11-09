<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('apuesta_detalle', function (Blueprint $table) {
            $table->string('resultado', 3)->nullable(); // GAN / PER / ANU; null = pendiente
        });
        Schema::connection('pgsql')->table('apuesta', function (Blueprint $table) {
            $table->timestampTz('liquidada_en')->nullable();
        });
        DB::connection('pgsql')->table('metodo_pago')->updateOrInsert(
            ['codigo' => 'WOMPI'],
            ['nombre' => 'Wompi', 'activo' => true]
        );
    }

    public function down(): void
    {
        DB::connection('pgsql')->table('metodo_pago')->where('codigo', 'WOMPI')->delete();
        Schema::connection('pgsql')->table('apuesta', fn (Blueprint $t) => $t->dropColumn('liquidada_en'));
        Schema::connection('pgsql')->table('apuesta_detalle', fn (Blueprint $t) => $t->dropColumn('resultado'));
    }
};
