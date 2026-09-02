<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EstadoUsuarioSeeder extends Seeder
{
    /**
     * Códigos base de estado_usuario (máx 5 caracteres por columna).
     */
    public function run(): void
    {
        $estados = [
            ['codigo' => 'ACT', 'nombre' => 'Activo', 'descripcion' => 'Usuario habilitado para operar'],
            ['codigo' => 'INA', 'nombre' => 'Inactivo', 'descripcion' => 'Usuario deshabilitado temporalmente'],
            ['codigo' => 'BLQ', 'nombre' => 'Bloqueado', 'descripcion' => 'Usuario bloqueado por incumplimiento o fraude'],
        ];

        foreach ($estados as $estado) {
            DB::table('estado_usuario')->updateOrInsert(['codigo' => $estado['codigo']], $estado);
        }
    }
}
