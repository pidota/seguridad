<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use Core\Logger;

final class PasswordService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function change(int $userId, string $currentPassword, string $newPassword): bool
    {
        $user = $this->users->findById($userId);

        if ($user === null || !password_verify($currentPassword, $user['password'])) {
            return false;
        }

        $this->users->updatePassword($userId, $newPassword);
        Logger::info('Contraseña actualizada para usuario ID ' . $userId);
        $this->audit->log('password_changed', 'auth', 'users', $userId);

        return true;
    }

    public function requestReset(string $email): void
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 3600);

        $this->users->createPasswordReset($email, $hash, $expires);

        Logger::info('Solicitud de recuperación de contraseña registrada para usuario ID ' . $user['id'] . '. Envío de correo pendiente de configuración.');
    }
}
