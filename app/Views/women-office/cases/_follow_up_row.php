<?php
$index = $index ?? 0;
$row = is_array($row ?? null) ? $row : [];
$contactTypes = is_array($contactTypes ?? null) ? $contactTypes : [];
$followUpResults = is_array($followUpResults ?? null) ? $followUpResults : [];
$id = old('followups.' . $index . '.id', $row['id'] ?? '');
$followUpDate = old('followups.' . $index . '.follow_up_date', $row['follow_up_date'] ?? '');
$followUpTime = old('followups.' . $index . '.follow_up_time', $row['follow_up_time_short'] ?? $row['follow_up_time'] ?? '');
if (is_string($followUpTime) && strlen($followUpTime) >= 5) {
    $followUpTime = substr($followUpTime, 0, 5);
}
$contactTypeId = old('followups.' . $index . '.contact_type_id', $row['contact_type_id'] ?? '');
$contactOther = old('followups.' . $index . '.contact_type_other', $row['contact_type_other'] ?? '');
$resultId = old('followups.' . $index . '.result_id', $row['result_id'] ?? '');
$resultOther = old('followups.' . $index . '.result_other', $row['result_other'] ?? '');
$notes = old('followups.' . $index . '.notes', $row['notes'] ?? '');
$requires = (string) old('followups.' . $index . '.requires_follow_up', !empty($row['requires_follow_up']) ? 'yes' : 'no');
$nextDate = old('followups.' . $index . '.next_follow_up_date', $row['next_follow_up_date'] ?? '');
$showContactOther = false;
$showResultOther = false;
foreach ($contactTypes as $type) {
    if ((string) $contactTypeId === (string) $type['id'] && ($type['slug'] ?? '') === 'otro') {
        $showContactOther = true;
        break;
    }
}
foreach ($followUpResults as $result) {
    if ((string) $resultId === (string) $result['id'] && ($result['slug'] ?? '') === 'otro') {
        $showResultOther = true;
        break;
    }
}
?>
<article class="women-repeat-row" data-women-followup-row>
    <div class="women-repeat-row__header">
        <strong class="women-repeat-row__title">Seguimiento</strong>
        <button type="button" class="btn btn-link btn-sm text-danger p-0" data-women-remove-followup>Eliminar</button>
    </div>
    <?php if ($id !== '' && $id !== null): ?>
        <input type="hidden" name="followups[<?= e((string) $index) ?>][id]" value="<?= e((string) $id) ?>">
    <?php endif; ?>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">Fecha</label>
            <input
                type="date"
                class="form-control <?= has_error('followups_' . $index . '_date') ? 'is-invalid' : '' ?>"
                name="followups[<?= e((string) $index) ?>][follow_up_date]"
                value="<?= e((string) $followUpDate) ?>"
            >
            <?php if (has_error('followups_' . $index . '_date')): ?>
                <div class="invalid-feedback"><?= e((string) error('followups_' . $index . '_date')) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Hora</label>
            <input
                type="time"
                class="form-control <?= has_error('followups_' . $index . '_time') ? 'is-invalid' : '' ?>"
                name="followups[<?= e((string) $index) ?>][follow_up_time]"
                value="<?= e((string) $followUpTime) ?>"
            >
            <?php if (has_error('followups_' . $index . '_time')): ?>
                <div class="invalid-feedback"><?= e((string) error('followups_' . $index . '_time')) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Tipo de contacto</label>
            <select
                class="form-select <?= has_error('followups_' . $index . '_contact') ? 'is-invalid' : '' ?>"
                name="followups[<?= e((string) $index) ?>][contact_type_id]"
                data-women-followup-contact-toggle
            >
                <option value="">Seleccione</option>
                <?php foreach ($contactTypes as $type): ?>
                    <option
                        value="<?= (int) $type['id'] ?>"
                        data-slug="<?= e((string) $type['slug']) ?>"
                        <?= (string) $contactTypeId === (string) $type['id'] ? 'selected' : '' ?>
                    ><?= e((string) $type['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (has_error('followups_' . $index . '_contact')): ?>
                <div class="invalid-feedback"><?= e((string) error('followups_' . $index . '_contact')) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="mb-3" data-women-followup-contact-other <?= $showContactOther ? '' : 'hidden' ?>>
        <label class="form-label">Especifique tipo de contacto</label>
        <input
            class="form-control <?= has_error('followups_' . $index . '_contact_other') ? 'is-invalid' : '' ?>"
            name="followups[<?= e((string) $index) ?>][contact_type_other]"
            value="<?= e((string) $contactOther) ?>"
        >
        <?php if (has_error('followups_' . $index . '_contact_other')): ?>
            <div class="invalid-feedback"><?= e((string) error('followups_' . $index . '_contact_other')) ?></div>
        <?php endif; ?>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Resultado</label>
            <select
                class="form-select <?= has_error('followups_' . $index . '_result') ? 'is-invalid' : '' ?>"
                name="followups[<?= e((string) $index) ?>][result_id]"
                data-women-followup-result-toggle
            >
                <option value="">Seleccione</option>
                <?php foreach ($followUpResults as $result): ?>
                    <option
                        value="<?= (int) $result['id'] ?>"
                        data-slug="<?= e((string) $result['slug']) ?>"
                        <?= (string) $resultId === (string) $result['id'] ? 'selected' : '' ?>
                    ><?= e((string) $result['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (has_error('followups_' . $index . '_result')): ?>
                <div class="invalid-feedback"><?= e((string) error('followups_' . $index . '_result')) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="mb-3" data-women-followup-result-other <?= $showResultOther ? '' : 'hidden' ?>>
        <label class="form-label">Especifique resultado</label>
        <input
            class="form-control <?= has_error('followups_' . $index . '_result_other') ? 'is-invalid' : '' ?>"
            name="followups[<?= e((string) $index) ?>][result_other]"
            value="<?= e((string) $resultOther) ?>"
        >
        <?php if (has_error('followups_' . $index . '_result_other')): ?>
            <div class="invalid-feedback"><?= e((string) error('followups_' . $index . '_result_other')) ?></div>
        <?php endif; ?>
    </div>
    <div class="mb-3">
        <label class="form-label">Observaciones</label>
        <textarea class="form-control" name="followups[<?= e((string) $index) ?>][notes]" rows="2"><?= e((string) $notes) ?></textarea>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">¿Requiere nuevo seguimiento?</label>
            <select
                class="form-select <?= has_error('followups_' . $index . '_requires') ? 'is-invalid' : '' ?>"
                name="followups[<?= e((string) $index) ?>][requires_follow_up]"
                data-women-followup-requires-toggle
            >
                <option value="no" <?= $requires === 'no' ? 'selected' : '' ?>>No</option>
                <option value="yes" <?= $requires === 'yes' ? 'selected' : '' ?>>Sí</option>
            </select>
            <?php if (has_error('followups_' . $index . '_requires')): ?>
                <div class="invalid-feedback"><?= e((string) error('followups_' . $index . '_requires')) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-md-6 mb-3" data-women-followup-next-date <?= $requires === 'yes' ? '' : 'hidden' ?>>
            <label class="form-label">Próximo seguimiento</label>
            <input
                type="date"
                class="form-control <?= has_error('followups_' . $index . '_next_date') ? 'is-invalid' : '' ?>"
                name="followups[<?= e((string) $index) ?>][next_follow_up_date]"
                value="<?= e((string) $nextDate) ?>"
            >
            <?php if (has_error('followups_' . $index . '_next_date')): ?>
                <div class="invalid-feedback"><?= e((string) error('followups_' . $index . '_next_date')) ?></div>
            <?php endif; ?>
        </div>
    </div>
</article>
