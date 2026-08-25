<?php $entry = $entry ?? []; ?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Novedad #<?= (int) ($entry['id'] ?? 0) ?></p>
        <h2 class="page-card__title mb-1">Detalle de novedad</h2>
        <p class="text-secondary mb-0"><?= e((string) ($entry['occurred_at_formatted'] ?? '—')) ?></p>
    </div>
    <a href="<?= e(url('/guards/log')) ?>" class="btn btn-outline-navy">Volver</a>
</section>

<?= guards_nav($guardsNav ?? []) ?>

<div class="page-card">
    <dl class="row mb-0">
        <div class="col-md-4"><dt class="text-secondary">Tipo</dt><dd><span class="camera-device-badge camera-device-badge--<?= e((string) ($entry['type_tone'] ?? 'other')) ?>"><?= e((string) ($entry['type_label'] ?? '—')) ?></span></dd></div>
        <div class="col-md-4"><dt class="text-secondary">Guardia</dt><dd><?= e((string) ($entry['guard_label'] ?? '—')) ?></dd></div>
        <div class="col-md-4"><dt class="text-secondary">Sector</dt><dd><?= e((string) ($entry['sector_label'] ?? '—')) ?></dd></div>
        <div class="col-md-4"><dt class="text-secondary">Estado</dt><dd><span class="camera-device-badge camera-device-badge--<?= e((string) ($entry['status_tone'] ?? 'other')) ?>"><?= e((string) ($entry['status_label'] ?? '—')) ?></span></dd></div>
        <div class="col-md-4"><dt class="text-secondary">Registrado por</dt><dd><?= e((string) ($entry['created_by_name'] ?? '—')) ?></dd></div>
        <div class="col-md-4"><dt class="text-secondary">Enlace CCTV</dt><dd><?= !empty($entry['has_cctv_link']) ? 'Sí' : 'No' ?></dd></div>
    </dl>
    <div class="mt-4">
        <h3 class="h6">Descripción</h3>
        <p class="mb-0"><?= nl2br(e((string) ($entry['observations'] ?? ''))) ?></p>
    </div>
    <?php if (!empty($canViewCctv) && !empty($entry['cctv_log_url'])): ?>
        <div class="mt-3">
            <a href="<?= e((string) $entry['cctv_log_url']) ?>" class="btn btn-outline-navy btn-sm">Ver en bitácora CCTV</a>
        </div>
    <?php endif; ?>
</div>
