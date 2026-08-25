<?php $defaults = $defaults ?? []; ?>
<section class="page-toolbar">    <div>
        <p class="welcome-kicker mb-1">Bitácora de terreno</p>
        <h2 class="page-card__title mb-1">Registrar novedad</h2>
        <p class="text-secondary mb-0">La novedad quedará asociada a su turno activo.</p>
    </div>
</section>

<?= guards_nav($guardsNav ?? []) ?>

<form method="post" action="<?= e(url('/guards/log')) ?>" class="page-card">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="log_type_id">Tipo</label>
            <select class="form-select <?= has_error('log_type_id') ? 'is-invalid' : '' ?>" id="log_type_id" name="log_type_id" required>
                <option value="">Seleccione</option>
                <?php foreach ($logTypes ?? [] as $type): ?>
                    <option value="<?= e((string) ($type['id'] ?? '')) ?>" <?= (string) old('log_type_id', (string) ($defaults['log_type_id'] ?? '')) === (string) ($type['id'] ?? '') ? 'selected' : '' ?>>
                        <?= e((string) ($type['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (has_error('log_type_id')): ?><div class="invalid-feedback"><?= e(error('log_type_id')) ?></div><?php endif; ?>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="event_date">Fecha</label>
            <input type="date" class="form-control <?= has_error('event_date') ? 'is-invalid' : '' ?>" id="event_date" name="event_date" value="<?= e((string) old('event_date', (string) ($defaults['event_date'] ?? ''))) ?>" required>
            <?php if (has_error('event_date')): ?><div class="invalid-feedback"><?= e(error('event_date')) ?></div><?php endif; ?>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="event_time">Hora</label>
            <input type="time" class="form-control <?= has_error('event_time') ? 'is-invalid' : '' ?>" id="event_time" name="event_time" value="<?= e((string) old('event_time', (string) ($defaults['event_time'] ?? ''))) ?>" required>
            <?php if (has_error('event_time')): ?><div class="invalid-feedback"><?= e(error('event_time')) ?></div><?php endif; ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="sector_id">Sector (opcional)</label>
            <select class="form-select" id="sector_id" name="sector_id">
                <option value="">Sin sector</option>
                <?php foreach ($sectors ?? [] as $sector): ?>
                    <option value="<?= e((string) ($sector['id'] ?? '')) ?>" <?= (string) old('sector_id', (string) ($defaults['sector_id'] ?? '')) === (string) ($sector['id'] ?? '') ? 'selected' : '' ?>>
                        <?= e((string) ($sector['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="status">Estado</label>
            <select class="form-select" id="status" name="status">
                <?php foreach ($statuses ?? [] as $status): ?>
                    <option value="<?= e((string) ($status['value'] ?? '')) ?>" <?= (string) old('status', (string) ($defaults['status'] ?? '')) === (string) ($status['value'] ?? '') ? 'selected' : '' ?>>
                        <?= e((string) ($status['label'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label" for="observations">Descripción</label>
            <textarea class="form-control <?= has_error('observations') ? 'is-invalid' : '' ?>" id="observations" name="observations" rows="5" required><?= e((string) old('observations', (string) ($defaults['observations'] ?? ''))) ?></textarea>
            <?php if (has_error('observations')): ?><div class="invalid-feedback"><?= e(error('observations')) ?></div><?php endif; ?>
        </div>
        <?php if (!empty($canLinkCctv)): ?>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="notify_cctv" name="notify_cctv" <?= old('notify_cctv', (string) ($defaults['notify_cctv'] ?? '')) !== '' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="notify_cctv">
                        Notificar al monitoreo CCTV (bitácora de operadores)
                    </label>
                </div>
                <p class="small text-secondary mb-0">Si hay un turno CCTV abierto, se registrará una coordinación en su bitácora.</p>
            </div>
        <?php endif; ?>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-navy">Registrar novedad</button>
        <a href="<?= e(url('/guards')) ?>" class="btn btn-outline-navy">Cancelar</a>
    </div>
</form>
