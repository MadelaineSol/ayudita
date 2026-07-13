<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Error de validación de datos de entrada (HTTP 422).
 */
final class ValidationException extends HttpException
{
    public function __construct(array $errors)
    {
        parent::__construct('Los datos enviados no son válidos', 422, $errors);
    }
}
