<?php

namespace App\Models\Bet;

use App\Models\PostgresModel;

class EstadoApuesta extends PostgresModel
{
    protected $table = 'estado_apuesta';
    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['codigo', 'nombre'];
}
