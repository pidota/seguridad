<section class="senda-hero">
    <div>
        <p class="welcome-kicker">Módulo operativo</p>
        <h2>Dashboard SENDA</h2>
        <p class="mb-0">Indicadores calculados desde los registros persistidos. Continúe con la atención, la ficha o el seguimiento.</p>
    </div>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<?php require __DIR__ . '/followup-alerts.php'; ?>

<?php if (($metrics ?? []) !== []): ?>
    <section class="senda-metric-grid" aria-label="Indicadores SENDA">
        <?php foreach ($metrics as $metric): ?>
            <a class="senda-metric senda-metric--<?= e((string) $metric['tone']) ?>" href="<?= e(url((string) $metric['path'])) ?>">
                <p class="senda-metric__label"><?= e((string) $metric['label']) ?></p>
                <p class="senda-metric__value"><?= (int) $metric['count'] ?></p>
                <p class="senda-metric__hint">Ver listado</p>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if ($cards === []): ?>
    <div class="page-card">
        <p class="mb-0 text-secondary">No tiene permisos de consulta sobre las secciones de SENDA.</p>
    </div>
<?php else: ?>
    <section class="senda-card-grid senda-card-grid--three">
        <?php foreach ($cards as $card): ?>
            <a class="senda-mini-card" href="<?= e(url($card['path'])) ?>">
                <i class="bi <?= e($card['icon']) ?>"></i>
                <h3><?= e($card['label']) ?></h3>
                <p><?= e($card['description']) ?></p>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
