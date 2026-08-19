<?php
$index = $index ?? 0;
$row = is_array($row ?? null) ? $row : [];
$ageRanges = is_array($ageRanges ?? null) ? $ageRanges : [];
$ageRangeId = old('linked_minors.' . $index . '.age_range_id', $row['age_range_id'] ?? '');
$gender = old('linked_minors.' . $index . '.gender', $row['gender'] ?? '');
$notes = old('linked_minors.' . $index . '.notes', $row['notes'] ?? '');
?>
<article class="women-repeat-row" data-women-linked-minor-row>
    <div class="women-repeat-row__header">
        <strong class="women-repeat-row__title">NNA vinculado</strong>
        <button type="button" class="btn btn-link btn-sm text-danger p-0" data-women-remove-linked-minor>Eliminar</button>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">Rango etario</label>
            <select class="form-select" name="linked_minors[<?= e((string) $index) ?>][age_range_id]">
                <option value="">Seleccione</option>
                <?php foreach ($ageRanges as $range): ?>
                    <option value="<?= (int) $range['id'] ?>" <?= (string) $ageRangeId === (string) $range['id'] ? 'selected' : '' ?>>
                        <?= e((string) $range['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Sexo</label>
            <select class="form-select" name="linked_minors[<?= e((string) $index) ?>][gender]">
                <option value="">Seleccione</option>
                <option value="female" <?= $gender === 'female' ? 'selected' : '' ?>>Femenino</option>
                <option value="male" <?= $gender === 'male' ? 'selected' : '' ?>>Masculino</option>
                <option value="other" <?= $gender === 'other' ? 'selected' : '' ?>>Otro</option>
                <option value="unknown" <?= $gender === 'unknown' ? 'selected' : '' ?>>No informado</option>
            </select>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Observaciones (solo datos estrictamente necesarios)</label>
        <textarea class="form-control" name="linked_minors[<?= e((string) $index) ?>][notes]" rows="2"><?= e((string) $notes) ?></textarea>
    </div>
</article>
