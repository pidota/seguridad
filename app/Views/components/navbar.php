<?php $user = $user ?? user(); ?>
<header class="app-navbar">
    <div class="navbar-left">
        <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-label="Abrir menú">
            <i class="bi bi-list"></i>
        </button>
        <div>
            <p class="navbar-kicker"><?= e((string) config('app.name')) ?></p>
            <h1><?= e($title ?? 'Dashboard') ?></h1>
        </div>
    </div>

    <div class="navbar-user dropdown">
        <?php $pendingSignatures = meetings_pending_signature_count(); ?>
        <?php if ($pendingSignatures > 0 && hasPermission('meetings.view_pending_signatures')): ?>
            <a class="btn btn-outline-navy btn-sm me-2 position-relative" href="<?= e(url('/meetings/pending-signatures')) ?>" title="Firmas pendientes">
                <i class="bi bi-pen"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">
                    <?= (int) $pendingSignatures ?>
                </span>
            </a>
        <?php endif; ?>
        <button class="navbar-user__button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="navbar-user__avatar"><?= e(mb_strtoupper(mb_substr((string) ($user['name'] ?? 'U'), 0, 1))) ?></span>
            <span class="navbar-user__meta">
                <strong><?= e((string) ($user['name'] ?? '')) ?></strong>
                <small><?= e((string) ($user['role_names'] ?? '')) ?></small>
            </span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end navbar-dropdown">
            <li>
                <a class="dropdown-item" href="<?= e(url('/profile')) ?>">
                    <i class="bi bi-person"></i> Perfil
                </a>
            </li>
            <?php if (hasPermission('meetings.signature.manage')): ?>
            <li>
                <a class="dropdown-item" href="<?= e(url('/meetings/profile/signature')) ?>">
                    <i class="bi bi-pen"></i> Mi firma simple
                </a>
            </li>
            <?php endif; ?>
            <li>
                <a class="dropdown-item" href="<?= e(url('/password/change')) ?>">
                    <i class="bi bi-key"></i> Cambiar contraseña
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="post" action="<?= e(url('/logout')) ?>" data-confirm="Se cerrará su sesión en el sistema." data-confirm-title="Cerrar sesión">
                    <?= csrf_field() ?>
                    <button type="submit" class="dropdown-item text-danger">
                        <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                    </button>
                </form>
            </li>
        </ul>
    </div>
</header>
