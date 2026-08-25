<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Centro de avisos</p>
        <h2 class="page-card__title mb-1">Notificaciones</h2>
        <p class="text-secondary mb-0">
            <?= (int) ($total ?? 0) ?> aviso(s)
            <?php if (($unreadCount ?? 0) > 0): ?>
                · <?= (int) $unreadCount ?> sin leer
            <?php endif; ?>
        </p>
    </div>
    <?php if (($unreadCount ?? 0) > 0): ?>
        <form method="post" action="<?= e(url('/notifications/read-all')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-navy">Marcar todas como leídas</button>
        </form>
    <?php endif; ?>
</section>

<div class="page-card">
    <?php if (($notifications ?? []) === []): ?>
        <p class="text-secondary mb-0">No hay notificaciones registradas.</p>
    <?php else: ?>
        <ul class="notifications-list">
            <?php foreach ($notifications as $item): ?>
                <li class="notifications-list__item <?= !empty($item['is_unread']) ? 'is-unread' : '' ?>">
                    <div class="notifications-list__icon notifications-list__icon--<?= e((string) ($item['tone'] ?? 'other')) ?>">
                        <i class="bi <?= e((string) ($item['icon'] ?? 'bi-bell')) ?>"></i>
                    </div>
                    <div class="notifications-list__body">
                        <div class="notifications-list__head">
                            <strong><?= e((string) ($item['title'] ?? '')) ?></strong>
                            <time><?= e((string) ($item['created_at_formatted'] ?? '')) ?></time>
                        </div>
                        <p class="notifications-list__message mb-1"><?= e((string) ($item['message'] ?? '')) ?></p>
                        <p class="notifications-list__meta mb-0"><?= e((string) ($item['module_label'] ?? '')) ?></p>
                    </div>
                    <div class="notifications-list__actions">
                        <?php if (!empty($item['url'])): ?>
                            <a href="<?= e((string) $item['url']) ?>" class="btn btn-sm btn-outline-navy">Abrir</a>
                        <?php endif; ?>
                        <?php if (!empty($item['is_unread'])): ?>
                            <form method="post" action="<?= e(url('/notifications/' . (int) ($item['id'] ?? 0) . '/read')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-link">Marcar leída</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?= component('pagination', [
            'page' => (int) ($page ?? 1),
            'pages' => (int) ($pages ?? 1),
            'base' => url('/notifications'),
            'query' => [],
        ]) ?>
    <?php endif; ?>
</div>
