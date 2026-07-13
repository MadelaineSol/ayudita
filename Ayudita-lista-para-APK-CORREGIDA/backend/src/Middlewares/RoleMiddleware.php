<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Request;
use App\Exceptions\HttpException;

/**
 * Autorización por rol. Uso en rutas: 'role:admin' o 'role:provider,client'.
 */
final class RoleMiddleware
{
    /** @var string[] */
    private array $roles;

    public function __construct(string ...$roles)
    {
        $this->roles = $roles;
    }

    public function handle(Request $request): void
    {
        $user = $request->user();
        if ($user === null || !in_array($user['role'], $this->roles, true)) {
            throw new HttpException('No tenés permisos para esta acción', 403);
        }
    }
}
