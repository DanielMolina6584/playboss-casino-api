<?php

namespace App\Models\Usuario;

use App\Models\SqlServerModel;

class EstadoUsuario extends SqlServerModel
{
    protected $table = 'estado_usuario';
    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $fillable = ['codigo', 'nombre', 'descripcion'];
}
