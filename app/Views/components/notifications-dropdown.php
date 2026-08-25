<?php

$user = $user ?? user();
$notificationItems = notifications_recent(8);
$notificationUnread = notifications_unread_count();
?>
<div class="navbar-notifications dropdown">
    <button
        type="button"
        class="navbar-notifications__toggle dropdown-toggle"
        data-bs-toggle="dropdown"
        aria-expanded="false"
        title="Notificaciones"
    >
        <i class="bi bi-bell"></i>
        <?php if ($notificationUnread > 0): ?>
            <span class="navbar-notifications__badge"><?= (int) $notificationUnread ?></span>
        <?php endif; ?>
    </button>
    <div class="dropdown-menu dropdown-menu-end navbar-notifications__menu">
        <div class="navbar-notifications__header">
            <strong>Notificaciones</strong>
            <?php if ($notificationUnread > 0): ?>
                <span class="navbar-notifications__count"><?= (int) $notificationUnread ?> sin leer</span>
            <?php endif; ?>
        </div>

        <?php if ($notificationItems === []): ?>
            <p class="navbar-notifications__empty">No tiene notificaciones recientes.</p>
        <?php else: ?>
            <ul class="navbar-notifications__list">
                <?php foreach ($notificationItems as $item): ?>
                    <li class="navbar-notifications__item <?= !empty($item['is_unread']) ? 'is-unread' : '' ?>">
                        <?php if (!empty($item['url'])): ?>
                            <a class="navbar-notifications__link" href="<?= e((string) $item['url']) ?>">
                                <span class="navbar-notifications__icon navbar-notifications__icon--<?= e((string) ($item['tone'] ?? 'other')) ?>">
                                    <i class="bi <?= e((string) ($item['icon'] ?? 'bi-bell')) ?>"></i>
                                </span>
                                <span class="navbar-notifications__body">
                                    <span class="navbar-notifications__title"><?= e((string) ($item['title'] ?? '')) ?></span>
                                    <span class="navbar-notifications__message"><?= e((string) ($item['excerpt'] ?? '')) ?></span>
                                    <span class="navbar-notifications__meta">
                                        <?= e((string) ($item['module_label'] ?? '')) ?> · <?= e((string) ($item['time_ago'] ?? '')) ?>
                                    </span>
                                </span>
                            </a>
                        <?php else: ?>
                            <div class="navbar-notifications__link">
                                <span class="navbar-notifications__icon navbar-notifications__icon--<?= e((string) ($item['tone'] ?? 'other')) ?>">
                                    <i class="bi <?= e((string) ($item['icon'] ?? 'bi-bell')) ?>"></i>
                                </span>
                                <span class="navbar-notifications__body">
                                    <span class="navbar-notifications__title"><?= e((string) ($item['title'] ?? '')) ?></span>
                                    <span class="navbar-notifications__message"><?= e((string) ($item['excerpt'] ?? '')) ?></span>
                                    <span class="navbar-notifications__meta">
                                        <?= e((string) ($item['module_label'] ?? '')) ?> · <?= e((string) ($item['time_ago'] ?? '')) ?>
                                    </span>
                                </span>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="navbar-notifications__footer">
            <a href="<?= e(url('/notifications')) ?>">Ver todas</a>
            <?php if ($notificationUnread > 0): ?>
                <form method="post" action="<?= e(url('/notifications/read-all')) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-link btn-sm p-0">Marcar leídas</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
