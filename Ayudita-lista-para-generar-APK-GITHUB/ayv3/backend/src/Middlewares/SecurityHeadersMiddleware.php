<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Request;

/**
 * Headers de seguridad aplicados a todas las respuestas.
 */
final class SecurityHeadersMiddleware
{
    public function handle(Request $request): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 0');
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header_remove('X-Powered-By');
    }
}
