<?php
$index = $index ?? 0;
$row = is_array($row ?? null) ? $row : [];
$type = old('participants.' . $index . '.participant_type', $row['participant_type'] ?? 'internal');
$userId = old('participants.' . $index . '.user_id', $row['user_id'] ?? '');
$userName = (string) ($row['user_name'] ?? '');
$signatureRequired = old('participants.' . $index . '.signature_required', !empty($row['signature_required']) ? '1' : '0');
$isInternal = $type === 'internal';
?>
<article class="meetings-repeat-row" data-meetings-participant-row data-participant-type="<?= e((string) $type) ?>">
    <div class="meetings-repeat-row__header">
        <strong><?= $isInternal ? 'Participante interno' : 'Participante externo' ?></strong>
        <button type="button" class="btn btn-link btn-sm text-danger p-0" data-meetings-remove-row>Eliminar</button>
    </div>
    <input type="hidden" name="participants[<?= e((string) $index) ?>][participant_type]" value="<?= e((string) $type) ?>">
    <?php if ($isInternal): ?>
        <div class="row">
            <div class="col-md-8 mb-3">
                <label class="form-label">Usuario del sistema</label>
                <input type="hidden" name="participants[<?= e((string) $index) ?>][user_id]" value="<?= e((string) $userId) ?>" data-meetings-user-id>
                <input type="text" class="form-control" value="<?= e($userName) ?>" placeholder="Buscar por nombre o correo..." data-meetings-user-search autocomplete="off">
                <div class="list-group mt-1 meetings-user-results" data-meetings-user-results hidden></div>
            </div>
            <div class="col-md-4 mb-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="participants[<?= e((string) $index) ?>][signature_required]" value="1" id="sig_<?= e((string) $index) ?>" <?= (string) $signatureRequired === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="sig_<?= e((string) $index) ?>">Requiere firma</label>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Nombre completo</label>
                <input class="form-control" name="participants[<?= e((string) $index) ?>][external_name]" value="<?= e(old('participants.' . $index . '.external_name', $row['external_name'] ?? '')) ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Cargo</label>
                <input class="form-control" name="participants[<?= e((string) $index) ?>][external_position]" value="<?= e(old('participants.' . $index . '.external_position', $row['external_position'] ?? '')) ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Institución / área</label>
                <input class="form-control" name="participants[<?= e((string) $index) ?>][external_organization]" value="<?= e(old('participants.' . $index . '.external_organization', $row['external_organization'] ?? '')) ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Correo (opcional)</label>
                <input type="email" class="form-control" name="participants[<?= e((string) $index) ?>][external_email]" value="<?= e(old('participants.' . $index . '.external_email', $row['external_email'] ?? '')) ?>">
            </div>
        </div>
    <?php endif; ?>
</article>
