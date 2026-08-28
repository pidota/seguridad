<?php

declare(strict_types=1);

namespace Core;

final class Session
{
    private const FLASH_KEY = '_flash';
    private const OLD_KEY = '_old_input';
    private const ERRORS_KEY = '_errors';
    private const LAST_ACTIVITY_KEY = '_last_activity';
    private const AUTH_SESSION_KEY = 'auth_user_id';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $lifetime = max(60, (int) env('SESSION_LIFETIME', 60) * 60);
        $secure = Request::isHttps();
        $name = (string) env('SESSION_NAME', 'sigsm_session');

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', (string) $lifetime);

        session_name($name);

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        self::ageFlash();
        self::enforceIdleTimeout($lifetime);
    }

    private static function enforceIdleTimeout(int $lifetimeSeconds): void
    {
        $now = time();
        $lastActivity = (int) ($_SESSION[self::LAST_ACTIVITY_KEY] ?? 0);
        $isAuthenticated = isset($_SESSION[self::AUTH_SESSION_KEY]);

        if ($isAuthenticated && $lastActivity > 0 && ($now - $lastActivity) > $lifetimeSeconds) {
            Auth::logout();
            $idleMinutes = max(1, (int) env('SESSION_LIFETIME', 60));
            $idleLabel = $idleMinutes === 60
                ? '1 hora'
                : ($idleMinutes % 60 === 0
                    ? ((int) ($idleMinutes / 60)) . ' horas'
                    : $idleMinutes . ' minutos');
            self::flashAlert(
                'warning',
                'Sesión expirada',
                'Su sesión se cerró tras ' . $idleLabel . ' de inactividad.'
            );
            $_SESSION[self::LAST_ACTIVITY_KEY] = $now;

            return;
        }

        if ($isAuthenticated) {
            $_SESSION[self::LAST_ACTIVITY_KEY] = $now;

            $params = session_get_cookie_params();
            setcookie(session_name(), session_id(), [
                'expires' => $now + $lifetimeSeconds,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION[self::FLASH_KEY]['new'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION[self::FLASH_KEY]['old'][$key] ?? $default;
    }

    public static function flashAlert(string $type, string $title, string $message): void
    {
        self::flash('alert', [
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);
    }

    public static function flashInput(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['current_password'], $input['_token'], $input['_method']);
        self::flash(self::OLD_KEY, $input);
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        $old = self::getFlash(self::OLD_KEY, []);

        return $old[$key] ?? $default;
    }

    public static function flashErrors(array $errors): void
    {
        self::flash(self::ERRORS_KEY, $errors);
    }

    public static function errors(): array
    {
        return self::getFlash(self::ERRORS_KEY, []);
    }

    public static function error(string $field): ?string
    {
        $errors = self::errors();

        if (!isset($errors[$field])) {
            return null;
        }

        return is_array($errors[$field]) ? ($errors[$field][0] ?? null) : (string) $errors[$field];
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    private static function ageFlash(): void
    {
        $_SESSION[self::FLASH_KEY]['old'] = $_SESSION[self::FLASH_KEY]['new'] ?? [];
        $_SESSION[self::FLASH_KEY]['new'] = [];
    }
}
