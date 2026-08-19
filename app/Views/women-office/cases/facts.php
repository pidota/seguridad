<?php
$case = $case ?? [];
$canEdit = !empty($canEdit);
$v = static function (string $key, mixed $fallback = '') use ($case): string {
    $value = old($key, $case[$key] ?? $fallback);

    return $value === null ? '' : (string) $value;
};
$selectedViolence = old('violence_type_ids', $case['violence_type_ids'] ?? []);
if (!is_array($selectedViolence)) {
    $selectedViolence = [];
}
$selectedViolence = array_map('intval', $selectedViolence);
$violenceOther = old('violence_other', []);
if (!is_array($violenceOther)) {
    $violenceOther = [];
}
foreach ($case['violence_types'] ?? [] as $item) {
    $typeId = (int) ($item['violence_type_id'] ?? 0);
    if ($typeId > 0 && !isset($violenceOther[$typeId]) && !empty($item['other_text'])) {
        $violenceOther[$typeId] = (string) $item['other_text'];
    }
}
$precision = $v('incident_date_precision', 'undetermined');
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1"><?= e((string) ($case['case_number'] ?? 'Caso')) ?></p>
        <h2 class="page-card__title mb-0">2. Hechos y tipos de violencia</h2>
        <p class="text-secondary mb-0">Registre los antecedentes del hecho y las formas de violencia denunciadas.</p>
    </div>
    <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . ($case['id'] ?? ''))) ?>">Ver caso</a>
</section>

<?= women_nav($womenNav ?? []) ?>

<?= \Core\View::make('women-office/cases/_steps', ['currentStep' => $currentStep ?? 2], null) ?>

<div class="page-card page-card--md">
    <?php if (!$canEdit): ?>
        <p class="text-secondary">Este caso no puede modificarse con su perfil actual.</p>
    <?php else: ?>
    <form method="post" action="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/facts')) ?>" novalidate autocomplete="off" data-women-case-facts-form>
        <?= csrf_field() ?>

        <h3 class="h6 mb-3">Antecedentes del hecho</h3>

        <div class="mb-3">
            <label class="form-label" for="incident_date_precision">Fecha del hecho</label>
            <select class="form-select <?= has_error('incident_date_precision') ? 'is-invalid' : '' ?>" id="incident_date_precision" name="incident_date_precision" data-women-date-precision required>
                <option value="exact" <?= $precision === 'exact' ? 'selected' : '' ?>>Fecha exacta</option>
                <option value="approximate" <?= $precision === 'approximate' ? 'selected' : '' ?>>Fecha aproximada</option>
                <option value="undetermined" <?= $precision === 'undetermined' ? 'selected' : '' ?>>No determinada</option>
            </select>
            <?php if (has_error('incident_date_precision')): ?><div class="invalid-feedback"><?= e((string) error('incident_date_precision')) ?></div><?php endif; ?>
        </div>

        <div class="row" data-women-incident-date-row <?= $precision === 'undetermined' ? 'hidden' : '' ?>>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="incident_date">Fecha</label>
                <input type="date" class="form-control <?= has_error('incident_date') ? 'is-invalid' : '' ?>" id="incident_date" name="incident_date" value="<?= e($v('incident_date')) ?>">
                <?php if (has_error('incident_date')): ?><div class="invalid-feedback"><?= e((string) error('incident_date')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="incident_time">Hora aproximada</label>
                <input type="time" class="form-control <?= has_error('incident_time') ? 'is-invalid' : '' ?>" id="incident_time" name="incident_time" value="<?= e(substr($v('incident_time'), 0, 5)) ?>">
                <?php if (has_error('incident_time')): ?><div class="invalid-feedback"><?= e((string) error('incident_time')) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="incident_time_notes">Referencia horaria u observaciones</label>
            <input class="form-control <?= has_error('incident_time_notes') ? 'is-invalid' : '' ?>" id="incident_time_notes" name="incident_time_notes" value="<?= e($v('incident_time_notes')) ?>" placeholder="Ej.: en la noche, al regresar del trabajo">
            <?php if (has_error('incident_time_notes')): ?><div class="invalid-feedback"><?= e((string) error('incident_time_notes')) ?></div><?php endif; ?>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="incident_place">Lugar</label>
                <input class="form-control" id="incident_place" name="incident_place" value="<?= e($v('incident_place')) ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="incident_sector_id">Sector</label>
                <select class="form-select" id="incident_sector_id" name="incident_sector_id">
                    <option value="">Seleccione</option>
                    <?php foreach ($sectors ?? [] as $sector): ?>
                        <option value="<?= (int) $sector['id'] ?>" <?= $v('incident_sector_id') === (string) $sector['id'] ? 'selected' : '' ?>><?= e((string) $sector['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="incident_address">Dirección o referencia</label>
            <input class="form-control" id="incident_address" name="incident_address" value="<?= e($v('incident_address')) ?>">
        </div>

        <div class="mb-4">
            <label class="form-label" for="description">Descripción de los hechos</label>
            <textarea class="form-control <?= has_error('description') ? 'is-invalid' : '' ?>" id="description" name="description" rows="8" required><?= e($v('description')) ?></textarea>
            <div class="form-text">Relato libre. No se limita artificialmente la extensión del relato.</div>
            <?php if (has_error('description')): ?><div class="invalid-feedback"><?= e((string) error('description')) ?></div><?php endif; ?>
        </div>

        <h3 class="h6 mb-3">Tipos de violencia</h3>
        <?php if (has_error('violence_type_ids')): ?>
            <div class="alert alert-danger py-2"><?= e((string) error('violence_type_ids')) ?></div>
        <?php endif; ?>

        <div class="women-violence-grid mb-3">
            <?php foreach ($violenceTypes ?? [] as $type): ?>
                <?php
                $typeId = (int) $type['id'];
                $checked = in_array($typeId, $selectedViolence, true);
                $isOther = ($type['slug'] ?? '') === 'otra';
                ?>
                <div class="women-violence-option">
                    <label class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="violence_type_ids[]"
                            value="<?= $typeId ?>"
                            data-slug="<?= e((string) $type['slug']) ?>"
                            data-women-violence-toggle
                            <?= $checked ? 'checked' : '' ?>
                        >
                        <span class="form-check-label"><?= e((string) $type['name']) ?></span>
                    </label>
                    <?php if ($isOther): ?>
                        <div class="women-violence-option__other" data-women-violence-other="<?= $typeId ?>" <?= $checked ? '' : 'hidden' ?>>
                            <input
                                class="form-control form-control-sm <?= has_error('violence_other_' . $typeId) ? 'is-invalid' : '' ?>"
                                type="text"
                                name="violence_other[<?= $typeId ?>]"
                                value="<?= e((string) ($violenceOther[$typeId] ?? '')) ?>"
                                placeholder="Especifique"
                            >
                            <?php if (has_error('violence_other_' . $typeId)): ?>
                                <div class="invalid-feedback d-block"><?= e((string) error('violence_other_' . $typeId)) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <button class="btn btn-navy" type="submit">Guardar hechos</button>
        <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . ($case['id'] ?? ''))) ?>">Cancelar</a>
    </form>
    <?php endif; ?>
</div>
