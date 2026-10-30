<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('servicio', function (Blueprint $table) {
            $table->string('codigo', 10)->primary();
            $table->string('nombre', 50);
            $table->boolean('activo')->default(true);
        });
        Schema::connection('mysql')->create('pais', function (Blueprint $table) {
            $table->char('codigo_iso', 2)->primary();
            $table->string('nombre', 80);
        });
        Schema::connection('mysql')->create('ciudad', function (Blueprint $table) {
            $table->increments('id_ciudad');
            $table->string('nombre', 80);
            $table->char('pais_codigo', 2);
            $table->foreign('pais_codigo')->references('codigo_iso')->on('pais')->restrictOnDelete();
            $table->index('pais_codigo');
        });
        Schema::connection('mysql')->create('liga', function (Blueprint $table) {
            $table->increments('id_liga');
            $table->string('servicio_codigo', 10);
            $table->char('pais_codigo', 2);
            $table->string('nombre', 80);
            $table->string('logo_drive_id', 255)->nullable();
            $table->boolean('activa')->default(true);
            $table->foreign('servicio_codigo')->references('codigo')->on('servicio')->restrictOnDelete();
            $table->foreign('pais_codigo')->references('codigo_iso')->on('pais')->restrictOnDelete();
            $table->index('servicio_codigo');
            $table->index('pais_codigo');
        });
        Schema::connection('mysql')->create('temporada', function (Blueprint $table) {
            $table->increments('id_temporada');
            $table->unsignedInteger('liga_id');
            $table->string('nombre', 20);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->foreign('liga_id')->references('id_liga')->on('liga')->cascadeOnDelete();
            $table->unique(['liga_id', 'nombre']);
        });
        Schema::connection('mysql')->create('estadio', function (Blueprint $table) {
            $table->increments('id_estadio');
            $table->string('nombre', 100);
            $table->unsignedInteger('ciudad_id');
            $table->unsignedInteger('capacidad')->nullable();
            $table->string('direccion', 200)->nullable();
            $table->foreign('ciudad_id')->references('id_ciudad')->on('ciudad')->restrictOnDelete();
        });
        Schema::connection('mysql')->create('equipo', function (Blueprint $table) {
            $table->increments('id_equipo');
            $table->string('nombre', 80);
            $table->string('escudo_drive_id', 255)->nullable();
            $table->unsignedInteger('ciudad_id');
            $table->unsignedInteger('estadio_id')->nullable();
            $table->date('fecha_fundacion')->nullable();
            $table->foreign('ciudad_id')->references('id_ciudad')->on('ciudad')->restrictOnDelete();
            $table->foreign('estadio_id')->references('id_estadio')->on('estadio')->nullOnDelete();
            $table->index(['ciudad_id', 'estadio_id']);
        });
        Schema::connection('mysql')->create('equipo_liga_temporada', function (Blueprint $table) {
            $table->bigIncrements('id_equipo_liga_temporada');
            $table->unsignedInteger('equipo_id');
            $table->unsignedInteger('temporada_id');
            $table->date('fecha_inscripcion');
            $table->integer('puntos')->default(0);
            $table->unique(['equipo_id', 'temporada_id']);
            $table->foreign('equipo_id')->references('id_equipo')->on('equipo')->cascadeOnDelete();
            $table->foreign('temporada_id')->references('id_temporada')->on('temporada')->cascadeOnDelete();
        });
        Schema::connection('mysql')->create('posicion', function (Blueprint $table) {
            $table->string('codigo', 3)->primary();
            $table->string('nombre', 30);
        });
        Schema::connection('mysql')->create('jugador', function (Blueprint $table) {
            $table->increments('id_jugador');
            $table->string('primer_nombre', 50);
            $table->string('segundo_nombre', 50)->nullable();
            $table->string('primer_apellido', 50)->nullable();
            $table->string('segundo_apellido', 50)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->char('pais_codigo', 2)->nullable();
            $table->string('posicion_codigo', 3)->nullable();
            $table->unsignedInteger('equipo_actual_id')->nullable();
            $table->smallInteger('dorsal')->nullable();
            $table->string('foto_drive_id', 255)->nullable();
            $table->foreign('pais_codigo')->references('codigo_iso')->on('pais')->restrictOnDelete();
            $table->foreign('posicion_codigo')->references('codigo')->on('posicion')->restrictOnDelete();
            $table->foreign('equipo_actual_id')->references('id_equipo')->on('equipo')->nullOnDelete();
        });
        Schema::connection('mysql')->create('jornada', function (Blueprint $table) {
            $table->increments('id_jornada');
            $table->unsignedInteger('temporada_id');
            $table->unsignedSmallInteger('numero');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->foreign('temporada_id')->references('id_temporada')->on('temporada')->cascadeOnDelete();
            $table->unique(['temporada_id', 'numero']);
        });
        Schema::connection('mysql')->create('estado_partido', function (Blueprint $table) {
            $table->string('codigo', 4)->primary();
            $table->string('nombre', 30);
        });
        Schema::connection('mysql')->create('partido', function (Blueprint $table) {
            $table->bigIncrements('id_partido');
            $table->unsignedInteger('jornada_id');
            $table->unsignedInteger('equipo_local_id');
            $table->unsignedInteger('equipo_visitante_id');
            $table->unsignedInteger('estadio_id')->nullable();
            $table->timestamp('fecha_hora');
            $table->string('estado_codigo', 4);
            $table->unsignedSmallInteger('goles_local')->nullable();
            $table->unsignedSmallInteger('goles_visitante')->nullable();
            $table->foreign('jornada_id')->references('id_jornada')->on('jornada')->cascadeOnDelete();
            $table->foreign('equipo_local_id')->references('id_equipo')->on('equipo')->restrictOnDelete();
            $table->foreign('equipo_visitante_id')->references('id_equipo')->on('equipo')->restrictOnDelete();
            $table->foreign('estadio_id')->references('id_estadio')->on('estadio')->nullOnDelete();
            $table->foreign('estado_codigo')->references('codigo')->on('estado_partido')->restrictOnDelete();
            $table->index(['fecha_hora', 'estado_codigo']);
        });
        Schema::connection('mysql')->create('alineacion', function (Blueprint $table) {
            $table->bigIncrements('id_alineacion');
            $table->unsignedBigInteger('partido_id');
            $table->unsignedInteger('jugador_id');
            $table->unsignedInteger('equipo_id');
            $table->boolean('titular')->default(true);
            $table->unsignedSmallInteger('minuto_entra')->nullable();
            $table->unsignedSmallInteger('minuto_sale')->nullable();
            $table->unique(['partido_id', 'jugador_id']);
            $table->foreign('partido_id')->references('id_partido')->on('partido')->cascadeOnDelete();
            $table->foreign('jugador_id')->references('id_jugador')->on('jugador')->cascadeOnDelete();
            $table->foreign('equipo_id')->references('id_equipo')->on('equipo')->cascadeOnDelete();
        });

        DB::connection('mysql')->table('servicio')->updateOrInsert(['codigo' => 'FUTBOL'], ['nombre' => 'Fútbol', 'activo' => true]);
        foreach ([
            ['codigo' => 'PROG', 'nombre' => 'Programado'],
            ['codigo' => 'ENJ', 'nombre' => 'En juego'],
            ['codigo' => 'FIN', 'nombre' => 'Finalizado'],
            ['codigo' => 'SUSP', 'nombre' => 'Suspendido'],
        ] as $estado) {
            DB::connection('mysql')->table('estado_partido')->updateOrInsert(['codigo' => $estado['codigo']], ['nombre' => $estado['nombre']]);
        }
    }

    public function down(): void
    {
        foreach (['alineacion', 'partido', 'estado_partido', 'jornada', 'jugador', 'posicion', 'equipo_liga_temporada', 'equipo', 'estadio', 'temporada', 'liga', 'ciudad', 'pais', 'servicio'] as $table) {
            Schema::connection('mysql')->dropIfExists($table);
        }
    }
};
