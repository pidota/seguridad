<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Integración CCTV</p>
        <h2 class="page-card__title mb-1">Coordinaciones desde monitoreo</h2>
        <p class="text-secondary mb-0"><?= (int) ($total ?? 0) ?> incidentes con notificación a Guardias Municipales</p>
    </div>
    <a href="<?= e(url('/guards')) ?>" class="btn btn-outline-navy">Volver al inicio</a>
</section>

<?= guards_nav($guardsNav ?? []) ?>

<div class="page-card">
    <?php if (($coordinations ?? []) === []): ?>
        <p class="text-secondary mb-0">No hay coordinaciones registradas desde la bitácora de operadores CCTV.</p>
    <?php else: ?>
        <?php foreach ($coordinations as $item): ?>
            <article class="guards-cctv-panel__item">
                <div class="d-flex justify-content-between gap-2 mb-1">
                    <strong><?= e((string) ($item['log_type_name'] ?? 'Incidente')) ?></strong>
                    <time class="text-secondary"><?= e((string) ($item['occurred_at_formatted'] ?? '—')) ?></time>
                </div>
                <p class="mb-2"><?= nl2br(e((string) ($item['observations'] ?? ''))) ?></p>
                <p class="small text-secondary mb-2">
                    Operador CCTV: <?= e((string) ($item['cctv_operator_name'] ?? '—')) ?>
                    · Sector: <?= e((string) ($item['sector_label'] ?? '—')) ?>
                    <?php if (!empty($item['contact_name'])): ?>
                        · Contacto: <?= e((string) $item['contact_name']) ?>
                    <?php endif; ?>
                </p>
                <?php if (!empty($canViewCctv) && !empty($item['cctv_log_url'])): ?>
                    <a href="<?= e((string) $item['cctv_log_url']) ?>">Ver en bitácora CCTV</a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
