<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Http\Requests\CambiarEstadoUsuarioRequest;
use App\Http\Requests\ListadoRequest;
use App\Models\Usuario\Usuario;
use App\Service\Auth\Mfa\SvcMfa;
use App\Service\Storage\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class UsuarioAdminController extends Controller
{
    use RespondsJson;

    public function __construct(private readonly AuditLogService $audit, private readonly SvcMfa $mfa)
    {
    }

    public function index(ListadoRequest $r): JsonResponse
    {
        $q = Usuario::query()->with(['credencial', 'perfil'])->orderByDesc('id_usuario');
        $r->filled('estado') && $q->where('estado_codigo', $r->string('estado'));
        $r->filled('rol') && $q->where('rol_codigo', $r->string('rol'));
        $r->filled('desde') && $q->where('fecha_registro', '>=', $r->date('desde')->startOfDay());
        $r->filled('hasta') && $q->where('fecha_registro', '<=', $r->date('hasta')->endOfDay());
        if ($r->filled('q')) {
            $like = '%' . addcslashes($r->string('q'), '%_\\[') . '%';
            $q->where(fn ($w) => $w
                ->whereHas('credencial', fn ($c) => $c->where('correo', 'like', $like))
                ->orWhereHas('perfil', fn ($p) => $p->where('primer_nombre', 'like', $like)
                    ->orWhere('primer_apellido', 'like', $like)
                    ->orWhere('numero_documento', 'like', $like)));
        }

        return $this->paginated($q->paginate($r->porPagina()), fn (Usuario $u) => self::presentar($u));
    }

    public function cambiarEstado(CambiarEstadoUsuarioRequest $r, int $id): JsonResponse
    {
        $admin = $r->user();
        if ((int) $admin->getAuthIdentifier() === $id) {
            throw new ReglaNegocioException('No puedes cambiar el estado de tu propia cuenta.', 422);
        }
        $usuario = Usuario::query()->with(['credencial', 'perfil'])->find($id);
        if (!$usuario) {
            throw new ReglaNegocioException('Usuario no encontrado.', 404);
        }

        $estado = (string) $r->input('estado');
        $previo = $usuario->estado_codigo;
        if ($previo !== $estado) {
            $nombres = ['ACT' => 'Activo', 'BLQ' => 'Bloqueado', 'SUS' => 'Suspendido'];
            // El catálogo de estados solo trae ACT de origen; asegura que exista el destino (FK).
            DB::connection('sqlsrv')->table('estado_usuario')->updateOrInsert(['codigo' => $estado], ['nombre' => $nombres[$estado], 'descripcion' => 'Cuenta ' . mb_strtolower($nombres[$estado])]);
            $usuario->estado_codigo = $estado;
            $usuario->save();
            if ($estado !== 'ACT') {
                $usuario->tokens()->delete(); // cierra todas las sesiones abiertas
            }
            $this->audit->registrarEvento('admin.user_status_changed', (int) $admin->getAuthIdentifier(), [
                'usuario_id' => $id, 'de' => $previo, 'a' => $estado, 'motivo' => $r->input('motivo'),
            ]);
        }

        return $this->ok(self::presentar($usuario));
    }

    /** Quita el 2FA de un usuario (perdió el teléfono y los códigos de recuperación) y cierra sus sesiones. */
    public function restablecerMfa(Request $r, int $id): JsonResponse
    {
        $r->validate(['motivo' => ['required', 'string', 'min:5', 'max:300']]);
        if ((int) $r->user()->getAuthIdentifier() === $id) {
            throw new ReglaNegocioException('No puedes restablecer tu propio 2FA; pídeselo a otro administrador.', 422);
        }
        $tenia = $this->mfa->restablecer($id, (int) $r->user()->getAuthIdentifier(), (string) $r->input('motivo'), $r->ip());

        return $this->ok(['usuario_id' => $id, 'tenia_mfa' => $tenia], 200, [$tenia ? 'Autenticación en dos pasos restablecida; el usuario debe volver a iniciar sesión.' : 'El usuario no tenía 2FA activo; se cerraron sus sesiones.']);
    }

    /** Sin contraseña, tokens ni datos de contacto completos: documento enmascarado. */
    public static function presentar(Usuario $u): array
    {
        $p = $u->perfil;
        $documento = (string) ($p?->numero_documento ?? '');

        return [
            'id_usuario' => $u->id_usuario,
            'rol_codigo' => $u->rol_codigo,
            'estado_codigo' => $u->estado_codigo,
            'fecha_registro' => $u->fecha_registro,
            'correo' => $u->credencial?->correo,
            'nombre' => $p ? trim(implode(' ', array_filter([$p->primer_nombre, $p->segundo_nombre, $p->primer_apellido, $p->segundo_apellido]))) : null,
            'tipo_documento_codigo' => $p?->tipo_documento_codigo,
            'numero_documento' => $documento === '' ? null : str_repeat('*', max(0, strlen($documento) - 3)) . substr($documento, -3),
        ];
    }
}
