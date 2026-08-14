<?php
$equipmentItems = $equipmentItems ?? [];
$statuses = $statuses ?? [];
$equipmentInput = is_array($equipmentInput ?? null) ? $equipmentInput : [];
$generalNotesField = (string) ($generalNotesField ?? 'opening_notes');
$generalNotesLabel = (string) ($generalNotesLabel ?? 'Observaciones generales');
$generalNotesPlaceholder = (string) ($generalNotesPlaceholder ?? '');
$generalNotesHelp = (string) ($generalNotesHelp ?? '');
?>
<div class="cctv-reception-equipment">
    <?php foreach ($equipmentItems as $item): ?>
        <?php
        $equipmentId = (int) ($item['id'] ?? 0);
        $equipmentName = (string) ($item['name'] ?? 'Equipo');
        $entry = is_array($equipmentInput[$equipmentId] ?? null)
            ? $equipmentInput[$equipmentId]
            : (is_array($equipmentInput[(string) $equipmentId] ?? null) ? $equipmentInput[(string) $equipmentId] : []);
        $selectedStatus = (string) ($entry['status'] ?? '');
        $requiresNotes = in_array($selectedStatus, ['con_observaciones', 'no_operativo'], true);
        $statusError = error('equipment.' . $equipmentId . '.status');
        $observationsError = error('equipment.' . $equipmentId . '.observations');
        ?>
        <article class="cctv-reception-item">
            <div class="cctv-reception-item__header">
                <h3 class="cctv-reception-item__title"><?= e($equipmentName) ?></h3>
                <?php if ($statusError): ?>
                    <p class="cctv-reception-item__error"><?= e((string) $statusError) ?></p>
                <?php endif; ?>
            </div>

            <div class="cctv-reception-item__statuses">
                <?php foreach ($statuses as $status): ?>
                    <?php $value = (string) ($status['value'] ?? ''); ?>
                    <label class="cctv-reception-status">
                        <input
                            type="radio"
                            name="equipment[<?= $equipmentId ?>][status]"
                            value="<?= e($value) ?>"
                            data-equipment-status
                            data-equipment-id="<?= $equipmentId ?>"
                            <?= $selectedStatus === $value ? 'checked' : '' ?>
                            required
                        >
                        <span class="cctv-reception-status__label camera-device-badge camera-device-badge--<?= e((string) ($status['tone'] ?? 'other')) ?>">
                            <?= e((string) ($status['label'] ?? $value)) ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div
                class="cctv-reception-item__notes"
                data-equipment-notes
                data-equipment-id="<?= $equipmentId ?>"
                <?= $requiresNotes ? '' : 'hidden' ?>
            >
                <label class="form-label" for="equipment_<?= $equipmentId ?>_observations">
                    Observaciones de <?= e($equipmentName) ?>
                </label>
                <textarea
                    class="form-control <?= $observationsError ? 'is-invalid' : '' ?>"
                    id="equipment_<?= $equipmentId ?>_observations"
                    name="equipment[<?= $equipmentId ?>][observations]"
                    rows="2"
                    maxlength="500"
                    placeholder="Describa la situación detectada"
                ><?= e((string) ($entry['observations'] ?? '')) ?></textarea>
                <?php if ($observationsError): ?>
                    <div class="invalid-feedback"><?= e((string) $observationsError) ?></div>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php if (has_error('equipment')): ?>
    <div class="alert alert-danger"><?= e((string) error('equipment')) ?></div>
<?php endif; ?>

<div class="mb-4">
    <label class="form-label" for="<?= e($generalNotesField) ?>"><?= e($generalNotesLabel) ?></label>
    <textarea
        class="form-control <?= has_error($generalNotesField) ? 'is-invalid' : '' ?>"
        id="<?= e($generalNotesField) ?>"
        name="<?= e($generalNotesField) ?>"
        rows="4"
        maxlength="2000"
        placeholder="<?= e($generalNotesPlaceholder) ?>"
    ><?= e((string) old($generalNotesField, '')) ?></textarea>
    <?php if (has_error($generalNotesField)): ?>
        <div class="invalid-feedback"><?= e((string) error($generalNotesField)) ?></div>
    <?php endif; ?>
    <?php if ($generalNotesHelp !== ''): ?>
        <div class="form-text"><?= e($generalNotesHelp) ?></div>
    <?php endif; ?>
</div>
