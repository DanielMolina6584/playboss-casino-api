<?php

namespace App\Models\Usuario;

use App\Models\SqlServerAuthenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends SqlServerAuthenticatable
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

    protected $hidden = ['remember_token'];

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

    public function mfa(): HasOne
    {
        return $this->hasOne(UsuarioMfa::class, 'usuario_id', 'id_usuario');
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_codigo', 'codigo');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoUsuario::class, 'estado_codigo', 'codigo');
    }

    public function getAuthPassword(): string
    {
        return $this->credencial?->contrasena_hash ?? '';
    }

    public function estaActivo(): bool
    {
        return $this->estado_codigo === 'ACT';
    }

    /** Datos obligatorios para apostar/depositar; un usuario creado con Google nace sin ellos. */
    public function perfilCompleto(): bool
    {
        $this->loadMissing('perfil');
        $p = $this->perfil;

        return $p && $p->fecha_nacimiento !== null && $p->tipo_documento_codigo && $p->numero_documento && $p->primer_apellido;
    }

    public function tieneRol(string $codigo): bool
    {
        return $this->rol_codigo === $codigo;
    }

    public function datosPublicos(): array
    {
        $this->loadMissing(['credencial', 'perfil']);
        $perfil = $this->perfil?->attributesToArray() ?? [];
        unset($perfil['usuario_id']);

        return array_merge(
            $this->attributesToArray(),
            $perfil,
            ['correo' => $this->credencial?->correo, 'auth_proveedor' => $this->credencial?->auth_proveedor ?? 'LOCAL', 'perfil_completo' => $this->perfilCompleto(), 'mfa_activo' => config('mfa.enabled') && $this->loadMissing('mfa')->mfa?->confirmado_en !== null, 'mfa_obligatorio' => config('mfa.enabled') && in_array($this->rol_codigo, (array) config('mfa.required_roles'), true)]
        );
    }
}
