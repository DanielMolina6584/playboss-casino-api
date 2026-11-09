<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Id en Google Drive de la foto de perfil del usuario. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('usuario_perfil', function (Blueprint $table) {
            $table->string('foto_drive_id', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('usuario_perfil', function (Blueprint $table) {
            $table->dropColumn('foto_drive_id');
        });
    }
};
