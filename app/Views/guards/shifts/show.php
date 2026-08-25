<?php $shift = $shift ?? []; ?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Turno #<?= (int) ($shift['id'] ?? 0) ?></p>
        <h2 class="page-card__title mb-1">Detalle de turno</h2>
        <p class="text-secondary mb-0"><?= e((string) ($shift['guard_label'] ?? '—')) ?> · <?= e((string) ($shift['shift_date_formatted'] ?? '—')) ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if (!empty($canCreateLog)): ?>
            <a href="<?= e(url('/guards/log/create')) ?>" class="btn btn-navy">+ Novedad</a>
        <?php endif; ?>
        <a href="<?= e(url('/guards/shifts')) ?>" class="btn btn-outline-navy">Volver</a>
    </div>
</section>

<?= guards_nav($guardsNav ?? []) ?>

<div class="page-card mb-3">
    <dl class="row mb-0">
        <div class="col-md-3"><dt class="text-secondary">Estado</dt><dd><span class="camera-device-badge camera-device-badge--<?= e((string) ($shift['status_tone'] ?? 'other')) ?>"><?= e((string) ($shift['status_label'] ?? '—')) ?></span></dd></div>
        <div class="col-md-3"><dt class="text-secondary">Inicio</dt><dd><?= e((string) ($shift['started_at_formatted'] ?? '—')) ?></dd></div>
        <div class="col-md-3"><dt class="text-secondary">Fin</dt><dd><?= e((string) ($shift['ended_at_formatted'] ?? '—')) ?></dd></div>
        <div class="col-md-3"><dt class="text-secondary">Duración</dt><dd><?= e((string) ($shift['duration_label'] ?? '—')) ?></dd></div>
    </dl>
    <?php if (!empty($shift['opening_notes'])): ?>
        <p class="mt-3 mb-0"><strong>Notas de apertura:</strong> <?= e((string) $shift['opening_notes']) ?></p>
    <?php endif; ?>
    <?php if (!empty($shift['closing_notes'])): ?>
        <p class="mt-2 mb-0"><strong>Notas de cierre:</strong> <?= e((string) $shift['closing_notes']) ?></p>
    <?php endif; ?>
</div>

<div class="page-card mb-3">
    <h3 class="h6 mb-3">Resumen del turno</h3>
    <div class="guards-active-shift__stats">
        <div><dt>Novedades</dt><dd><?= (int) ($stats['total_entries'] ?? 0) ?></dd></div>
        <div><dt>Incidencias</dt><dd><?= (int) ($stats['incidents'] ?? 0) ?></dd></div>
        <div><dt>Rondas</dt><dd><?= (int) ($stats['rounds'] ?? 0) ?></dd></div>
        <div><dt>Notificadas a CCTV</dt><dd><?= (int) ($stats['cctv_linked'] ?? 0) ?></dd></div>
    </div>
</div>

<div class="page-card">
    <h3 class="h6 mb-3">Bitácora del turno</h3>
    <?php if (($logEntries ?? []) === []): ?>
        <p class="text-secondary mb-0">No hay novedades registradas en este turno.</p>
    <?php else: ?>
        <?php foreach ($logEntries as $entry): ?>
            <article class="guards-cctv-panel__item">
                <div class="d-flex justify-content-between gap-2 mb-1">
                    <span class="camera-device-badge camera-device-badge--<?= e((string) ($entry['type_tone'] ?? 'other')) ?>"><?= e((string) ($entry['type_label'] ?? '—')) ?></span>
                    <time><?= e((string) ($entry['occurred_at_formatted'] ?? '—')) ?></time>
                </div>
                <p class="mb-1"><?= e((string) ($entry['observations'] ?? '')) ?></p>
                <p class="small text-secondary mb-1">Sector: <?= e((string) ($entry['sector_label'] ?? '—')) ?></p>
                <div class="d-flex gap-3">
                    <a href="<?= e(url('/guards/log/' . (int) ($entry['id'] ?? 0))) ?>">Ver detalle</a>
                    <?php if (!empty($canViewCctv) && !empty($entry['cctv_log_url'])): ?>
                        <a href="<?= e((string) $entry['cctv_log_url']) ?>">Ver en CCTV</a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
