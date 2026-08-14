<?php
$record = $record ?? [];
$openShift = $openShift ?? [];
$isEdit = !empty($isEdit);
$formAction = (string) ($formAction ?? url('/cctv/log'));
$cancelUrl = (string) ($cancelUrl ?? url('/cctv/log'));
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Central de Cámaras</p>
        <h2 class="page-card__title mb-0"><?= $isEdit ? 'Editar novedad' : 'Registrar novedad' ?></h2>
    </div>
    <a class="btn btn-outline-navy" href="<?= e($cancelUrl) ?>" data-cctv-leave-without-save>Volver<?= $isEdit ? ' al detalle' : ' al dashboard' ?></a>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<div class="page-card page-card--lg">
    <div class="cctv-log-create-meta mb-4">
        <div>
            <p class="welcome-kicker mb-1">Turno operativo</p>
            <p class="mb-0">
                Turno activo iniciado el
                <strong><?= e((string) ($openShift['started_at_formatted'] ?? '—')) ?></strong>
            </p>
        </div>
        <div>
            <p class="welcome-kicker mb-1">Operador</p>
            <p class="mb-0"><strong><?= e((string) ($operatorName ?? '—')) ?></strong></p>
        </div>
    </div>

    <form method="post" action="<?= e($formAction) ?>" novalidate data-cctv-log-form data-cctv-unsaved-guard>
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <?= method_field('PUT') ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label" for="event_date">Fecha</label>
                <input
                    type="date"
                    class="form-control <?= has_error('event_date') ? 'is-invalid' : '' ?>"
                    id="event_date"
                    name="event_date"
                    value="<?= e((string) old('event_date', (string) ($record['event_date'] ?? ''))) ?>"
                    required
                >
                <?php if (has_error('event_date')): ?>
                    <div class="invalid-feedback"><?= e((string) error('event_date')) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="event_time">Hora</label>
                <input
                    type="time"
                    class="form-control <?= has_error('event_time') ? 'is-invalid' : '' ?>"
                    id="event_time"
                    name="event_time"
                    data-cctv-event-time
                    value="<?= e((string) old('event_time', (string) ($record['event_time'] ?? ''))) ?>"
                    required
                >
                <?php if (has_error('event_time')): ?>
                    <div class="invalid-feedback"><?= e((string) error('event_time')) ?></div>
                <?php endif; ?>
                <div class="form-text">Puede ajustar la hora si el hecho ocurrió minutos antes.</div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="log_type_id">Tipo de registro</label>
                <select
                    class="form-select <?= has_error('log_type_id') ? 'is-invalid' : '' ?>"
                    id="log_type_id"
                    name="log_type_id"
                    required
                >
                    <option value="">Seleccione</option>
                    <?php foreach ($logTypes ?? [] as $option): ?>
                        <option
                            value="<?= (int) ($option['id'] ?? 0) ?>"
                            <?= (string) old('log_type_id', (string) ($record['log_type_id'] ?? '')) === (string) ($option['id'] ?? '') ? 'selected' : '' ?>
                        >
                            <?= e((string) ($option['label'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('log_type_id')): ?>
                    <div class="invalid-feedback"><?= e((string) error('log_type_id')) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="camera_id">Cámara</label>
                <select class="form-select <?= has_error('camera_id') ? 'is-invalid' : '' ?>" id="camera_id" name="camera_id">
                    <option value="">Sin cámara asociada</option>
                    <?php foreach ($cameras ?? [] as $option): ?>
                        <option
                            value="<?= (int) ($option['id'] ?? 0) ?>"
                            <?= (string) old('camera_id', (string) ($record['camera_id'] ?? '')) === (string) ($option['id'] ?? '') ? 'selected' : '' ?>
                        >
                            <?= e((string) ($option['label'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('camera_id')): ?>
                    <div class="invalid-feedback"><?= e((string) error('camera_id')) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="sector_id">Sector</label>
                <select class="form-select <?= has_error('sector_id') ? 'is-invalid' : '' ?>" id="sector_id" name="sector_id">
                    <option value="">Sin sector asociado</option>
                    <?php foreach ($sectors ?? [] as $option): ?>
                        <option
                            value="<?= (int) ($option['id'] ?? 0) ?>"
                            <?= (string) old('sector_id', (string) ($record['sector_id'] ?? '')) === (string) ($option['id'] ?? '') ? 'selected' : '' ?>
                        >
                            <?= e((string) ($option['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('sector_id')): ?>
                    <div class="invalid-feedback"><?= e((string) error('sector_id')) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label" for="observations">Observaciones</label>
            <textarea
                class="form-control <?= has_error('observations') ? 'is-invalid' : '' ?>"
                id="observations"
                name="observations"
                rows="5"
                maxlength="5000"
                required
            ><?= e((string) old('observations', (string) ($record['observations'] ?? ''))) ?></textarea>
            <?php if (has_error('observations')): ?>
                <div class="invalid-feedback"><?= e((string) error('observations')) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button class="btn btn-navy" type="submit"><?= $isEdit ? 'Guardar cambios' : 'Registrar novedad' ?></button>
            <a class="btn btn-outline-navy" href="<?= e($cancelUrl) ?>" data-cctv-leave-without-save>Cancelar</a>
        </div>
    </form>
</div>
