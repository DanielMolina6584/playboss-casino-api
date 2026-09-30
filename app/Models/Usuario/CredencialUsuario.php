<?php

namespace App\Models\Usuario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredencialUsuario extends Model
{
    protected $table = 'usuario_credencial';
    protected $primaryKey = 'usuario_id';

    public $timestamps = false;
    public $incrementing = false;

    protected $hidden = ['contrasena_hash'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id_usuario');
    }
}
