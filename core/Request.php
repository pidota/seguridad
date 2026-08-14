<?php

declare(strict_types=1);

namespace Core;

final class Request
{
    private array $query;
    private array $request;
    private array $server;
    private array $params = [];
    private string $method;
    private string $path;

    private static ?self $instance = null;

    private function __construct(array $query, array $request, array $server)
    {
        $this->query = $query;
        $this->request = $request;
        $this->server = $server;
        $this->method = $this->resolveMethod();
        $this->path = $this->resolvePath();
    }

    public static function capture(): self
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        self::$instance = new self($_GET, $_POST, $_SERVER);

        return self::$instance;
    }

    public static function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';

        return (!empty($https) && $https !== 'off') || $forwarded === 'https';
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function url(): string
    {
        $scheme = self::isHttps() ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host . ($this->server['REQUEST_URI'] ?? '/');
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $this->query[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        $data = [];

        foreach ($keys as $key) {
            $data[$key] = $this->input($key);
        }

        return $data;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->request);
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function header(string $name, mixed $default = null): mixed
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return $this->server[$key] ?? $this->server[$name] ?? $default;
    }

    public function wantsJson(): bool
    {
        $accept = (string) $this->header('Accept', '');

        return str_contains($accept, 'application/json');
    }

    public static function basePath(): string
    {
        if (PHP_SAPI === 'cli') {
            $configured = (string) env('APP_URL', '');
            $path = parse_url($configured, PHP_URL_PATH) ?: '';

            return rtrim($path, '/');
        }

        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $script = rtrim($script, '/');

        if ($script === '' || $script === '/' || $script === '.') {
            $script = '';
        }

        $uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

        if ($script !== '' && str_ends_with($script, '/public')) {
            $parent = rtrim(str_replace('\\', '/', dirname($script)), '/');

            if ($parent !== '' && $parent !== '/' && str_starts_with($uri, $parent) && !str_starts_with($uri, $script)) {
                return $parent;
            }
        }

        return $script;
    }

    public static function baseUrl(): string
    {
        $scheme = self::isHttps() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host . self::basePath();
    }

    private function resolveMethod(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            $spoofed = strtoupper((string) ($this->request['_method'] ?? ''));
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoofed;
            }
        }

        if ($method === 'HEAD') {
            return 'GET';
        }

        return $method;
    }

    private function resolvePath(): string
    {
        $uri = parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = rawurldecode($uri);
        $base = self::basePath();

        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base)) ?: '/';
        }

        $uri = '/' . ltrim($uri, '/');

        if ($uri !== '/') {
            $uri = rtrim($uri, '/') ?: '/';
        }

        return $uri;
    }
}
