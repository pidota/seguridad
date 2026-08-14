<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PasswordService;
use App\Validators\ForgotPasswordValidator;
use App\Validators\PasswordChangeValidator;
use Core\Auth;
use Core\Controller;
use Core\Request;
use Core\Session;

final class PasswordController extends Controller
{
    public function __construct(private readonly PasswordService $passwords = new PasswordService())
    {
    }

    public function showChange(): void
    {
        $this->view('auth/change-password', [
            'title' => 'Cambiar contraseña',
            'user' => Auth::user(),
        ]);
    }

    public function update(Request $request): void
    {
        $payload = $request->only(['current_password', 'password', 'password_confirmation']);
        $errors = (new PasswordChangeValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashErrors($errors);
            Session::flashAlert('error', 'No se pudo actualizar', 'Revise los campos del formulario.');
            $this->redirect(url('/password/change'));
        }

        $userId = Auth::id();

        if ($userId === null) {
            $this->redirect(url('/login'));
        }

        $ok = $this->passwords->change(
            $userId,
            (string) $payload['current_password'],
            (string) $payload['password']
        );

        if (!$ok) {
            Session::flashErrors(['current_password' => 'La contraseña actual no es correcta.']);
            Session::flashAlert('error', 'Contraseña actual incorrecta', 'Verifique su contraseña vigente.');
            $this->redirect(url('/password/change'));
        }

        Session::flashAlert('success', 'Contraseña actualizada', 'Su nueva contraseña quedó registrada.');
        $this->redirect(url('/dashboard'));
    }

    public function showForgot(): void
    {
        $this->view('auth/forgot-password', [
            'title' => 'Recuperar contraseña',
        ], 'auth');
    }

    public function forgot(Request $request): void
    {
        $payload = $request->only(['email']);
        $errors = (new ForgotPasswordValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Correo inválido', 'Ingrese un correo electrónico válido.');
            $this->redirect(url('/password/forgot'));
        }

        $this->passwords->requestReset((string) $payload['email']);

        Session::flashAlert(
            'info',
            'Solicitud registrada',
            'Si el correo existe en el sistema, la recuperación quedará registrada. El envío de correo se habilitará en una etapa posterior.'
        );
        $this->redirect(url('/login'));
    }
}
