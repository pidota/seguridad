<?php

declare(strict_types=1);

namespace Core;

use App\Repositories\UserRepository;

final class Auth
{
    private const SESSION_KEY = 'auth_user_id';

    private static ?array $cachedUser = null;
    private static ?int $cachedUserId = null;

    public static function attempt(string $email, string $password): bool
    {
        $user = (new UserRepository())->findByEmail($email);

        $verified = $user !== null && password_verify($password, $user['password']);

        if (!$verified || empty($user['is_active'])) {
            return false;
        }

        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            (new UserRepository())->updatePassword((int) $user['id'], $password);
        }

        self::login((int) $user['id']);

        return true;
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::put(self::SESSION_KEY, $userId);
        Csrf::rotate();
        self::forgetCache();
        Permission::flush();
    }

    public static function logout(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::regenerate();
        Csrf::rotate();
        self::forgetCache();
        Permission::flush();
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function id(): ?int
    {
        $id = Session::get(self::SESSION_KEY);

        return $id !== null ? (int) $id : null;
    }

    public static function user(): ?array
    {
        $id = self::id();

        if ($id === null) {
            return null;
        }

        if (self::$cachedUserId === $id && self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        $user = (new UserRepository())->findById($id);

        if ($user === null || empty($user['is_active'])) {
            self::logout();
            return null;
        }

        unset($user['password']);
        self::$cachedUser = $user;
        self::$cachedUserId = $id;

        return $user;
    }

    public static function hasRole(string ...$slugs): bool
    {
        $user = self::user();

        if ($user === null) {
            return false;
        }

        $owned = $user['role_slugs'] ?? [];

        foreach ($slugs as $slug) {
            if (in_array($slug, $owned, true)) {
                return true;
            }
        }

        return false;
    }

    public static function isSuperAdmin(): bool
    {
        return self::hasRole('superadministrador');
    }

    public static function forgetCache(): void
    {
        self::$cachedUser = null;
        self::$cachedUserId = null;
    }
}
