<?php
$case = $case ?? [];
$aggressor = $aggressor ?? [];
$canEdit = !empty($canEdit);
$v = static function (string $key, mixed $fallback = '') use ($aggressor, $case): string {
    $aggressorKey = str_starts_with($key, 'aggressor_') ? substr($key, 10) : $key;
    $source = str_starts_with($key, 'aggressor_') ? $aggressor : $case;
    $value = old($key, $source[$aggressorKey] ?? $fallback);

    return $value === null ? '' : (string) $value;
};
$selectedRelationship = (string) old('relationship_type_id', (string) ($case['relationship_type_id'] ?? ''));
$showRelationshipOther = false;
foreach ($relationshipTypes ?? [] as $type) {
    if ((string) $type['id'] === $selectedRelationship && ($type['slug'] ?? '') === 'otro') {
        $showRelationshipOther = true;
        break;
    }
}
$currentRelationship = $v('current_relationship');
$hasBirthDate = $v('aggressor_birth_date') !== '';
$hasApproxAge = $v('aggressor_approximate_age') !== '';
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1"><?= e((string) ($case['case_number'] ?? 'Caso')) ?></p>
        <h2 class="page-card__title mb-0">3. Persona denunciada</h2>
        <p class="text-secondary mb-0">Registre solo los antecedentes disponibles. Ningún campo es obligatorio.</p>
    </div>
    <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . ($case['id'] ?? ''))) ?>">Ver caso</a>
</section>

<?= women_nav($womenNav ?? []) ?>

<?= \Core\View::make('women-office/cases/_steps', ['currentStep' => $currentStep ?? 3], null) ?>

<div class="page-card page-card--md">
    <?php if (!$canEdit): ?>
        <p class="text-secondary">Este caso no puede modificarse con su perfil actual.</p>
    <?php else: ?>
    <form method="post" action="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/aggressor')) ?>" novalidate autocomplete="off" data-women-case-aggressor-form>
        <?= csrf_field() ?>

        <h3 class="h6 mb-3">Relación con la persona afectada</h3>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="relationship_type_id">Tipo de relación</label>
                <select class="form-select <?= has_error('relationship_type_id') ? 'is-invalid' : '' ?>" id="relationship_type_id" name="relationship_type_id" data-women-relationship-toggle>
                    <option value="">Seleccione</option>
                    <?php foreach ($relationshipTypes ?? [] as $type): ?>
                        <option
                            value="<?= (int) $type['id'] ?>"
                            data-slug="<?= e((string) $type['slug']) ?>"
                            <?= $selectedRelationship === (string) $type['id'] ? 'selected' : '' ?>
                        ><?= e((string) $type['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3" data-women-relationship-other <?= $showRelationshipOther ? '' : 'hidden' ?>>
                <label class="form-label" for="relationship_other">Especifique relación</label>
                <input class="form-control <?= has_error('relationship_other') ? 'is-invalid' : '' ?>" id="relationship_other" name="relationship_other" value="<?= e($v('relationship_other')) ?>">
                <?php if (has_error('relationship_other')): ?><div class="invalid-feedback"><?= e((string) error('relationship_other')) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label" for="current_relationship">¿Mantienen actualmente relación?</label>
            <select class="form-select" id="current_relationship" name="current_relationship">
                <option value="">Seleccione</option>
                <option value="yes" <?= $currentRelationship === 'yes' ? 'selected' : '' ?>>Sí</option>
                <option value="no" <?= $currentRelationship === 'no' ? 'selected' : '' ?>>No</option>
                <option value="unknown" <?= $currentRelationship === 'unknown' ? 'selected' : '' ?>>No informado</option>
            </select>
        </div>

        <h3 class="h6 mb-3">Antecedentes de la persona denunciada</h3>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="aggressor_first_names">Nombres</label>
                <input class="form-control" id="aggressor_first_names" name="aggressor_first_names" value="<?= e($v('aggressor_first_names')) ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label" for="aggressor_paternal_surname">Apellido paterno</label>
                <input class="form-control" id="aggressor_paternal_surname" name="aggressor_paternal_surname" value="<?= e($v('aggressor_paternal_surname')) ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label" for="aggressor_maternal_surname">Apellido materno</label>
                <input class="form-control" id="aggressor_maternal_surname" name="aggressor_maternal_surname" value="<?= e($v('aggressor_maternal_surname')) ?>">
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label" for="aggressor_rut">RUT</label>
                <input class="form-control <?= has_error('aggressor_rut') ? 'is-invalid' : '' ?>" id="aggressor_rut" name="aggressor_rut" value="<?= e($v('aggressor_rut')) ?>" data-rut-input>
                <?php if (has_error('aggressor_rut')): ?><div class="invalid-feedback"><?= e((string) error('aggressor_rut')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4 mb-3" data-women-aggressor-birth <?= ($hasApproxAge && !$hasBirthDate) ? 'hidden' : '' ?>>
                <label class="form-label" for="aggressor_birth_date">Fecha de nacimiento</label>
                <input type="date" class="form-control <?= has_error('aggressor_birth_date') ? 'is-invalid' : '' ?>" id="aggressor_birth_date" name="aggressor_birth_date" value="<?= e($v('aggressor_birth_date')) ?>" data-women-aggressor-birth-input>
                <?php if (has_error('aggressor_birth_date')): ?><div class="invalid-feedback"><?= e((string) error('aggressor_birth_date')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4 mb-3" data-women-aggressor-age <?= ($hasBirthDate && !$hasApproxAge) ? 'hidden' : '' ?>>
                <label class="form-label" for="aggressor_approximate_age">Edad aproximada</label>
                <input class="form-control <?= has_error('aggressor_approximate_age') ? 'is-invalid' : '' ?>" id="aggressor_approximate_age" name="aggressor_approximate_age" value="<?= e($v('aggressor_approximate_age')) ?>" placeholder="Ej.: 35 años" data-women-aggressor-age-input>
                <?php if (has_error('aggressor_approximate_age')): ?><div class="invalid-feedback"><?= e((string) error('aggressor_approximate_age')) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label" for="aggressor_phone">Teléfono</label>
                <input class="form-control" id="aggressor_phone" name="aggressor_phone" value="<?= e($v('aggressor_phone')) ?>">
            </div>
            <div class="col-md-8 mb-3">
                <label class="form-label" for="aggressor_address">Domicilio</label>
                <input class="form-control" id="aggressor_address" name="aggressor_address" value="<?= e($v('aggressor_address')) ?>">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="aggressor_occupation">Ocupación</label>
            <input class="form-control" id="aggressor_occupation" name="aggressor_occupation" value="<?= e($v('aggressor_occupation')) ?>">
        </div>

        <div class="mb-4">
            <label class="form-label" for="aggressor_notes">Observaciones</label>
            <textarea class="form-control" id="aggressor_notes" name="aggressor_notes" rows="3"><?= e($v('aggressor_notes')) ?></textarea>
        </div>

        <button class="btn btn-navy" type="submit">Guardar persona denunciada</button>
        <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/facts')) ?>">Volver a hechos</a>
    </form>
    <?php endif; ?>
</div>
