<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Request;
use App\Exceptions\HttpException;
use App\Repositories\UserRepository;
use App\Security\Jwt;

/**
 * Autenticación por JWT Bearer. Inyecta el usuario en la request.
 */
final class AuthMiddleware
{
    public function handle(Request $request): void
    {
        $token = $request->bearerToken();
        if ($token === null) {
            throw new HttpException('No autenticado', 401);
        }

        $claims = Jwt::verify($token);
        if ($claims === null || ($claims['type'] ?? '') !== 'access') {
            throw new HttpException('Token inválido o expirado', 401);
        }

        $user = (new UserRepository())->findById((int) $claims['sub']);
        if ($user === null || $user['status'] === 'blocked') {
            throw new HttpException('Cuenta no disponible', 403);
        }

        unset($user['password_hash']);
        $request->setAttribute('user', $user);
    }
}
