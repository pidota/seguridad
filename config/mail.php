<?php

declare(strict_types=1);

$mailFromName = trim((string) env('MAIL_FROM_NAME', ''));
if ($mailFromName === '' || str_contains($mailFromName, '${')) {
    $mailFromName = 'SISTEMA INTEGRAL DE GESTIÓN DE SEGURIDAD MUNICIPAL';
}

return [
    'mailer' => env('MAIL_MAILER', 'smtp'),
    'host' => env('MAIL_HOST', 'mail.municipalidadchepica.cl'),
    'port' => (int) env('MAIL_PORT', 587),
    'username' => env('MAIL_USERNAME', 'reuniones_seguridad@municipalidadchepica.cl'),
    'password' => env('MAIL_PASSWORD', ''),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'reuniones_seguridad@municipalidadchepica.cl'),
        'name' => $mailFromName,
    ],
];
