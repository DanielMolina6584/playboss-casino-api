<?php

namespace App\Models\Usuario;

use App\Models\SqlServerModel;

class TipoDocumento extends SqlServerModel
{
    protected $table = 'tipo_documento';
    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $fillable = ['codigo', 'nombre'];
}
