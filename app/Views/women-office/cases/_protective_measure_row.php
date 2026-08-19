<?php
$index = $index ?? 0;
$row = is_array($row ?? null) ? $row : [];
$measureTypes = is_array($measureTypes ?? null) ? $measureTypes : [];
$typeId = old('protective_measures.' . $index . '.measure_type_id', $row['measure_type_id'] ?? '');
$institution = old('protective_measures.' . $index . '.institution', $row['institution'] ?? '');
$startDate = old('protective_measures.' . $index . '.start_date', $row['start_date'] ?? '');
$endDate = old('protective_measures.' . $index . '.end_date', $row['end_date'] ?? '');
$causeNumber = old('protective_measures.' . $index . '.cause_number', $row['cause_number'] ?? '');
$notes = old('protective_measures.' . $index . '.notes', $row['notes'] ?? '');
?>
<article class="women-repeat-row" data-women-protective-measure-row>
    <div class="women-repeat-row__header">
        <strong class="women-repeat-row__title">Medida informada</strong>
        <button type="button" class="btn btn-link btn-sm text-danger p-0" data-women-remove-protective-measure>Eliminar</button>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Tipo de medida</label>
            <select class="form-select" name="protective_measures[<?= e((string) $index) ?>][measure_type_id]">
                <option value="">Seleccione</option>
                <?php foreach ($measureTypes as $type): ?>
                    <option value="<?= (int) $type['id'] ?>" <?= (string) $typeId === (string) $type['id'] ? 'selected' : '' ?>>
                        <?= e((string) $type['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Institución / tribunal</label>
            <input class="form-control" name="protective_measures[<?= e((string) $index) ?>][institution]" value="<?= e((string) $institution) ?>">
        </div>
    </div>
    <div class="row">
        <div class="col-md-3 mb-3">
            <label class="form-label">Fecha inicio</label>
            <input type="date" class="form-control" name="protective_measures[<?= e((string) $index) ?>][start_date]" value="<?= e((string) $startDate) ?>">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Fecha término</label>
            <input type="date" class="form-control" name="protective_measures[<?= e((string) $index) ?>][end_date]" value="<?= e((string) $endDate) ?>">
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">N.º causa / RIT</label>
            <input class="form-control" name="protective_measures[<?= e((string) $index) ?>][cause_number]" value="<?= e((string) $causeNumber) ?>">
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Observaciones</label>
        <textarea class="form-control" name="protective_measures[<?= e((string) $index) ?>][notes]" rows="2"><?= e((string) $notes) ?></textarea>
    </div>
</article>
