<?php

declare(strict_types=1);

namespace Core;

use App\Repositories\PermissionRepository;
use Core\Exceptions\HttpException;

final class Permission
{
    private static ?array $cache = null;
    private static ?int $cacheUserId = null;

    public static function has(string $permission): bool
    {
        if (Auth::guest()) {
            return false;
        }

        if (Auth::isSuperAdmin()) {
            return true;
        }

        return in_array($permission, self::all(), true);
    }

    public static function require(string $permission): void
    {
        if (!self::has($permission)) {
            throw new HttpException(403, 'No tiene el permiso requerido: ' . $permission);
        }
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $userId = Auth::id();

        if ($userId === null) {
            return [];
        }

        if (self::$cache !== null && self::$cacheUserId === $userId) {
            return self::$cache;
        }

        self::$cache = (new PermissionRepository())->slugsForUser($userId);
        self::$cacheUserId = $userId;

        return self::$cache;
    }

    public static function flush(): void
    {
        self::$cache = null;
        self::$cacheUserId = null;
    }
}
