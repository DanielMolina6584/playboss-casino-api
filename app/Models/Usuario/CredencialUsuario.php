<?php

namespace App\Models\Usuario;

use App\Models\SqlServerModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredencialUsuario extends SqlServerModel
{
    protected $table = 'usuario_credencial';
    protected $primaryKey = 'usuario_id';

    public $timestamps = false;
    public $incrementing = false;

    protected $hidden = ['contrasena_hash', 'google_sub'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id_usuario');
    }
}
