<?php

declare(strict_types=1);

namespace Core;

use Core\Exceptions\HttpException;

final class Router
{
    private array $routes = [];
    private array $named = [];
    private array $aliases = [];
    private string $groupPrefix = '';
    private array $groupMiddleware = [];

    public function alias(string $name, string $class): self
    {
        $this->aliases[$name] = $class;
        return $this;
    }

    public function get(string $path, array|callable $action, array|string $middleware = [], ?string $name = null): self
    {
        return $this->add('GET', $path, $action, $middleware, $name);
    }

    public function post(string $path, array|callable $action, array|string $middleware = [], ?string $name = null): self
    {
        return $this->add('POST', $path, $action, $middleware, $name);
    }

    public function put(string $path, array|callable $action, array|string $middleware = [], ?string $name = null): self
    {
        return $this->add('PUT', $path, $action, $middleware, $name);
    }

    public function patch(string $path, array|callable $action, array|string $middleware = [], ?string $name = null): self
    {
        return $this->add('PATCH', $path, $action, $middleware, $name);
    }

    public function delete(string $path, array|callable $action, array|string $middleware = [], ?string $name = null): self
    {
        return $this->add('DELETE', $path, $action, $middleware, $name);
    }

    public function group(string $prefix, array|string $middleware, callable $routes): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $prefix = trim($prefix, '/');
        $this->groupPrefix = $previousPrefix === ''
            ? ($prefix === '' ? '' : '/' . $prefix)
            : rtrim($previousPrefix, '/') . '/' . $prefix;

        $extra = is_string($middleware) ? [$middleware] : $middleware;
        $this->groupMiddleware = array_merge($previousMiddleware, $extra);

        $routes($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    public function dispatch(Request $request): mixed
    {
        $method = $request->method();
        $path = $request->path();
        $allowed = [];

        foreach ($this->routes as $route) {
            $params = $this->match($route['path'], $path);

            if ($params === false) {
                continue;
            }

            $allowed[] = $route['method'];

            if ($route['method'] !== $method) {
                continue;
            }

            $request->setParams($params);
            $this->runMiddleware($route['middleware'], $request);

            return $this->invoke($route['action'], $request, $params);
        }

        if ($allowed !== []) {
            throw new HttpException(405, 'Método no permitido.');
        }

        throw new HttpException(404, 'La página solicitada no existe.');
    }

    public function url(string $name, array $params = []): string
    {
        if (!isset($this->named[$name])) {
            throw new \InvalidArgumentException("La ruta nombrada [{$name}] no existe.");
        }

        $path = $this->named[$name];

        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', (string) $value, $path);
        }

        if (preg_match('/\{[^}]+\}/', $path)) {
            throw new \InvalidArgumentException("Faltan parámetros para la ruta [{$name}].");
        }

        return url($path);
    }

    public function has(string $name): bool
    {
        return isset($this->named[$name]);
    }

    private function add(string $method, string $path, array|callable $action, array|string $middleware, ?string $name): self
    {
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/') ?: '/';
        }

        $middleware = is_string($middleware) ? [$middleware] : $middleware;
        $middleware = array_merge($this->groupMiddleware, $middleware);

        if ($this->groupPrefix !== '') {
            $path = $path === '/'
                ? $this->groupPrefix
                : rtrim($this->groupPrefix, '/') . $path;
        }

        $route = [
            'method' => strtoupper($method),
            'path' => $path,
            'action' => $action,
            'middleware' => $middleware,
            'name' => $name,
        ];

        $this->routes[] = $route;

        if ($name !== null) {
            $this->named[$name] = $path;
        }

        return $this;
    }

    private function match(string $pattern, string $path): array|false
    {
        $pattern = rtrim($pattern, '/') ?: '/';
        $path = rtrim($path, '/') ?: '/';

        $names = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $m) use (&$names): string {
            $names[] = $m[1];
            return '([^/]+)';
        }, $pattern);

        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $path, $matches)) {
            return false;
        }

        array_shift($matches);

        return array_combine($names, $matches) ?: [];
    }

    private function runMiddleware(array $middleware, Request $request): void
    {
        foreach ($middleware as $definition) {
            [$alias, $argument] = array_pad(explode(':', (string) $definition, 2), 2, null);

            if (!isset($this->aliases[$alias])) {
                throw new \RuntimeException("El middleware [{$alias}] no está registrado.");
            }

            $instance = new $this->aliases[$alias]();
            $instance->handle($request, $argument);
        }
    }

    private function invoke(array|callable $action, Request $request, array $params): mixed
    {
        if (is_callable($action) && !is_array($action)) {
            return $action($request, ...array_values($params));
        }

        [$controller, $method] = $action;

        if (!class_exists($controller)) {
            throw new \RuntimeException("El controlador [{$controller}] no existe.");
        }

        $instance = new $controller();

        if (!method_exists($instance, $method)) {
            throw new \RuntimeException("El método [{$controller}::{$method}] no existe.");
        }

        return $instance->{$method}($request, ...array_values($params));
    }
}
