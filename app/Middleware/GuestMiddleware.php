<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Auth;
use Core\MiddlewareInterface;
use Core\Request;
use Core\Response;

final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, ?string $argument = null): void
    {
        if (Auth::check()) {
            Response::redirect(url('/dashboard'));
        }
    }
}
