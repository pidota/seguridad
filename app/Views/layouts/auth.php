<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'Acceso') ?> — <?= e((string) config('app.name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="auth-body">
    <div class="auth-shell">
        <aside class="auth-brand">
            <div class="auth-brand__inner">
                <img src="<?= e(asset('images/logo.svg')) ?>" alt="Escudo municipal" class="auth-logo">
                <p class="auth-kicker">Municipalidad</p>
                <h1 class="auth-title">Seguridad Comunal</h1>
                <p class="auth-lead">Plataforma integral para la Unidad de Seguridad, Central de Cámaras, SENDA, Oficina de la Mujer y Guardias Municipales.</p>
            </div>
        </aside>
        <section class="auth-panel">
            <?= $content ?? '' ?>
        </section>
    </div>
    <?= component('flash') ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
