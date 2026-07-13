<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Request;
use App\Exceptions\HttpException;
use App\Security\RateLimiter;

/**
 * Rate limiting por IP + ruta. Uso: 'throttle:10,60' => 10 peticiones / 60 s.
 */
final class ThrottleMiddleware
{
    public function __construct(
        private readonly string $maxHits = '60',
        private readonly string $windowSeconds = '60',
    ) {
    }

    public function handle(Request $request): void
    {
        $key = sha1($request->ip . '|' . $request->path);
        if (!RateLimiter::attempt($key, (int) $this->maxHits, (int) $this->windowSeconds)) {
            throw new HttpException('Demasiadas peticiones. Probá de nuevo en unos minutos.', 429);
        }
    }
}
