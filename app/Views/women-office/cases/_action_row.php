<?php
$index = $index ?? 0;
$row = is_array($row ?? null) ? $row : [];
$actionTypes = is_array($actionTypes ?? null) ? $actionTypes : [];
$id = old('actions.' . $index . '.id', $row['id'] ?? '');
$actionDate = old('actions.' . $index . '.action_date', $row['action_date'] ?? '');
$actionTime = old('actions.' . $index . '.action_time', $row['action_time_short'] ?? $row['action_time'] ?? '');
if (is_string($actionTime) && strlen($actionTime) >= 5) {
    $actionTime = substr($actionTime, 0, 5);
}
$typeId = old('actions.' . $index . '.action_type_id', $row['action_type_id'] ?? '');
$description = old('actions.' . $index . '.description', $row['description'] ?? '');
$institution = old('actions.' . $index . '.institution', $row['institution'] ?? '');
$selectedSlug = '';
foreach ($actionTypes as $type) {
    if ((string) $typeId === (string) $type['id']) {
        $selectedSlug = (string) ($type['slug'] ?? '');
        break;
    }
}
?>
<article class="women-repeat-row" data-women-action-row>
    <div class="women-repeat-row__header">
        <strong class="women-repeat-row__title">Acción</strong>
        <button type="button" class="btn btn-link btn-sm text-danger p-0" data-women-remove-action>Eliminar</button>
    </div>
    <?php if ($id !== '' && $id !== null): ?>
        <input type="hidden" name="actions[<?= e((string) $index) ?>][id]" value="<?= e((string) $id) ?>">
    <?php endif; ?>
    <div class="row">
        <div class="col-md-3 mb-3">
            <label class="form-label">Fecha</label>
            <input
                type="date"
                class="form-control <?= has_error('actions_' . $index . '_date') ? 'is-invalid' : '' ?>"
                name="actions[<?= e((string) $index) ?>][action_date]"
                value="<?= e((string) $actionDate) ?>"
            >
            <?php if (has_error('actions_' . $index . '_date')): ?>
                <div class="invalid-feedback"><?= e((string) error('actions_' . $index . '_date')) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Hora</label>
            <input
                type="time"
                class="form-control <?= has_error('actions_' . $index . '_time') ? 'is-invalid' : '' ?>"
                name="actions[<?= e((string) $index) ?>][action_time]"
                value="<?= e((string) $actionTime) ?>"
            >
            <?php if (has_error('actions_' . $index . '_time')): ?>
                <div class="invalid-feedback"><?= e((string) error('actions_' . $index . '_time')) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Tipo de acción</label>
            <select
                class="form-select <?= has_error('actions_' . $index . '_type') ? 'is-invalid' : '' ?>"
                name="actions[<?= e((string) $index) ?>][action_type_id]"
                data-women-action-type-toggle
            >
                <option value="">Seleccione</option>
                <?php foreach ($actionTypes as $type): ?>
                    <option
                        value="<?= (int) $type['id'] ?>"
                        data-slug="<?= e((string) $type['slug']) ?>"
                        <?= (string) $typeId === (string) $type['id'] ? 'selected' : '' ?>
                    ><?= e((string) $type['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (has_error('actions_' . $index . '_type')): ?>
                <div class="invalid-feedback"><?= e((string) error('actions_' . $index . '_type')) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Institución relacionada</label>
            <input
                class="form-control <?= has_error('actions_' . $index . '_institution') ? 'is-invalid' : '' ?>"
                name="actions[<?= e((string) $index) ?>][institution]"
                value="<?= e((string) $institution) ?>"
                placeholder="Opcional"
            >
            <?php if (has_error('actions_' . $index . '_institution')): ?>
                <div class="invalid-feedback"><?= e((string) error('actions_' . $index . '_institution')) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="mb-3" data-women-action-description-wrap>
        <label class="form-label">
            Descripción
            <?php if ($selectedSlug === 'otra'): ?>
                <span class="text-danger">*</span>
            <?php endif; ?>
        </label>
        <textarea
            class="form-control <?= has_error('actions_' . $index . '_description') ? 'is-invalid' : '' ?>"
            name="actions[<?= e((string) $index) ?>][description]"
            rows="3"
            data-women-action-description
            placeholder="<?= $selectedSlug === 'otra' ? 'Especifique la acción realizada' : 'Detalle de la acción' ?>"
        ><?= e((string) $description) ?></textarea>
        <?php if (has_error('actions_' . $index . '_description')): ?>
            <div class="invalid-feedback"><?= e((string) error('actions_' . $index . '_description')) ?></div>
        <?php endif; ?>
    </div>
</article>
