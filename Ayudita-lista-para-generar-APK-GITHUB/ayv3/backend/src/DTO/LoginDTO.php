<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Credenciales de inicio de sesión ya validadas.
 */
final class LoginDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(strtolower($data['email']), $data['password']);
    }
}
