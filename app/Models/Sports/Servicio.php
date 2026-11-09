<?php

namespace App\Models\Sports;

use App\Models\MysqlModel;

class Servicio extends MysqlModel
{
    protected $table = 'servicio';
    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['codigo', 'nombre', 'activo'];
}
