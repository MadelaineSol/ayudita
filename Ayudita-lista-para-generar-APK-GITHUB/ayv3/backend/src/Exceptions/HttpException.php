<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Excepción HTTP con código de estado y detalles opcionales.
 */
class HttpException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $status = 400,
        public readonly ?array $details = null,
    ) {
        parent::__construct($message);
    }
}
