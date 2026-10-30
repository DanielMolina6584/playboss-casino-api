<?php

namespace App\Models\Sports;

use App\Models\MysqlModel;

class EstadoPartido extends MysqlModel
{
    protected $table = 'estado_partido';
    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['codigo', 'nombre'];
}
