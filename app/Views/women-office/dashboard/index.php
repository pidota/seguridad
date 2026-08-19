<section class="women-hero">
    <div>
        <p class="welcome-kicker">Módulo operativo</p>
        <h2>Oficina de la Mujer</h2>
        <p class="mb-0">Registro confidencial de casos de violencia de género. Los indicadores se calculan desde la base de datos.</p>
    </div>
</section>

<?= women_nav($womenNav ?? []) ?>

<?php if (($alerts ?? []) !== []): ?>
    <section class="women-alert-grid" aria-label="Alertas operativas">
        <?php foreach ($alerts as $alert): ?>
            <a class="women-alert women-alert--<?= e((string) ($alert['tone'] ?? 'default')) ?>" href="<?= e(url((string) ($alert['path'] ?? '/women'))) ?>">
                <p class="women-alert__label"><?= e((string) ($alert['label'] ?? '')) ?></p>
                <p class="women-alert__value"><?= (int) ($alert['count'] ?? 0) ?></p>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if (($metrics ?? []) !== []): ?>
    <section class="women-metric-grid" aria-label="Indicadores del módulo">
        <?php foreach ($metrics as $metric): ?>
            <a class="women-metric women-metric--<?= e((string) $metric['tone']) ?>" href="<?= e(url((string) ($metric['path'] ?? '/women/cases'))) ?>">
                <p class="women-metric__label"><?= e((string) $metric['label']) ?></p>
                <p class="women-metric__value"><?= (int) $metric['count'] ?></p>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if (($cards ?? []) === []): ?>
    <div class="page-card">
        <p class="mb-0 text-secondary">No tiene permisos para operar secciones de la Oficina de la Mujer.</p>
    </div>
<?php else: ?>
    <section class="women-card-grid">
        <?php foreach ($cards as $card): ?>
            <a class="women-mini-card" href="<?= e(url($card['path'])) ?>">
                <i class="bi <?= e($card['icon']) ?>"></i>
                <h3><?= e($card['label']) ?></h3>
                <p><?= e($card['description']) ?></p>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
