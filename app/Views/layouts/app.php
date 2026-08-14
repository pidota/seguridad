<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'Panel') ?> — <?= e((string) config('app.name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="app-body">
    <div class="app-shell">
        <?= component('sidebar', ['user' => $user ?? user()]) ?>
        <div class="app-main">
            <?= component('navbar', ['title' => $title ?? '', 'user' => $user ?? user()]) ?>
            <main class="app-content">
                <?php if (!empty($showSendaEntryBanner) && !empty($sendaEntry)): ?>
                    <?= \Core\View::make('senda/components/entry-bar', ['sendaEntry' => $sendaEntry], null) ?>
                <?php endif; ?>
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>
    <?= component('flash') ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
    <?php foreach ($moduleScripts ?? [] as $script): ?>
        <script src="<?= e($script) ?>"></script>
    <?php endforeach; ?>
    <?php if (!empty($moduleScript)): ?>
        <script src="<?= e($moduleScript) ?>"></script>
    <?php endif; ?>
</body>
</html>
