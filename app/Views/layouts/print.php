<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Imprimir') ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <style>
        body { background: #fff; }
        .print-shell { max-width: 900px; margin: 0 auto; padding: 1.5rem; }
        @media print {
            .print-actions { display: none !important; }
            .print-shell { padding: 0; }
        }
    </style>
</head>
<body>
<div class="print-shell">
    <div class="print-actions mb-3 d-flex gap-2">
        <button type="button" class="btn btn-navy btn-sm" onclick="window.print()">Imprimir</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.close()">Cerrar</button>
    </div>
    <?= $content ?? '' ?>
</div>
</body>
</html>
