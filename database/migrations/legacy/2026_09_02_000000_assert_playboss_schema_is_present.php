<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Se ejecuta antes de importar el esquema. Una base vacía es válida; si ya hay
 * alguna tabla central del dominio, todas deben tener la forma de PlayBoss.
 * Así una tabla `usuario` de otra aplicación no se toma por el esquema propio.
 */
return new class extends Migration
{
    private const COLUMNAS_ESPERADAS = [
        'usuario' => ['id_usuario', 'rol_codigo', 'estado_codigo'],
        'liga' => ['id_liga'],
        'equipo' => ['id_equipo'],
        'partido' => ['id_partido'],
        'apuesta' => ['id_apuesta'],
    ];

    public function up(): void
    {
        $hayEsquemaPrevio = collect(array_keys(self::COLUMNAS_ESPERADAS))
            ->contains(fn (string $tabla) => Schema::hasTable($tabla));

        if (!$hayEsquemaPrevio) {
            return;
        }

        foreach (self::COLUMNAS_ESPERADAS as $tabla => $columnas) {
            if (!Schema::hasTable($tabla)) {
                throw new RuntimeException(
                    "El esquema existente parece parcial o pertenece a otra aplicación: falta '{$tabla}'. " .
                    'Configura una base vacía y exclusiva para PlayBoss.'
                );
            }

            foreach ($columnas as $columna) {
                if (!Schema::hasColumn($tabla, $columna)) {
                    throw new RuntimeException(
                        "La tabla '{$tabla}' existe, pero no corresponde al esquema PlayBoss (falta '{$columna}'). " .
                        'Configura una base exclusiva para PlayBoss antes de continuar.'
                    );
                }
            }
        }
    }

    public function down(): void
    {
        // Comprobación sin cambios de esquema.
    }
};
