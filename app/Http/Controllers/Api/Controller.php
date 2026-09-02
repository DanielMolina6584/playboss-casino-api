<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;

class Controller extends BaseController
{
    public array $respuesta = ['error' => 1, 'mensaje' => [], 'data' => []];
    protected array $requestData = [];
    protected string $ip = "";
    protected array $validationRules = [];
    protected array $validateErros = [];
    protected Request $request;

    /**
     * Constructor Base para Controladores de API
     */
    public function __construct()
    {
        $this->request = request();
        $this->ip = $this->request->ip();
        $this->requestData = $this->request->all();
    }

    /**
     * Obtiene la ip del cliente que realiza la petición
     * @return string
     */
    protected function getIp(): string
    {
        return $this->ip;
    }

    /**
     * Obtiene los datos enviados en el request
     * @return array
     */
    protected function getRequestData(): array
    {
        return $this->requestData;
    }

    /**
     * Agregar Reglas de formulario.
     */
    protected function setRequestValidationRules(array $rules): static
    {
        $this->validationRules = $rules;
        return $this;
    }

    /**
     * Validar Reglas de formulario.
     */
    protected function validateRequestRules(array $requestData = []): bool
    {
        if (is_array($requestData) && !empty($requestData)) {
            $data = $requestData;
        } else {
            $data = $this->requestData;
        }

        $validator = Validator::make($data, $this->validationRules);

        if ($validator->fails()) {
            $tmpErrores = $validator->errors()->all();
            $this->validateErros = $tmpErrores;
            $this->agregarError($tmpErrores);
            return false;
        }
        return true;
    }

    /**
     * Retorno de la respuesta final de la petición
     */
    public function sendResponse(int $rcodigo = 200): JsonResponse
    {
        return response()->json($this->respuesta, $rcodigo);
    }

    /**
     * Obtiene un parametro enviado por body
     */
    public function getDataBody($campo): mixed
    {
        return (isset($this->requestData[$campo])) ? $this->requestData[$campo] : "";
    }

    /**
     * Seteo de la respuesta sin errores
     */
    public function respSinError(): void
    {
        $this->respuesta["error"] = 0;
    }

    /**
     * Registra un nuevo mensaje de error.
     */
    public function agregarError(mixed $mensaje): void
    {
        if (is_array($mensaje)) {
            foreach ($mensaje as $value) {
                $this->respuesta["mensaje"][] = $value;
            }
        } else {
            $this->respuesta["mensaje"][] = $mensaje;
        }
    }

    /**
     * Verifica si hay mensajes de error
     */
    public function existeErrores(): bool
    {
        return !empty($this->respuesta["mensaje"]);
    }

    /**
     * Guardado de respuestas en el cuerpo de la petición en el campo "data"
     */
    public function setDataResponse(mixed $valor, string $nombreVar = ""): void
    {
        if (is_array($valor) && $nombreVar == "") {
            foreach ($valor as $key => $value) {
                $this->respuesta["data"][$key] = $value;
            }
        } else {
            if ($nombreVar == "") {
                $this->respuesta["data"] = $valor;
            } else {
                $this->respuesta["data"][$nombreVar] = $valor;
            }
        }
    }
}
