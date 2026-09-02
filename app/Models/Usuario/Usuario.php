<?php

namespace App\Models\Usuario;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;

    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';

    public $timestamps = false;

    protected $fillable = [
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'tipo_documento_codigo',
        'numero_documento',
        'rol_codigo',
        'correo',
        'celular',
        'fecha_nacimiento',
        'estado_codigo',
        'fecha_registro',
    ];

    protected $hidden = [
        'contrasena_hash',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_registro' => 'datetime',
        ];
    }

    /**
     * Requerido por el contrato Authenticatable: la columna de contraseña real es "contrasena_hash".
     */
    public function getAuthPassword(): string
    {
        return $this->contrasena_hash;
    }
}
