<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Validators\LoginValidator;
use Core\Auth;
use Core\Controller;
use Core\Request;
use Core\Session;

final class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService = new AuthService())
    {
    }

    public function showLogin(): void
    {
        $this->view('auth/login', [
            'title' => 'Iniciar sesión',
            'locked' => $this->authService->isLocked(),
            'lockSeconds' => $this->authService->remainingLockSeconds(),
        ], 'auth');
    }

    public function login(Request $request): void
    {
        $payload = $request->only(['email', 'password']);
        $errors = (new LoginValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Datos incompletos', 'Revise los campos e intente nuevamente.');
            $this->redirect(url('/login'));
        }

        if ($this->authService->isLocked()) {
            $seconds = $this->authService->remainingLockSeconds();
            Session::flashInput(['email' => $payload['email']]);
            Session::flashAlert(
                'warning',
                'Acceso temporalmente bloqueado',
                'Demasiados intentos fallidos. Espere ' . $seconds . ' segundos.'
            );
            $this->redirect(url('/login'));
        }

        $ok = $this->authService->attempt((string) $payload['email'], (string) $payload['password']);

        if (!$ok) {
            Session::flashInput(['email' => $payload['email']]);
            Session::flashAlert('error', 'Credenciales inválidas', 'El correo o la contraseña no coinciden.');
            $this->redirect(url('/login'));
        }

        $user = Auth::user();

        if ($user !== null && !empty($user['must_change_password'])) {
            Session::flashAlert('info', 'Actualice su contraseña', 'Por seguridad debe establecer una nueva contraseña.');
            $this->redirect(url('/password/change'));
        }

        Session::flashAlert('success', 'Bienvenido', 'Sesión iniciada correctamente.');
        $this->redirect(url('/dashboard'));
    }

    public function logout(): void
    {
        $this->authService->logout();
        Session::flashAlert('info', 'Sesión cerrada', 'Ha salido del sistema de forma segura.');
        $this->redirect(url('/login'));
    }
}
