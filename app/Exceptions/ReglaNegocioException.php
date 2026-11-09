<?php

namespace App\Exceptions;

use RuntimeException;

/** Error de regla de negocio con código HTTP y mensaje seguro para el cliente. */
class ReglaNegocioException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
