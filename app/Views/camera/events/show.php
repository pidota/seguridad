<?php
$record = $record ?? [];
$id = (int) ($record['id'] ?? 0);
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Central de Cámaras</p>
        <h2 class="page-card__title mb-1">Detalle de novedad</h2>
        <p class="mb-0">
            <span class="camera-badge camera-badge--<?= e((string) ($record['classification_tone'] ?? 'other')) ?>">
                <?= e((string) ($record['classification_label'] ?? '—')) ?>
            </span>
        </p>
    </div>
    <div class="page-toolbar__actions">
        <?php if (hasPermission('cctv.log.edit')): ?>
            <a class="btn btn-navy" href="<?= e(url('/cctv/log/' . $id . '/edit')) ?>">Editar</a>
        <?php endif; ?>
        <a class="btn btn-outline-navy" href="<?= e(url('/cctv/log')) ?>">Volver a la bitácora</a>
    </div>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<div class="page-card page-card--xl">
    <dl class="camera-detail-grid">
        <div>
            <dt>Fecha</dt>
            <dd><?= e(!empty($record['event_date']) ? date('d-m-Y', strtotime((string) $record['event_date'])) : '—') ?></dd>
        </div>
        <div>
            <dt>Hora</dt>
            <dd><?= e((string) (($record['event_time'] ?? '') !== '' ? $record['event_time'] : '—')) ?></dd>
        </div>
        <div>
            <dt>Turno</dt>
            <dd><?= e((string) ($record['shift_label'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Clasificación</dt>
            <dd><?= e((string) ($record['classification_label'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Ubicación / cámara</dt>
            <dd><?= e((string) ($record['location'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Operador</dt>
            <dd><?= e((string) ($record['created_by_name'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Última modificación</dt>
            <dd>
                <?php if (!empty($record['updated_at'])): ?>
                    <?= e(date('d-m-Y H:i', strtotime((string) $record['updated_at']))) ?>
                    <?php if (!empty($record['updated_by_name'])): ?>
                        · <?= e((string) $record['updated_by_name']) ?>
                    <?php endif; ?>
                <?php else: ?>
                    —
                <?php endif; ?>
            </dd>
        </div>
    </dl>

    <div class="camera-detail-block">
        <h3 class="camera-detail-block__title">Descripción</h3>
        <p class="camera-detail-block__text"><?= nl2br(e((string) ($record['description'] ?? '—'))) ?></p>
    </div>

    <div class="camera-detail-block">
        <h3 class="camera-detail-block__title">Acciones realizadas</h3>
        <p class="camera-detail-block__text"><?= nl2br(e((string) (($record['actions_taken'] ?? '') !== '' ? $record['actions_taken'] : 'Sin acciones registradas.'))) ?></p>
    </div>
</div>
