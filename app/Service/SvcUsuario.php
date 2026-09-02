<?php

namespace App\Service;

use App\Models\Usuario\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SvcUsuario
{
    /**
     * Rol y estado por defecto asignados a un usuario que se auto-registra.
     */
    private const ROL_DEFECTO = 'CLIENTE';
    private const ESTADO_DEFECTO = 'ACT';

    /**
     * Registra un nuevo usuario con clave hasheada.
     * @param array $datos
     * @return Usuario|null
     */
    public function registrar(array $datos): ?Usuario
    {
        try {
            $usuario = new Usuario();
            $usuario->fill([
                'primer_nombre'         => $datos['primer_nombre'],
                'segundo_nombre'        => $datos['segundo_nombre'] ?? null,
                'primer_apellido'       => $datos['primer_apellido'],
                'segundo_apellido'      => $datos['segundo_apellido'] ?? null,
                'tipo_documento_codigo' => $datos['tipo_documento_codigo'],
                'numero_documento'      => $datos['numero_documento'],
                'rol_codigo'            => $datos['rol_codigo'] ?? self::ROL_DEFECTO,
                'correo'                => $datos['correo'],
                'celular'               => $datos['celular'] ?? null,
                'fecha_nacimiento'      => $datos['fecha_nacimiento'],
                'estado_codigo'         => $datos['estado_codigo'] ?? self::ESTADO_DEFECTO,
            ]);
            $usuario->contrasena_hash = Hash::make($datos['contrasena']);
            $usuario->save();

            return $usuario;
        } catch (\Exception $err) {
            Log::channel('single')->error('SvcUsuario::registrar - ' . $err->getMessage());
            return null;
        }
    }

    /**
     * Busca un usuario por correo, sin validar credenciales.
     * @param string $correo
     * @return Usuario|null
     */
    public function buscarPorCorreo(string $correo): ?Usuario
    {
        try {
            return Usuario::where('correo', $correo)->first();
        } catch (\Exception $err) {
            Log::channel('single')->error('SvcUsuario::buscarPorCorreo - ' . $err->getMessage());
            return null;
        }
    }

    /**
     * Verifica la contraseña en texto plano contra el hash almacenado.
     * @param Usuario $usuario
     * @param string $contrasena
     * @return bool
     */
    public function verificarContrasena(Usuario $usuario, string $contrasena): bool
    {
        return Hash::check($contrasena, $usuario->contrasena_hash);
    }

    /**
     * Valida credenciales de acceso y retorna el usuario si son correctas.
     * @param string $correo
     * @param string $contrasena
     * @return Usuario|null
     */
    public function autenticar(string $correo, string $contrasena): ?Usuario
    {
        $usuario = $this->buscarPorCorreo($correo);

        if (!$usuario || !Hash::check($contrasena, $usuario->contrasena_hash)) {
            return null;
        }

        return $usuario;
    }

    /**
     * Registra un intento de acceso en la tabla "login" (auditoría de sesiones).
     * Solo se registra cuando existe un usuario_id válido, ya que la tabla
     * exige la relación con "usuario".
     * @param int $usuarioId
     * @param bool $exitoso
     * @param string $ip
     * @param string|null $dispositivo
     * @param string|null $motivoFallo
     * @param int|null $tokenId id de personal_access_tokens emitido en este login
     * @return void
     */
    public function registrarIntentoLogin(
        int $usuarioId,
        bool $exitoso,
        string $ip,
        ?string $dispositivo = null,
        ?string $motivoFallo = null,
        ?int $tokenId = null
    ): void {
        try {
            DB::table('login')->insert([
                'usuario_id'                => $usuarioId,
                'fecha_hora'                => now(),
                'ip_origen'                 => $ip,
                'dispositivo'               => $dispositivo,
                'exitoso'                   => $exitoso,
                'motivo_fallo'              => $motivoFallo,
                'personal_access_token_id'  => $tokenId,
            ]);
        } catch (\Exception $err) {
            Log::channel('single')->error('SvcUsuario::registrarIntentoLogin - ' . $err->getMessage());
        }
    }
}
