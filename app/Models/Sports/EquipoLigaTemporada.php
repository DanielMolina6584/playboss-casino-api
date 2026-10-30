<?php

namespace App\Models\Sports;

use App\Models\MysqlModel;

class EquipoLigaTemporada extends MysqlModel
{
    protected $table = 'equipo_liga_temporada';
    protected $primaryKey = 'id_equipo_liga_temporada';
    protected $fillable = ['equipo_id', 'temporada_id', 'fecha_inscripcion', 'puntos'];
    protected function casts(): array { return ['fecha_inscripcion' => 'date']; }
}
