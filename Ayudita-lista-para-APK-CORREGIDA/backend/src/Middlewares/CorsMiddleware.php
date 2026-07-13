<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Env;
use App\Core\Request;

/**
 * CORS restringido a los orígenes configurados en .env (CORS_ORIGINS).
 */
final class CorsMiddleware
{
    public function handle(Request $request): void
    {
        $allowed = array_map('trim', explode(',', Env::get('CORS_ORIGINS', '*') ?? '*'));
        $origin = $request->header('origin', '');

        if (in_array('*', $allowed, true)) {
            header('Access-Control-Allow-Origin: *');
        } elseif ($origin !== '' && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');

        if ($request->method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
