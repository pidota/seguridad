<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use Core\Auth;
use Core\Logger;
use Core\Session;

final class AuthService
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 300;
    private const ATTEMPTS_KEY = 'login_attempts';
    private const LOCK_KEY = 'login_locked_until';

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function attempt(string $email, string $password): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        $ok = Auth::attempt($email, $password);

        if (!$ok) {
            $this->hit();
            $this->audit->log('login_failed', 'auth', 'users', null, null, ['email' => mb_strtolower($email)]);
            return false;
        }

        $this->clearAttempts();

        $user = Auth::user();
        if ($user !== null) {
            $this->users->markLastLogin((int) $user['id']);
            Logger::info('Inicio de sesión correcto para usuario ID ' . $user['id']);
            $this->audit->log('login', 'auth', 'users', (int) $user['id']);
        }

        return true;
    }

    public function logout(): void
    {
        $user = Auth::user();
        if ($user !== null) {
            $this->audit->log('logout', 'auth', 'users', (int) $user['id']);
        }

        Auth::logout();
        Session::destroy();
        Session::start();
    }

    public function isLocked(): bool
    {
        $until = (int) Session::get(self::LOCK_KEY, 0);

        return $until > time();
    }

    public function remainingLockSeconds(): int
    {
        $until = (int) Session::get(self::LOCK_KEY, 0);

        return max(0, $until - time());
    }

    private function hit(): void
    {
        $attempts = (int) Session::get(self::ATTEMPTS_KEY, 0) + 1;
        Session::put(self::ATTEMPTS_KEY, $attempts);

        if ($attempts >= self::MAX_ATTEMPTS) {
            Session::put(self::LOCK_KEY, time() + self::LOCKOUT_SECONDS);
        }
    }

    private function clearAttempts(): void
    {
        Session::forget(self::ATTEMPTS_KEY);
        Session::forget(self::LOCK_KEY);
    }
}
