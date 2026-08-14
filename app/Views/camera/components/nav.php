<div class="cctv-module-nav">
    <nav class="senda-nav" aria-label="Navegación Central de Cámaras">
        <?php foreach ($camerasNav ?? [] as $item): ?>
            <?php
                $active = !empty($item['exact'])
                    ? is_current_path($item['path'])
                    : is_active_path($item['path']);
            ?>
            <a class="senda-nav__link <?= $active ? 'is-active' : '' ?>" href="<?= e(url($item['path'])) ?>">
                <i class="bi <?= e($item['icon']) ?>"></i>
                <span><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!empty($cctvQuickActions)): ?>
        <aside class="cctv-quick-actions" aria-label="Acciones rápidas">
            <span class="cctv-quick-actions__label">Acciones rápidas</span>
            <div class="cctv-quick-actions__buttons">
                <?php foreach ($cctvQuickActions as $action): ?>
                    <?php
                        $path = (string) ($action['path'] ?? '');
                        $isCurrent = $path !== '' && is_current_path($path);
                        $variant = (string) ($action['variant'] ?? 'outline');
                        $btnClass = $variant === 'primary' ? 'btn-navy' : 'btn-outline-navy';
                    ?>
                    <?php if ($isCurrent): ?>
                        <span class="btn <?= e($btnClass) ?> btn-sm is-current" aria-current="page">
                            <?= e((string) ($action['label'] ?? '')) ?>
                        </span>
                    <?php else: ?>
                        <a class="btn <?= e($btnClass) ?> btn-sm" href="<?= e(url($path)) ?>">
                            <?= e((string) ($action['label'] ?? '')) ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </aside>
    <?php endif; ?>
</div>
