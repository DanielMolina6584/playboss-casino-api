<?php

namespace App\Http\Controllers\Api;

use App\Service\SvcFotoPerfil;
use App\Service\SvcUsuario;
use App\Models\Usuario\CredencialUsuario;
use App\Models\Usuario\PerfilUsuario;
use App\Models\Usuario\TipoDocumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(
        private readonly SvcUsuario $svcUsuario,
        private readonly SvcFotoPerfil $svcFotoPerfil
    ) {
        parent::__construct();
    }

    /**
     * Registra un nuevo usuario con su foto de perfil (se guarda en Google Drive) y retorna un token de acceso.
     */
    public function registro(): JsonResponse
    {
        $this->setRequestValidationRules([
            'primer_nombre'         => 'required|string|max:50',
            'segundo_nombre'        => 'nullable|string|max:50',
            'primer_apellido'       => 'required|string|max:50',
            'segundo_apellido'      => 'nullable|string|max:50',
            'tipo_documento_codigo' => ['required', 'string', Rule::exists(TipoDocumento::class, 'codigo')],
            'numero_documento'      => [
                'required',
                'string',
                'max:20',
                Rule::unique(PerfilUsuario::class, 'numero_documento')
                    ->where(fn ($query) => $query->where(
                        'tipo_documento_codigo',
                        $this->getDataBody('tipo_documento_codigo')
                    )),
            ],
            'correo'                => ['required', 'email', 'max:120', Rule::unique(CredencialUsuario::class, 'correo')],
            'celular'               => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s\-]{7,20}$/'],
            'fecha_nacimiento'      => 'required|date',
            'contrasena'            => 'required|string|min:8|confirmed',
            'foto'                  => ['required', ...SvcFotoPerfil::REGLAS],
        ]);

        if (!$this->validateRequestRules()) {
            return $this->sendResponse(422);
        }

        $usuario = $this->svcUsuario->registrar($this->getRequestData());

        if (!$usuario) {
            $this->agregarError('No fue posible registrar el usuario.');
            return $this->sendResponse(500);
        }

        if (!$this->svcFotoPerfil->guardar($usuario->id_usuario, $this->request->file('foto'))) {
            $this->svcUsuario->eliminar($usuario->id_usuario);
            $this->agregarError('No pudimos guardar tu foto de perfil. Intenta de nuevo.');
            return $this->sendResponse(502);
        }

        $nuevoToken = $usuario->createToken('playboss-api');

        $this->svcUsuario->registrarIntentoLogin(
            $usuario->id_usuario,
            true,
            $this->getIp(),
            $this->request->userAgent(),
            null,
            $nuevoToken->accessToken->id,
            $this->getDataBody('correo')
        );

        $this->setDataResponse($usuario->fresh()->datosPublicos(), 'usuario');
        $this->setDataResponse($nuevoToken->plainTextToken, 'token');
        $this->respSinError();
        return $this->sendResponse(201);
    }

    /**
     * Valida credenciales y retorna un token de acceso.
     */
    public function login(): JsonResponse
    {
        $this->setRequestValidationRules([
            'correo'     => 'required|email',
            'contrasena' => 'required|string',
        ]);

        if (!$this->validateRequestRules()) {
            return $this->sendResponse(422);
        }

        $correo = $this->getDataBody('correo');
        $usuarioEncontrado = $this->svcUsuario->buscarPorCorreo($correo);
        $credencialesValidas = $usuarioEncontrado
            && $usuarioEncontrado->estaActivo()
            && $this->svcUsuario->verificarContrasena($usuarioEncontrado, $this->getDataBody('contrasena'));

        if (!$credencialesValidas) {
            $this->svcUsuario->registrarIntentoLogin(
                $usuarioEncontrado?->id_usuario,
                false,
                $this->getIp(),
                $this->request->userAgent(),
                !$usuarioEncontrado ? 'Usuario no encontrado' : (!$usuarioEncontrado->estaActivo() ? 'Cuenta inactiva' : 'Contraseña incorrecta'),
                null,
                $correo
            );
            $this->agregarError('Correo o contraseña incorrectos.');
            return $this->sendResponse(401);
        }

        $nuevoToken = $usuarioEncontrado->createToken('playboss-api');

        $this->svcUsuario->registrarIntentoLogin(
            $usuarioEncontrado->id_usuario,
            true,
            $this->getIp(),
            $this->request->userAgent(),
            null,
            $nuevoToken->accessToken->id,
            $correo
        );

        $this->setDataResponse($usuarioEncontrado->datosPublicos(), 'usuario');
        $this->setDataResponse($nuevoToken->plainTextToken, 'token');
        $this->respSinError();
        return $this->sendResponse();
    }

    /**
     * Revoca el token actual del usuario autenticado.
     */
    public function logout(): JsonResponse
    {
        $this->request->user()->currentAccessToken()->delete();

        $this->respSinError();
        return $this->sendResponse();
    }

    /**
     * Retorna los datos del usuario autenticado.
     */
    public function me(): JsonResponse
    {
        $this->setDataResponse($this->request->user()->datosPublicos(), 'usuario');
        $this->respSinError();
        return $this->sendResponse();
    }
}
