<?php
$index = $index ?? 0;
$row = is_array($row ?? null) ? $row : [];
$institution = old('previous_reports.' . $index . '.institution_name', $row['institution_name'] ?? '');
$reportDate = old('previous_reports.' . $index . '.report_date', $row['report_date'] ?? '');
$reference = old('previous_reports.' . $index . '.reference_number', $row['reference_number'] ?? '');
$notes = old('previous_reports.' . $index . '.notes', $row['notes'] ?? '');
$errorKey = 'previous_reports_' . $index . '_institution';
?>
<article class="women-repeat-row" data-women-previous-report-row>
    <div class="women-repeat-row__header">
        <strong class="women-repeat-row__title">Antecedente</strong>
        <button type="button" class="btn btn-link btn-sm text-danger p-0" data-women-remove-previous-report>Eliminar</button>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Institución</label>
            <input
                class="form-control <?= has_error($errorKey) ? 'is-invalid' : '' ?>"
                name="previous_reports[<?= e((string) $index) ?>][institution_name]"
                value="<?= e((string) $institution) ?>"
            >
            <?php if (has_error($errorKey)): ?><div class="invalid-feedback"><?= e((string) error($errorKey)) ?></div><?php endif; ?>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Fecha</label>
            <input type="date" class="form-control" name="previous_reports[<?= e((string) $index) ?>][report_date]" value="<?= e((string) $reportDate) ?>">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">N.º denuncia / parte</label>
            <input class="form-control" name="previous_reports[<?= e((string) $index) ?>][reference_number]" value="<?= e((string) $reference) ?>">
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Observaciones</label>
        <textarea class="form-control" name="previous_reports[<?= e((string) $index) ?>][notes]" rows="2"><?= e((string) $notes) ?></textarea>
    </div>
</article>
