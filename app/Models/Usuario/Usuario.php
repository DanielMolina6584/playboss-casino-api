<?php

namespace App\Models\Usuario;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;

    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';

    public $timestamps = false;

    protected $fillable = [
        'rol_codigo',
        'estado_codigo',
    ];

    protected function casts(): array
    {
        return ['fecha_registro' => 'datetime'];
    }

    public function credencial(): HasOne
    {
        return $this->hasOne(CredencialUsuario::class, 'usuario_id', 'id_usuario');
    }

    public function perfil(): HasOne
    {
        return $this->hasOne(PerfilUsuario::class, 'usuario_id', 'id_usuario');
    }

    public function getAuthPassword(): string
    {
        return $this->credencial?->contrasena_hash ?? '';
    }

    public function datosPublicos(): array
    {
        $this->loadMissing(['credencial', 'perfil']);
        $perfil = $this->perfil?->attributesToArray() ?? [];
        unset($perfil['usuario_id']);

        return array_merge(
            $this->attributesToArray(),
            $perfil,
            ['correo' => $this->credencial?->correo]
        );
    }
}
