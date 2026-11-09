<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complementaria al esquema base: enlaza cada registro exitoso de "logs"
 * con el token de Sanctum (personal_access_tokens) que se emitió en ese
 * inicio de sesión, para poder auditar/revocar sesiones desde el listado
 * de logins sin tener que guardar el token en texto plano.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tableName = Schema::hasTable('logs') ? 'logs' : 'login';

        if (Schema::hasColumn($tableName, 'personal_access_token_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->unsignedBigInteger('personal_access_token_id')->nullable()->after('motivo_fallo');

            $table->foreign('personal_access_token_id')
                ->references('id')->on('personal_access_tokens')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        $tableName = Schema::hasTable('logs') ? 'logs' : 'login';

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropForeign(['personal_access_token_id']);
            $table->dropColumn('personal_access_token_id');
        });
    }
};
