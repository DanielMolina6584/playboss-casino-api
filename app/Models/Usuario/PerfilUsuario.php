<?php

namespace App\Models\Usuario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerfilUsuario extends Model
{
    protected $table = 'usuario_perfil';
    protected $primaryKey = 'usuario_id';

    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'tipo_documento_codigo',
        'numero_documento',
        'celular',
        'fecha_nacimiento',
    ];

    protected function casts(): array
    {
        return ['fecha_nacimiento' => 'date'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id_usuario');
    }
}
