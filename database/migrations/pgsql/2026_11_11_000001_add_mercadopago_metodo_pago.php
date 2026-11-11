<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        DB::connection('pgsql')->table('metodo_pago')->updateOrInsert(
            ['codigo' => 'MERCADOPAGO'],
            ['nombre' => 'Mercado Pago', 'activo' => true]
        );
    }

    public function down(): void
    {
        DB::connection('pgsql')->table('metodo_pago')->where('codigo', 'MERCADOPAGO')->delete();
    }
};
