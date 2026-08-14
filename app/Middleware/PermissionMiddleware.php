<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\MiddlewareInterface;
use Core\Permission;
use Core\Request;

final class PermissionMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, ?string $argument = null): void
    {
        if ($argument === null || $argument === '') {
            throw new \InvalidArgumentException('El middleware can requiere un permiso.');
        }

        $permissions = array_filter(array_map('trim', explode(',', $argument)));

        foreach ($permissions as $permission) {
            if (Permission::has($permission)) {
                return;
            }
        }

        Permission::require($permissions[0] ?? $argument);
    }
}
