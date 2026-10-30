<?php

namespace App\Models\Sports;

use App\Models\MysqlModel;

class Alineacion extends MysqlModel
{
    protected $table = 'alineacion';
    protected $primaryKey = 'id_alineacion';
    protected $fillable = ['partido_id', 'jugador_id', 'equipo_id', 'titular', 'minuto_entra', 'minuto_sale'];
    protected function casts(): array { return ['titular' => 'boolean']; }
}
