<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Auth;
use Core\Exceptions\HttpException;
use Core\MiddlewareInterface;
use Core\Request;

final class RoleMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, ?string $argument = null): void
    {
        if ($argument === null || $argument === '') {
            throw new \InvalidArgumentException('El middleware role requiere uno o más slugs.');
        }

        $roles = array_map('trim', explode(',', $argument));

        if (Auth::isSuperAdmin()) {
            return;
        }

        if (!Auth::hasRole(...$roles)) {
            throw new HttpException(403, 'No tiene permisos para acceder a este módulo.');
        }
    }
}
