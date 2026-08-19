<?php
$index = $index ?? 0;
$row = is_array($row ?? null) ? $row : [];
$description = old('topics.' . $index . '.description', $row['description'] ?? '');
$numericIndex = is_numeric($index) ? (int) $index : 0;
$position = (int) ($row['position'] ?? ($numericIndex + 1));
?>
<article class="meetings-repeat-row" data-meetings-topic-row>
    <div class="meetings-repeat-row__header">
        <strong>Tema <span data-meetings-item-number><?= $position ?></span></strong>
        <button type="button" class="btn btn-link btn-sm text-danger p-0" data-meetings-remove-row>Eliminar</button>
    </div>
    <div class="mb-0">
        <label class="form-label">Descripción</label>
        <textarea class="form-control" name="topics[<?= e((string) $index) ?>][description]" rows="2" required><?= e((string) $description) ?></textarea>
    </div>
</article>
