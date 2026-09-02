<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoDocumentoSeeder extends Seeder
{
    /**
     * Códigos base de tipo_documento (máx 5 caracteres por columna).
     */
    public function run(): void
    {
        $tipos = [
            ['codigo' => 'CC', 'nombre' => 'Cédula de ciudadanía'],
            ['codigo' => 'CE', 'nombre' => 'Cédula de extranjería'],
            ['codigo' => 'TI', 'nombre' => 'Tarjeta de identidad'],
            ['codigo' => 'PA', 'nombre' => 'Pasaporte'],
            ['codigo' => 'NIT', 'nombre' => 'NIT'],
        ];

        foreach ($tipos as $tipo) {
            DB::table('tipo_documento')->updateOrInsert(['codigo' => $tipo['codigo']], $tipo);
        }
    }
}
