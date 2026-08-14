<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Csrf;
use Core\MiddlewareInterface;
use Core\Request;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, ?string $argument = null): void
    {
        $token = $request->input('_token') ?? $request->header('X-CSRF-TOKEN');
        Csrf::checkOrFail(is_string($token) ? $token : null);
    }
}
