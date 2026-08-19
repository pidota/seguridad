<?php
$case = $case ?? [];
$canEdit = !empty($canEdit);
$v = static function (string $key, mixed $fallback = '') use ($case): string {
    $value = old($key, $case[$key] ?? $fallback);

    return $value === null ? '' : (string) $value;
};
$hasProtective = $v('has_protective_measures');
$hasMinors = $v('has_linked_minors');
$hasDependents = $v('has_dependents');
$selectedNeeds = old('need_ids', $case['need_ids'] ?? []);
if (!is_array($selectedNeeds)) {
    $selectedNeeds = [];
}
$selectedNeeds = array_map('intval', $selectedNeeds);
$needOther = old('need_other', []);
if (!is_array($needOther)) {
    $needOther = [];
}
foreach ($case['needs'] ?? [] as $item) {
    $typeId = (int) ($item['need_id'] ?? 0);
    if ($typeId > 0 && !isset($needOther[$typeId]) && !empty($item['other_text'])) {
        $needOther[$typeId] = (string) $item['other_text'];
    }
}
$measureRows = $protectiveMeasures ?? [];
if ($measureRows === []) {
    $measureRows = [['measure_type_id' => '', 'institution' => '', 'start_date' => '', 'end_date' => '', 'cause_number' => '', 'notes' => '']];
}
$minorRows = $linkedMinors ?? [];
if ($minorRows === []) {
    $minorRows = [['age_range_id' => '', 'gender' => '', 'notes' => '']];
}
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1"><?= e((string) ($case['case_number'] ?? 'Caso')) ?></p>
        <h2 class="page-card__title mb-0">6. Medidas y necesidades</h2>
        <p class="text-secondary mb-0">Medidas de protección informadas, necesidades de atención, NNA y personas dependientes.</p>
    </div>
    <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . ($case['id'] ?? ''))) ?>">Ver caso</a>
</section>

<?= women_nav($womenNav ?? []) ?>

<?= \Core\View::make('women-office/cases/_steps', ['currentStep' => $currentStep ?? 6], null) ?>

<div class="page-card page-card--md">
    <?php if (!$canEdit): ?>
        <p class="text-secondary">Este caso no puede modificarse con su perfil actual.</p>
    <?php else: ?>
    <form method="post" action="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/support')) ?>" novalidate autocomplete="off" data-women-case-support-form>
        <?= csrf_field() ?>

        <h3 class="h6 mb-3">Medidas de protección informadas</h3>
        <div class="mb-3">
            <select class="form-select" id="has_protective_measures" name="has_protective_measures" data-women-protective-measures-toggle>
                <option value="">Seleccione</option>
                <option value="yes" <?= $hasProtective === 'yes' ? 'selected' : '' ?>>Sí</option>
                <option value="no" <?= $hasProtective === 'no' ? 'selected' : '' ?>>No</option>
                <option value="unknown" <?= $hasProtective === 'unknown' ? 'selected' : '' ?>>No informado</option>
            </select>
        </div>

        <div data-women-protective-measures-panel <?= $hasProtective === 'yes' ? '' : 'hidden' ?>>
            <?php if (has_error('protective_measures')): ?>
                <div class="alert alert-danger py-2"><?= e((string) error('protective_measures')) ?></div>
            <?php endif; ?>
            <div data-women-protective-measures-list>
                <?php foreach ($measureRows as $index => $row): ?>
                    <?= \Core\View::make('women-office/cases/_protective_measure_row', [
                        'index' => $index,
                        'row' => $row,
                        'measureTypes' => $measureTypes ?? [],
                    ], null) ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-outline-navy btn-sm mb-4" data-women-add-protective-measure>Agregar medida</button>
        </div>

        <h3 class="h6 mb-3">Necesidades identificadas durante la atención</h3>
        <?php if (has_error('need_ids')): ?>
            <div class="alert alert-danger py-2"><?= e((string) error('need_ids')) ?></div>
        <?php endif; ?>
        <div class="women-violence-grid mb-4">
            <?php foreach ($needs ?? [] as $need): ?>
                <?php
                $needId = (int) $need['id'];
                $checked = in_array($needId, $selectedNeeds, true);
                $isOther = ($need['slug'] ?? '') === 'otra';
                ?>
                <div class="women-violence-option">
                    <label class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="need_ids[]"
                            value="<?= $needId ?>"
                            data-slug="<?= e((string) $need['slug']) ?>"
                            data-women-need-toggle
                            <?= $checked ? 'checked' : '' ?>
                        >
                        <span class="form-check-label"><?= e((string) $need['name']) ?></span>
                    </label>
                    <?php if ($isOther): ?>
                        <div class="women-violence-option__other" data-women-need-other="<?= $needId ?>" <?= $checked ? '' : 'hidden' ?>>
                            <input
                                class="form-control form-control-sm <?= has_error('need_other_' . $needId) ? 'is-invalid' : '' ?>"
                                type="text"
                                name="need_other[<?= $needId ?>]"
                                value="<?= e((string) ($needOther[$needId] ?? '')) ?>"
                                placeholder="Especifique"
                            >
                            <?php if (has_error('need_other_' . $needId)): ?>
                                <div class="invalid-feedback d-block"><?= e((string) error('need_other_' . $needId)) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <h3 class="h6 mb-3">Niños, niñas o adolescentes vinculados</h3>
        <p class="text-secondary small">Registre solo datos estrictamente necesarios. Esta información no se muestra en listados generales.</p>
        <div class="mb-3">
            <select class="form-select" id="has_linked_minors" name="has_linked_minors" data-women-linked-minors-toggle>
                <option value="">Seleccione</option>
                <option value="yes" <?= $hasMinors === 'yes' ? 'selected' : '' ?>>Sí</option>
                <option value="no" <?= $hasMinors === 'no' ? 'selected' : '' ?>>No</option>
                <option value="unknown" <?= $hasMinors === 'unknown' ? 'selected' : '' ?>>No informado</option>
            </select>
        </div>

        <div data-women-linked-minors-panel <?= $hasMinors === 'yes' ? '' : 'hidden' ?>>
            <?php if (has_error('linked_minors')): ?>
                <div class="alert alert-danger py-2"><?= e((string) error('linked_minors')) ?></div>
            <?php endif; ?>
            <div data-women-linked-minors-list>
                <?php foreach ($minorRows as $index => $row): ?>
                    <?= \Core\View::make('women-office/cases/_linked_minor_row', [
                        'index' => $index,
                        'row' => $row,
                        'ageRanges' => $ageRanges ?? [],
                    ], null) ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-outline-navy btn-sm mb-4" data-women-add-linked-minor>Agregar NNA</button>
        </div>

        <h3 class="h6 mb-3">Otras personas dependientes a cargo</h3>
        <div class="mb-3">
            <select class="form-select" id="has_dependents" name="has_dependents" data-women-dependents-toggle>
                <option value="">Seleccione</option>
                <option value="yes" <?= $hasDependents === 'yes' ? 'selected' : '' ?>>Sí</option>
                <option value="no" <?= $hasDependents === 'no' ? 'selected' : '' ?>>No</option>
            </select>
        </div>

        <div data-women-dependents-panel <?= $hasDependents === 'yes' ? '' : 'hidden' ?>>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="dependents_count">Cantidad</label>
                    <input type="number" min="1" max="20" class="form-control <?= has_error('dependents_count') ? 'is-invalid' : '' ?>" id="dependents_count" name="dependents_count" value="<?= e($v('dependents_count')) ?>">
                    <?php if (has_error('dependents_count')): ?><div class="invalid-feedback"><?= e((string) error('dependents_count')) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="dependents_notes">Observaciones generales</label>
                <textarea class="form-control" id="dependents_notes" name="dependents_notes" rows="3"><?= e($v('dependents_notes')) ?></textarea>
            </div>
        </div>

        <button class="btn btn-navy" type="submit">Guardar información</button>
        <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/risk-priority')) ?>">Volver</a>
    </form>
    <?php endif; ?>
</div>

<template data-women-protective-measure-template>
    <?= \Core\View::make('women-office/cases/_protective_measure_row', [
        'index' => '__INDEX__',
        'row' => [],
        'measureTypes' => $measureTypes ?? [],
    ], null) ?>
</template>

<template data-women-linked-minor-template>
    <?= \Core\View::make('women-office/cases/_linked_minor_row', [
        'index' => '__INDEX__',
        'row' => [],
        'ageRanges' => $ageRanges ?? [],
    ], null) ?>
</template>
