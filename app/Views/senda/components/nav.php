<nav class="senda-nav" aria-label="Navegación SENDA">
    <?php foreach ($sendaNav ?? [] as $item): ?>
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
