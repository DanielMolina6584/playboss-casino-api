<?php

namespace App\Models\Sports;

use App\Models\MysqlModel;

class Posicion extends MysqlModel
{
    protected $table = 'posicion';
    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['codigo', 'nombre'];
}
