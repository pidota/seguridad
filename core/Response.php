<?php

declare(strict_types=1);

namespace Core;

final class Response
{
    public static function redirect(string $to, int $status = 302): never
    {
        header('Location: ' . $to, true, $status);
        exit;
    }

    public static function back(int $status = 302): never
    {
        $fallback = url('/');
        $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));

        if ($referer === '') {
            self::redirect($fallback, $status);
        }

        $parts = parse_url($referer);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $current = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'))[0]);

        if ($host === '' || $host !== $current) {
            self::redirect($fallback, $status);
        }

        $path = (string) ($parts['path'] ?? '/');
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        self::redirect($path . $query, $status);
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function abort(int $status, string $message = ''): never
    {
        throw new Exceptions\HttpException($status, $message);
    }
}
