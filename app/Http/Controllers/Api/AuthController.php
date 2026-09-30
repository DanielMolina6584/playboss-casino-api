<?php

namespace App\Http\Controllers\Api;

use App\Service\SvcUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(private readonly SvcUsuario $svcUsuario)
    {
        parent::__construct();
    }

    /**
     * Registra un nuevo usuario y retorna un token de acceso.
     */
    public function registro(): JsonResponse
    {
        $this->setRequestValidationRules([
            'primer_nombre'         => 'required|string|max:50',
            'segundo_nombre'        => 'nullable|string|max:50',
            'primer_apellido'       => 'required|string|max:50',
            'segundo_apellido'      => 'nullable|string|max:50',
            'tipo_documento_codigo' => 'required|string|exists:tipo_documento,codigo',
            'numero_documento'      => [
                'required',
                'string',
                'max:20',
                Rule::unique('usuario_perfil', 'numero_documento')
                    ->where(fn ($query) => $query->where(
                        'tipo_documento_codigo',
                        $this->getDataBody('tipo_documento_codigo')
                    )),
            ],
            'correo'                => 'required|email|max:120|unique:usuario_credencial,correo',
            'celular'               => 'nullable|string|max:20',
            'fecha_nacimiento'      => 'required|date',
            'contrasena'            => 'required|string|min:8|confirmed',
        ]);

        if (!$this->validateRequestRules()) {
            return $this->sendResponse(422);
        }

        $usuario = $this->svcUsuario->registrar($this->getRequestData());

        if (!$usuario) {
            $this->agregarError('No fue posible registrar el usuario.');
            return $this->sendResponse(500);
        }

        $nuevoToken = $usuario->createToken('playboss-api');

        $this->svcUsuario->registrarIntentoLogin(
            $usuario->id_usuario,
            true,
            $this->getIp(),
            $this->request->userAgent(),
            null,
            $nuevoToken->accessToken->id
        );

        $this->setDataResponse($usuario->datosPublicos(), 'usuario');
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
            && $this->svcUsuario->verificarContrasena($usuarioEncontrado, $this->getDataBody('contrasena'));

        if (!$credencialesValidas) {
            if ($usuarioEncontrado) {
                $this->svcUsuario->registrarIntentoLogin(
                    $usuarioEncontrado->id_usuario,
                    false,
                    $this->getIp(),
                    $this->request->userAgent(),
                    'Contraseña incorrecta'
                );
            }
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
            $nuevoToken->accessToken->id
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
