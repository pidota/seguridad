<?php

declare(strict_types=1);

namespace App\Services;

use Core\Logger;
use Core\Mail\SmtpMailer;

final class MailService
{
    public function isConfigured(): bool
    {
        $mailer = (string) config('mail.mailer', 'smtp');
        if ($mailer !== 'smtp') {
            return false;
        }

        $host = trim((string) config('mail.host', ''));
        $username = trim((string) config('mail.username', ''));
        $password = (string) config('mail.password', '');
        $from = trim((string) config('mail.from.address', ''));

        return $host !== '' && $from !== '' && $password !== '';
    }

    public function sendHtml(string $toEmail, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (!$this->isConfigured()) {
            Logger::warning('Correo no enviado: SMTP no configurado (revise MAIL_* en .env). Destino: ' . $toEmail);

            return false;
        }

        try {
            $mailer = new SmtpMailer(
                (string) config('mail.host'),
                (int) config('mail.port', 587),
                (string) config('mail.username', ''),
                (string) config('mail.password', ''),
                (string) config('mail.encryption', 'tls')
            );

            $mailer->send(
                (string) config('mail.from.address'),
                (string) config('mail.from.name'),
                $toEmail,
                $subject,
                $htmlBody,
                $textBody
            );

            return true;
        } catch (\Throwable $e) {
            Logger::error($e);
            Logger::warning('Fallo envío de correo a ' . $toEmail . ': ' . $e->getMessage());

            return false;
        }
    }
}
