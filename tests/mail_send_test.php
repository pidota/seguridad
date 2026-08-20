<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Solo CLI.\n");
    exit(1);
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Services\MailService;

$to = $argv[1] ?? 'informatica@municipalidadchepica.cl';
$mail = new MailService();

echo 'SMTP configurado: ' . ($mail->isConfigured() ? 'si' : 'no') . PHP_EOL;
echo 'Host: ' . (string) config('mail.host') . PHP_EOL;
echo 'From: ' . (string) config('mail.from.address') . PHP_EOL;
echo 'Destino: ' . $to . PHP_EOL;

$html = '<p>Prueba de envío SMTP desde el módulo de reuniones.</p>'
    . '<p>Fecha: ' . date('Y-m-d H:i:s') . '</p>'
    . '<p>Si recibe este correo, la configuración funciona correctamente.</p>';

$ok = $mail->sendHtml(
    $to,
    'Prueba SMTP — Reuniones Seguridad Municipal',
    $html,
    'Prueba de envío SMTP desde el módulo de reuniones. Fecha: ' . date('Y-m-d H:i:s')
);

if ($ok) {
    echo "RESULTADO: OK — correo enviado.\n";
    exit(0);
}

echo "RESULTADO: FALLO — revise storage/logs/app-" . date('Y-m-d') . ".log\n";
exit(1);
