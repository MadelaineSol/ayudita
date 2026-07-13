<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use App\Middlewares\CorsMiddleware;
use App\Middlewares\SecurityHeadersMiddleware;
use Throwable;

/**
 * Núcleo de la aplicación: middlewares globales, ruteo y manejo de errores.
 */
final class App
{
    public function run(): void
    {
        $request = Request::capture();

        try {
            (new SecurityHeadersMiddleware())->handle($request);
            (new CorsMiddleware())->handle($request);

            $router = new Router();
            $registerRoutes = require BASE_PATH . '/src/Config/routes.php';
            $registerRoutes($router);
            $router->dispatch($request);
        } catch (HttpException $e) {
            Response::error($e->getMessage(), $e->status, $e->details);
        } catch (Throwable $e) {
            Logger::error($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $debug = Env::bool('APP_DEBUG');
            Response::error(
                $debug ? $e->getMessage() : 'Error interno del servidor',
                500,
                $debug ? ['trace' => explode("\n", $e->getTraceAsString())] : null
            );
        }
    }
}
