<?php
$isEdit = !empty($record['id']);
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Central de Cámaras</p>
        <h2 class="page-card__title mb-0"><?= $isEdit ? 'Editar cámara' : 'Registrar cámara' ?></h2>
    </div>
    <a class="btn btn-outline-navy" href="<?= e(url('/cctv/cameras')) ?>" data-cctv-leave-without-save>Volver al inventario</a>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<div class="page-card page-card--lg">
    <form
        method="post"
        action="<?= e($isEdit ? url('/cctv/cameras/' . $record['id']) : url('/cctv/cameras')) ?>"
        novalidate
        data-cctv-camera-form
    >
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <?= method_field('PUT') ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label" for="code">Código</label>
                <input class="form-control <?= has_error('code') ? 'is-invalid' : '' ?>" id="code" name="code" value="<?= e((string) old('code', (string) ($record['code'] ?? ''))) ?>" maxlength="40" required>
                <?php if (has_error('code')): ?><div class="invalid-feedback"><?= e((string) error('code')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-8 mb-3">
                <label class="form-label" for="name">Nombre</label>
                <input class="form-control <?= has_error('name') ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e((string) old('name', (string) ($record['name'] ?? ''))) ?>" maxlength="180" required>
                <?php if (has_error('name')): ?><div class="invalid-feedback"><?= e((string) error('name')) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="sector_id">Sector</label>
                <select class="form-select <?= has_error('sector_id') ? 'is-invalid' : '' ?>" id="sector_id" name="sector_id">
                    <option value="">Sin sector</option>
                    <?php foreach ($sectors ?? [] as $sector): ?>
                        <option value="<?= e((string) $sector['id']) ?>" <?= (string) old('sector_id', (string) ($record['sector_id'] ?? '')) === (string) $sector['id'] ? 'selected' : '' ?>>
                            <?= e((string) $sector['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('sector_id')): ?><div class="invalid-feedback"><?= e((string) error('sector_id')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="location">Ubicación (descripción)</label>
                <input class="form-control <?= has_error('location') ? 'is-invalid' : '' ?>" id="location" name="location" value="<?= e((string) old('location', (string) ($record['location'] ?? ''))) ?>" maxlength="255" placeholder="Ej.: Plaza central, esquina con calle X">
                <?php if (has_error('location')): ?><div class="invalid-feedback"><?= e((string) error('location')) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label mb-0">Ubicación en mapa</label>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-camera-map-clear>Limpiar punto</button>
            </div>
            <p class="text-secondary small mb-2">Haga clic en el mapa para marcar la posición de la cámara. También puede arrastrar el marcador.</p>
            <div
                id="camera-map-picker"
                class="cctv-map-picker"
                style="min-height: 320px; height: 320px;"
                data-camera-map-picker
                data-map-config="<?= e(json_encode($mapConfig ?? [], JSON_UNESCAPED_UNICODE)) ?>"
                data-initial-lat="<?= e((string) old('latitude', (string) ($record['latitude'] ?? ''))) ?>"
                data-initial-lng="<?= e((string) old('longitude', (string) ($record['longitude'] ?? ''))) ?>"
            ></div>
            <div class="row mt-2">
                <div class="col-md-6">
                    <label class="form-label" for="latitude">Latitud</label>
                    <input class="form-control bg-light" id="latitude" name="latitude" value="<?= e((string) old('latitude', (string) ($record['latitude'] ?? ''))) ?>" readonly data-camera-lat placeholder="Se completa al marcar el mapa">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="longitude">Longitud</label>
                    <input class="form-control bg-light" id="longitude" name="longitude" value="<?= e((string) old('longitude', (string) ($record['longitude'] ?? ''))) ?>" readonly data-camera-lng placeholder="Se completa al marcar el mapa">
                </div>
            </div>
            <p class="form-text mb-0">No es necesario escribir coordenadas manualmente. Haga clic en el mapa y los campos se completarán solos.</p>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label" for="camera_type">Tipo de cámara</label>
                <select class="form-select <?= has_error('camera_type') ? 'is-invalid' : '' ?>" id="camera_type" name="camera_type" required>
                    <?php foreach ($cameraTypes ?? [] as $option): ?>
                        <option value="<?= e($option['value']) ?>" <?= (string) old('camera_type', (string) ($record['camera_type'] ?? '')) === $option['value'] ? 'selected' : '' ?>>
                            <?= e($option['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('camera_type')): ?><div class="invalid-feedback"><?= e((string) error('camera_type')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="status">Estado operativo</label>
                <select class="form-select <?= has_error('status') ? 'is-invalid' : '' ?>" id="status" name="status" required>
                    <?php foreach ($statuses ?? [] as $option): ?>
                        <option value="<?= e($option['value']) ?>" <?= (string) old('status', (string) ($record['status'] ?? '')) === $option['value'] ? 'selected' : '' ?>>
                            <?= e($option['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('status')): ?><div class="invalid-feedback"><?= e((string) error('status')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="active">Activa en inventario</label>
                <select class="form-select <?= has_error('active') ? 'is-invalid' : '' ?>" id="active" name="active">
                    <option value="1" <?= (string) old('active', (string) ((int) ($record['active'] ?? 1))) === '1' ? 'selected' : '' ?>>Sí</option>
                    <option value="0" <?= (string) old('active', (string) ((int) ($record['active'] ?? 1))) === '0' ? 'selected' : '' ?>>No</option>
                </select>
                <?php if (has_error('active')): ?><div class="invalid-feedback"><?= e((string) error('active')) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-navy" type="submit"><?= $isEdit ? 'Guardar cambios' : 'Registrar cámara' ?></button>
            <a class="btn btn-outline-navy" href="<?= e(url('/cctv/cameras')) ?>">Cancelar</a>
        </div>
    </form>
</div>
