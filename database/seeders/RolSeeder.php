<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolSeeder extends Seeder
{
    /**
     * Códigos base de rol. Ajustables a futuro según necesidades de negocio.
     */
    public function run(): void
    {
        $roles = [
            ['codigo' => 'CLIENTE', 'nombre' => 'Cliente', 'descripcion' => 'Usuario final que realiza apuestas'],
            ['codigo' => 'ADMIN', 'nombre' => 'Administrador', 'descripcion' => 'Usuario con acceso administrativo'],
        ];

        foreach ($roles as $rol) {
            DB::table('rol')->updateOrInsert(['codigo' => $rol['codigo']], $rol);
        }
    }
}
