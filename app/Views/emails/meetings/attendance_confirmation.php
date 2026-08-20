<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirmación de asistencia — <?= e((string) ($meeting['meeting_number'] ?? 'Reunión')) ?></title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.5; color: #1c2430; margin: 0; padding: 0; background: #f4efe6; }
        .wrap { max-width: 640px; margin: 0 auto; padding: 24px 16px; }
        .card { background: #fff; border: 1px solid #d9d1c3; border-radius: 12px; padding: 24px; }
        h1 { font-size: 1.25rem; margin: 0 0 12px; color: #0b1f33; }
        p { margin: 0 0 12px; }
        .meta { background: #f8f6f1; border-radius: 8px; padding: 12px 16px; margin: 16px 0; }
        .btn { display: inline-block; padding: 12px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; }
        .btn-primary { background: #0b1f33; color: #fff !important; }
        .footer { font-size: 0.875rem; color: #5c6774; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Confirmación de asistencia</h1>
            <p>Estimado/a <strong><?= e($participantName) ?></strong>,</p>
            <p>Se registró su participación en la reunión <strong><?= e($meetingNumber) ?></strong> del sistema <?= e($appName) ?>.</p>
            <div class="meta">
                <p><strong>Fecha:</strong> <?= e($meetingDate) ?></p>
                <p><strong>Hora:</strong> <?= e($meetingTime) ?></p>
                <p><strong>Lugar:</strong> <?= e($meetingPlace) ?></p>
            </div>
            <p>Por favor confirme o decline su asistencia usando el siguiente enlace:</p>
            <p><a class="btn btn-primary" href="<?= e($confirmUrl) ?>">Responder invitación</a></p>
            <p class="footer">Si no esperaba este correo, puede ignorarlo. Este mensaje fue enviado desde <?= e((string) config('mail.from.address')) ?>.</p>
        </div>
    </div>
</body>
</html>
