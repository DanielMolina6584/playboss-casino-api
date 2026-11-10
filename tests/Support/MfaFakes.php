<?php

namespace Tests\Support;

use App\Models\Usuario\CredencialUsuario;
use App\Models\Usuario\Usuario;
use App\Service\Auth\Mfa\MfaAlmacen;
use App\Service\Storage\AuditLogService;
use App\Service\SvcUsuario;
use Illuminate\Support\Facades\Hash;

/** Usuario sin base de datos: relaciones precargadas y datosPublicos simple. */
class UsuarioFalso extends Usuario
{
    public static function crear(int $id, string $rol = 'CLIENTE', ?string $contrasena = 'Clave-segura-1', string $correo = null): self
    {
        $u = new self();
        $u->forceFill(['id_usuario' => $id, 'rol_codigo' => $rol, 'estado_codigo' => 'ACT']);
        $c = new CredencialUsuario();
        $c->forceFill(['usuario_id' => $id, 'correo' => $correo ?? "u$id@example.com", 'contrasena_hash' => $contrasena === null ? null : Hash::make($contrasena), 'google_sub' => $contrasena === null ? 'sub-' . $id : null]);
        $u->setRelation('credencial', $c);

        return $u;
    }

    public function datosPublicos(): array
    {
        return ['id_usuario' => $this->id_usuario, 'rol_codigo' => $this->rol_codigo, 'correo' => $this->credencial->correo];
    }

    public function perfilCompleto(): bool
    {
        return true;
    }
}

class MfaAlmacenEnMemoria implements MfaAlmacen
{
    /** @var array<int, array> */
    public array $filas = [];
    /** @var array<int, array<string, bool>> hash => usado */
    public array $recuperacion = [];
    /** @var array<int, UsuarioFalso> */
    public array $usuarios = [];
    public array $sesionesRevocadas = [];
    public int $tokens = 0;

    public function agregar(UsuarioFalso $u): UsuarioFalso
    {
        return $this->usuarios[$u->id_usuario] = $u;
    }

    public function usuario(int $usuarioId): ?Usuario
    {
        return $this->usuarios[$usuarioId] ?? null;
    }

    public function obtener(int $usuarioId): ?array
    {
        return $this->filas[$usuarioId] ?? null;
    }

    public function guardarPendiente(int $usuarioId, string $secretoCifrado): void
    {
        $this->filas[$usuarioId] = ['secreto_cifrado' => $secretoCifrado, 'confirmado_en' => null, 'ultimo_contador' => null];
        $this->recuperacion[$usuarioId] = [];
    }

    public function confirmar(int $usuarioId, int $contador, array $hashesRecuperacion): void
    {
        $this->filas[$usuarioId]['confirmado_en'] = now();
        $this->filas[$usuarioId]['ultimo_contador'] = $contador;
        $this->reemplazarRecuperacion($usuarioId, $hashesRecuperacion);
    }

    public function avanzarContador(int $usuarioId, int $contador): bool
    {
        $ult = $this->filas[$usuarioId]['ultimo_contador'] ?? null;
        if ($ult !== null && $ult >= $contador) {
            return false;
        }
        $this->filas[$usuarioId]['ultimo_contador'] = $contador;

        return true;
    }

    public function reemplazarRecuperacion(int $usuarioId, array $hashes): void
    {
        $this->recuperacion[$usuarioId] = array_fill_keys($hashes, false);
    }

    public function consumirRecuperacion(int $usuarioId, string $hash): bool
    {
        if (($this->recuperacion[$usuarioId][$hash] ?? true) !== false) {
            return false;
        }
        $this->recuperacion[$usuarioId][$hash] = true;

        return true;
    }

    public function recuperacionRestantes(int $usuarioId): int
    {
        return count(array_filter($this->recuperacion[$usuarioId] ?? [], fn ($usado) => !$usado));
    }

    public function eliminar(int $usuarioId): void
    {
        unset($this->filas[$usuarioId], $this->recuperacion[$usuarioId]);
    }

    public function emitirToken(Usuario $usuario): array
    {
        $this->tokens++;

        return ["{$this->tokens}|token-real-{$usuario->id_usuario}", $this->tokens];
    }

    public function revocarSesiones(int $usuarioId, ?int $excepto = null): void
    {
        $this->sesionesRevocadas[] = [$usuarioId, $excepto];
    }
}

class AuditoriaEnMemoria extends AuditLogService
{
    public array $eventos = [];
    public array $autenticaciones = [];

    public function registrarAutenticacion(?int $usuarioId, ?string $correo, bool $exitoso, string $ip, ?string $dispositivo, ?string $motivo = null, ?int $tokenId = null): void
    {
        $this->autenticaciones[] = compact('usuarioId', 'exitoso', 'motivo', 'tokenId');
    }

    public function registrarEvento(string $evento, ?int $usuarioId, array $contexto = []): void
    {
        $this->eventos[] = [$evento, $usuarioId, $contexto];
    }

    public function nombres(): array
    {
        return array_column($this->eventos, 0);
    }
}

/** Login por correo sin SQL Server. */
class SvcUsuarioEnMemoria extends SvcUsuario
{
    public function __construct(private readonly MfaAlmacenEnMemoria $almacen, AuditLogService $audit)
    {
        parent::__construct($audit);
    }

    public function buscarPorCorreo(string $correo): ?Usuario
    {
        foreach ($this->almacen->usuarios as $u) {
            if ($u->credencial->correo === $correo) {
                return $u;
            }
        }

        return null;
    }
}
