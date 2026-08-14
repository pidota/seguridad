<?php
$isEdit = !empty($record['id']);
$classification = (string) old('classification', (string) ($record['classification'] ?? ''));
$showOther = $classification === 'otro';
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Central de Cámaras</p>
        <h2 class="page-card__title mb-0"><?= $isEdit ? 'Editar novedad' : 'Registrar novedad' ?></h2>
    </div>
    <a class="btn btn-outline-navy" href="<?= e(url($isEdit ? '/cctv/log/' . $record['id'] : '/cctv/log')) ?>" data-cctv-leave-without-save>
        <?= $isEdit ? 'Volver al detalle' : 'Volver a la bitácora' ?>
    </a>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<div class="page-card page-card--lg">
    <form
        method="post"
        action="<?= e($isEdit ? url('/cctv/log/' . $record['id']) : url('/cctv/log')) ?>"
        novalidate
        data-cctv-log-form
        data-camera-event-form
    >
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <?= method_field('PUT') ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label" for="event_date">Fecha del evento</label>
                <input type="date" class="form-control <?= has_error('event_date') ? 'is-invalid' : '' ?>" id="event_date" name="event_date" value="<?= e((string) old('event_date', (string) ($record['event_date'] ?? ''))) ?>" required>
                <?php if (has_error('event_date')): ?><div class="invalid-feedback"><?= e((string) error('event_date')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="event_time">Hora del evento</label>
                <input type="time" class="form-control <?= has_error('event_time') ? 'is-invalid' : '' ?>" id="event_time" name="event_time" value="<?= e((string) old('event_time', (string) ($record['event_time'] ?? ''))) ?>">
                <?php if (has_error('event_time')): ?><div class="invalid-feedback"><?= e((string) error('event_time')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="shift">Turno</label>
                <select class="form-select <?= has_error('shift') ? 'is-invalid' : '' ?>" id="shift" name="shift" required>
                    <option value="">Seleccione</option>
                    <?php foreach ($shifts ?? [] as $option): ?>
                        <option value="<?= e($option['value']) ?>" <?= (string) old('shift', (string) ($record['shift'] ?? '')) === $option['value'] ? 'selected' : '' ?>>
                            <?= e($option['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('shift')): ?><div class="invalid-feedback"><?= e((string) error('shift')) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="classification">Clasificación</label>
                <select class="form-select <?= has_error('classification') ? 'is-invalid' : '' ?>" id="classification" name="classification" data-camera-other-toggle required>
                    <option value="">Seleccione</option>
                    <?php foreach ($classifications ?? [] as $option): ?>
                        <option value="<?= e($option['value']) ?>" <?= $classification === $option['value'] ? 'selected' : '' ?>>
                            <?= e($option['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('classification')): ?><div class="invalid-feedback"><?= e((string) error('classification')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6 mb-3" data-camera-other-panel <?= $showOther ? '' : 'hidden' ?>>
                <label class="form-label" for="classification_other">Especifique la clasificación</label>
                <input class="form-control <?= has_error('classification_other') ? 'is-invalid' : '' ?>" id="classification_other" name="classification_other" value="<?= e((string) old('classification_other', (string) ($record['classification_other'] ?? ''))) ?>" maxlength="180">
                <?php if (has_error('classification_other')): ?><div class="invalid-feedback"><?= e((string) error('classification_other')) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="location">Ubicación / cámara</label>
            <input class="form-control <?= has_error('location') ? 'is-invalid' : '' ?>" id="location" name="location" value="<?= e((string) old('location', (string) ($record['location'] ?? ''))) ?>" maxlength="180" required>
            <?php if (has_error('location')): ?><div class="invalid-feedback"><?= e((string) error('location')) ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="description">Descripción de la novedad</label>
            <textarea class="form-control <?= has_error('description') ? 'is-invalid' : '' ?>" id="description" name="description" rows="5" required><?= e((string) old('description', (string) ($record['description'] ?? ''))) ?></textarea>
            <?php if (has_error('description')): ?><div class="invalid-feedback"><?= e((string) error('description')) ?></div><?php endif; ?>
        </div>

        <div class="mb-4">
            <label class="form-label" for="actions_taken">Acciones realizadas</label>
            <textarea class="form-control <?= has_error('actions_taken') ? 'is-invalid' : '' ?>" id="actions_taken" name="actions_taken" rows="4"><?= e((string) old('actions_taken', (string) ($record['actions_taken'] ?? ''))) ?></textarea>
            <?php if (has_error('actions_taken')): ?><div class="invalid-feedback"><?= e((string) error('actions_taken')) ?></div><?php endif; ?>
        </div>

        <div class="form-actions">
            <button class="btn btn-navy" type="submit"><?= $isEdit ? 'Guardar cambios' : 'Registrar novedad' ?></button>
            <a class="btn btn-outline-navy" href="<?= e(url($isEdit ? '/cctv/log/' . $record['id'] : '/cctv/log')) ?>">Cancelar</a>
        </div>
    </form>
</div>
