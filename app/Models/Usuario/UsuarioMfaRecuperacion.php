<?php

namespace App\Models\Usuario;

use App\Models\SqlServerModel;

class UsuarioMfaRecuperacion extends SqlServerModel
{
    protected $table = 'usuario_mfa_recuperacion';
    protected $primaryKey = 'id_codigo';
    protected $hidden = ['codigo_hash'];
    protected $fillable = ['usuario_id', 'codigo_hash', 'usado_en', 'creado_en'];

    protected function casts(): array
    {
        return ['usado_en' => 'datetime', 'creado_en' => 'datetime'];
    }
}
