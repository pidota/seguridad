<?php
$case = $case ?? [];
$formalReport = is_array($formalReport ?? null) ? $formalReport : [];
$canEdit = !empty($canEdit);
$v = static function (string $key, mixed $fallback = '') use ($case, $formalReport): string {
    if (str_starts_with($key, 'formal_report_')) {
        $formalKey = substr($key, 14);
        $map = [
            'institution_id' => 'institution_id',
            'institution_other' => 'institution_other',
            'reference_number' => 'reference_number',
            'date' => 'report_date',
            'notes' => 'notes',
        ];
        $sourceKey = $map[$formalKey] ?? $formalKey;
        $value = old($key, $formalReport[$sourceKey] ?? $fallback);
    } else {
        $value = old($key, $case[$key] ?? $fallback);
    }

    return $value === null ? '' : (string) $value;
};
$firstOccurrence = $v('is_first_occurrence');
$hasPrevious = $v('has_previous_reports');
$hasFormal = $v('has_formal_current_report');
$selectedFormalInstitution = $v('formal_report_institution_id');
$showFormalOther = false;
foreach ($formalInstitutions ?? [] as $institution) {
    if ((string) $institution['id'] === $selectedFormalInstitution && ($institution['slug'] ?? '') === 'otra') {
        $showFormalOther = true;
        break;
    }
}
$rows = $previousReports ?? [];
if ($rows === []) {
    $rows = [['institution_name' => '', 'report_date' => '', 'reference_number' => '', 'notes' => '']];
}
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1"><?= e((string) ($case['case_number'] ?? 'Caso')) ?></p>
        <h2 class="page-card__title mb-0">4. Antecedentes</h2>
        <p class="text-secondary mb-0">Frecuencia, denuncias anteriores y denuncia formal del hecho actual.</p>
    </div>
    <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . ($case['id'] ?? ''))) ?>">Ver caso</a>
</section>

<?= women_nav($womenNav ?? []) ?>

<?= \Core\View::make('women-office/cases/_steps', ['currentStep' => $currentStep ?? 4], null) ?>

<div class="page-card page-card--md">
    <?php if (!$canEdit): ?>
        <p class="text-secondary">Este caso no puede modificarse con su perfil actual.</p>
    <?php else: ?>
    <form method="post" action="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/background')) ?>" novalidate autocomplete="off" data-women-case-background-form>
        <?= csrf_field() ?>

        <h3 class="h6 mb-3">¿Es la primera vez que ocurre una situación similar?</h3>
        <div class="mb-3">
            <select class="form-select" id="is_first_occurrence" name="is_first_occurrence" data-women-first-occurrence-toggle>
                <option value="">Seleccione</option>
                <option value="yes" <?= $firstOccurrence === 'yes' ? 'selected' : '' ?>>Sí</option>
                <option value="no" <?= $firstOccurrence === 'no' ? 'selected' : '' ?>>No</option>
                <option value="unknown" <?= $firstOccurrence === 'unknown' ? 'selected' : '' ?>>No informado</option>
            </select>
        </div>

        <div data-women-recurrence-panel <?= $firstOccurrence === 'no' ? '' : 'hidden' ?>>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="occurrence_frequency">Frecuencia aproximada</label>
                    <input class="form-control" id="occurrence_frequency" name="occurrence_frequency" value="<?= e($v('occurrence_frequency')) ?>" placeholder="Ej.: semanalmente">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="occurring_since">Desde cuándo ocurre</label>
                    <input class="form-control" id="occurring_since" name="occurring_since" value="<?= e($v('occurring_since')) ?>" placeholder="Ej.: hace 2 años">
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="occurrence_notes">Observaciones</label>
                <textarea class="form-control" id="occurrence_notes" name="occurrence_notes" rows="3"><?= e($v('occurrence_notes')) ?></textarea>
            </div>
        </div>

        <h3 class="h6 mb-3">Denuncias anteriores relacionadas</h3>
        <div class="mb-3">
            <select class="form-select" id="has_previous_reports" name="has_previous_reports" data-women-previous-reports-toggle>
                <option value="">Seleccione</option>
                <option value="yes" <?= $hasPrevious === 'yes' ? 'selected' : '' ?>>Sí</option>
                <option value="no" <?= $hasPrevious === 'no' ? 'selected' : '' ?>>No</option>
                <option value="unknown" <?= $hasPrevious === 'unknown' ? 'selected' : '' ?>>No informado</option>
            </select>
        </div>

        <div data-women-previous-reports-panel <?= $hasPrevious === 'yes' ? '' : 'hidden' ?>>
            <?php if (has_error('previous_reports')): ?>
                <div class="alert alert-danger py-2"><?= e((string) error('previous_reports')) ?></div>
            <?php endif; ?>
            <div data-women-previous-reports-list>
                <?php foreach ($rows as $index => $row): ?>
                    <?= \Core\View::make('women-office/cases/_previous_report_row', [
                        'index' => $index,
                        'row' => $row,
                    ], null) ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-outline-navy btn-sm mb-4" data-women-add-previous-report>Agregar antecedente</button>
        </div>

        <h3 class="h6 mb-3">Denuncia formal del hecho actual</h3>
        <div class="mb-3">
            <select class="form-select" id="has_formal_current_report" name="has_formal_current_report" data-women-formal-report-toggle>
                <option value="">Seleccione</option>
                <option value="yes" <?= $hasFormal === 'yes' ? 'selected' : '' ?>>Sí</option>
                <option value="no" <?= $hasFormal === 'no' ? 'selected' : '' ?>>No</option>
                <option value="unknown" <?= $hasFormal === 'unknown' ? 'selected' : '' ?>>No informado</option>
            </select>
        </div>

        <div data-women-formal-report-panel <?= $hasFormal === 'yes' ? '' : 'hidden' ?>>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="formal_report_institution_id">Institución</label>
                    <select class="form-select <?= has_error('formal_report_institution_id') ? 'is-invalid' : '' ?>" id="formal_report_institution_id" name="formal_report_institution_id" data-women-formal-institution-toggle>
                        <option value="">Seleccione</option>
                        <?php foreach ($formalInstitutions ?? [] as $institution): ?>
                            <option
                                value="<?= (int) $institution['id'] ?>"
                                data-slug="<?= e((string) $institution['slug']) ?>"
                                <?= $selectedFormalInstitution === (string) $institution['id'] ? 'selected' : '' ?>
                            ><?= e((string) $institution['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('formal_report_institution_id')): ?><div class="invalid-feedback"><?= e((string) error('formal_report_institution_id')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3" data-women-formal-institution-other <?= $showFormalOther ? '' : 'hidden' ?>>
                    <label class="form-label" for="formal_report_institution_other">Especifique institución</label>
                    <input class="form-control <?= has_error('formal_report_institution_other') ? 'is-invalid' : '' ?>" id="formal_report_institution_other" name="formal_report_institution_other" value="<?= e($v('formal_report_institution_other')) ?>">
                    <?php if (has_error('formal_report_institution_other')): ?><div class="invalid-feedback"><?= e((string) error('formal_report_institution_other')) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="formal_report_reference_number">N.º denuncia / parte / causa</label>
                    <input class="form-control" id="formal_report_reference_number" name="formal_report_reference_number" value="<?= e($v('formal_report_reference_number')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="formal_report_date">Fecha</label>
                    <input type="date" class="form-control" id="formal_report_date" name="formal_report_date" value="<?= e($v('formal_report_date')) ?>">
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="formal_report_notes">Observaciones</label>
                <textarea class="form-control" id="formal_report_notes" name="formal_report_notes" rows="3"><?= e($v('formal_report_notes')) ?></textarea>
            </div>
        </div>

        <button class="btn btn-navy" type="submit">Guardar antecedentes</button>
        <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/aggressor')) ?>">Volver</a>
    </form>
    <?php endif; ?>
</div>

<template data-women-previous-report-template>
    <?= \Core\View::make('women-office/cases/_previous_report_row', [
        'index' => '__INDEX__',
        'row' => [],
    ], null) ?>
</template>
