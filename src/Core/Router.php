<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function put(string $path, callable $handler): void
    {
        $this->routes['PUT'][$path] = $handler;
    }

    public function delete(string $path, callable $handler): void
    {
        $this->routes['DELETE'][$path] = $handler;
    }

    public function dispatch(Request $request): void
    {
        $handler = $this->routes[$request->method][$request->path] ?? null;

        if ($handler === null) {
            Response::json([
                'success' => false,
                'message' => 'Route not found',
            ], 404);
        }

        $result = $handler($request);
        if (is_array($result)) {
            Response::json($result);
        }

        if ($result !== null) {
            throw new RuntimeException('Route handler must return array or null.');
        }
    }
}
