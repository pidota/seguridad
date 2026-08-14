<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Auth;
use Core\MiddlewareInterface;
use Core\Request;
use Core\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, ?string $argument = null): void
    {
        if (!Auth::check()) {
            \Core\Session::flashAlert('warning', 'Sesión requerida', 'Debe iniciar sesión para continuar.');
            Response::redirect(url('/login'));
        }
    }
}
