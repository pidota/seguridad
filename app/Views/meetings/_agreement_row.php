<?php
$index = $index ?? 0;
$row = is_array($row ?? null) ? $row : [];
$description = old('agreements.' . $index . '.description', $row['description'] ?? '');
$responsibleUserId = old('agreements.' . $index . '.responsible_user_id', $row['responsible_user_id'] ?? '');
$responsibleText = old('agreements.' . $index . '.responsible_text', $row['responsible_text'] ?? '');
$dueDate = old('agreements.' . $index . '.due_date', $row['due_date'] ?? '');
$numericIndex = is_numeric($index) ? (int) $index : 0;
$position = (int) ($row['position'] ?? ($numericIndex + 1));
?>
<article class="meetings-repeat-row" data-meetings-agreement-row>
    <div class="meetings-repeat-row__header">
        <strong>Acuerdo <span data-meetings-item-number><?= $position ?></span></strong>
        <button type="button" class="btn btn-link btn-sm text-danger p-0" data-meetings-remove-row>Eliminar</button>
    </div>
    <div class="mb-3">
        <label class="form-label">Descripción</label>
        <textarea class="form-control" name="agreements[<?= e((string) $index) ?>][description]" rows="2" required><?= e((string) $description) ?></textarea>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Responsable (texto libre)</label>
            <input class="form-control" name="agreements[<?= e((string) $index) ?>][responsible_text]" value="<?= e((string) $responsibleText) ?>" placeholder="Ej.: Juan Pérez">
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Fecha compromiso</label>
            <input type="date" class="form-control" name="agreements[<?= e((string) $index) ?>][due_date]" value="<?= e((string) $dueDate) ?>">
        </div>
    </div>
    <input type="hidden" name="agreements[<?= e((string) $index) ?>][responsible_user_id]" value="<?= e((string) $responsibleUserId) ?>">
</article>
