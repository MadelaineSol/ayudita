<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Hashing de contraseñas con Argon2id.
 */
final class Password
{
    public static function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 2,
        ]);
    }

    public static function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
