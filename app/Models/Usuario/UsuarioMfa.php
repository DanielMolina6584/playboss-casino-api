<?php

namespace App\Models\Usuario;

use App\Models\SqlServerModel;

class UsuarioMfa extends SqlServerModel
{
    protected $table = 'usuario_mfa';
    protected $primaryKey = 'usuario_id';
    public $incrementing = false;
    protected $hidden = ['secreto_cifrado'];
    protected $fillable = ['usuario_id', 'secreto_cifrado', 'confirmado_en', 'ultimo_contador', 'creado_en'];

    protected function casts(): array
    {
        return ['confirmado_en' => 'datetime', 'creado_en' => 'datetime', 'ultimo_contador' => 'integer'];
    }
}
