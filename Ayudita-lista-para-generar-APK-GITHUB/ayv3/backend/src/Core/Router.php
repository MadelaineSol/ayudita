<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;

/**
 * Router HTTP con soporte de parámetros {id} y middlewares por ruta.
 */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,handler:array,middlewares:array}> */
    private array $routes = [];

    /** @var string[] Middlewares aplicados al grupo actual. */
    private array $groupMiddlewares = [];

    private string $groupPrefix = '';

    public function group(string $prefix, array $middlewares, callable $definitions): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddlewares = $this->groupMiddlewares;
        $this->groupPrefix .= $prefix;
        $this->groupMiddlewares = array_merge($this->groupMiddlewares, $middlewares);
        $definitions($this);
        $this->groupPrefix = $previousPrefix;
        $this->groupMiddlewares = $previousMiddlewares;
    }

    public function get(string $path, array $handler, array $middlewares = []): void
    {
        $this->add('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, array $handler, array $middlewares = []): void
    {
        $this->add('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, array $handler, array $middlewares = []): void
    {
        $this->add('PUT', $path, $handler, $middlewares);
    }

    public function delete(string $path, array $handler, array $middlewares = []): void
    {
        $this->add('DELETE', $path, $handler, $middlewares);
    }

    private function add(string $method, string $path, array $handler, array $middlewares): void
    {
        $this->routes[] = [
            'method'      => $method,
            'pattern'     => $this->groupPrefix . $path,
            'handler'     => $handler,
            'middlewares' => array_merge($this->groupMiddlewares, $middlewares),
        ];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }
            $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $route['pattern']);
            if (!preg_match('#^' . $regex . '$#', $request->path, $matches)) {
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $request->setRouteParams($params);

            foreach ($route['middlewares'] as $middleware) {
                $this->resolveMiddleware($middleware)->handle($request);
            }

            [$class, $action] = $route['handler'];
            $controller = new $class();
            $controller->$action($request);
            return;
        }

        throw new HttpException('Recurso no encontrado', 404);
    }

    private function resolveMiddleware(string $definition): object
    {
        // Formato: "Nombre" o "Nombre:arg1,arg2" (ej: "role:admin")
        [$name, $args] = array_pad(explode(':', $definition, 2), 2, null);
        $class = 'App\\Middlewares\\' . ucfirst($name) . 'Middleware';
        $arguments = $args !== null ? explode(',', $args) : [];
        return new $class(...$arguments);
    }
}
