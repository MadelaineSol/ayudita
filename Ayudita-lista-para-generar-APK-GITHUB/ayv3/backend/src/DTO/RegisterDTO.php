<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Datos de registro de usuario ya validados.
 */
final class RegisterDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly string $role,
        public readonly ?string $phone,
        public readonly string $authProvider = 'email',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'],
            strtolower($data['email']),
            $data['password'],
            $data['role'],
            $data['phone'] ?? null,
            $data['auth_provider'] ?? 'email',
        );
    }
}
