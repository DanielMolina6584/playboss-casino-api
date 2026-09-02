<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Siembra database/sql/seeds/09_usuarios_demo.sql — usuarios y billeteras de
 * demostración (solo dev/QA, no producción). Requiere que tipo_documento,
 * rol y estado_usuario ya estén sembrados (seeders de Laravel). INSERT
 * IGNORE por correo/usuario_id: re-ejecutar es seguro.
 */
return new class extends Migration
{
    private const CORREOS_DEMO = [
        'admin@playboss.com',
        'valentina.ramirez@playboss.demo',
        'santiago.torres@playboss.demo',
        'camila.rodriguez@playboss.demo',
        'juanpablo.hernandez@playboss.demo',
    ];

    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/seeds/09_usuarios_demo.sql')));
    }

    public function down(): void
    {
        DB::table('cuenta')
            ->whereIn('usuario_id', DB::table('usuario')->whereIn('correo', self::CORREOS_DEMO)->pluck('id_usuario'))
            ->delete();
        DB::table('usuario')->whereIn('correo', self::CORREOS_DEMO)->delete();
    }
};
