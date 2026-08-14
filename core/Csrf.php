<?php

declare(strict_types=1);

namespace Core;

use Core\Exceptions\HttpException;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            $token = self::rotate();
        }

        return $token;
    }

    public static function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::put(self::SESSION_KEY, $token);

        return $token;
    }

    public static function field(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="_token" value="' . $token . '">';
    }

    public static function validate(?string $token = null): bool
    {
        $sessionToken = Session::get(self::SESSION_KEY);
        $provided = $token ?? Request::capture()->input('_token');

        if (!is_string($sessionToken) || !is_string($provided) || $sessionToken === '' || $provided === '') {
            return false;
        }

        return hash_equals($sessionToken, $provided);
    }

    public static function checkOrFail(?string $token = null): void
    {
        if (!self::validate($token)) {
            throw new HttpException(419, 'La sesión ha expirado o el token de seguridad no es válido.');
        }
    }
}
