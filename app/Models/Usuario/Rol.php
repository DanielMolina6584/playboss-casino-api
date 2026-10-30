<?php

namespace App\Models\Usuario;

use App\Models\SqlServerModel;

class Rol extends SqlServerModel
{
    protected $table = 'rol';
    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $fillable = ['codigo', 'nombre', 'descripcion'];
}
