<?php
$v = static function (string $key, mixed $fallback = '') use ($record): string {
    $value = old($key, $record[$key] ?? $fallback);

    return $value === null ? '' : (string) $value;
};
$safeContact = $v('safe_contact');
$isEdit = !empty($isEdit);
$personId = (int) ($personId ?? 0);
$returnUrl = isset($returnUrl) && is_string($returnUrl) ? $returnUrl : null;
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Oficina de la Mujer</p>
        <h2 class="page-card__title mb-0"><?= $isEdit ? 'Editar persona afectada' : 'Registrar persona afectada' ?></h2>
    </div>
</section>

<?= women_nav($womenNav ?? []) ?>

<div class="page-card page-card--md">
    <form method="post" action="<?= e($isEdit ? url('/women/people/' . $personId) : url('/women/people')) ?>" novalidate autocomplete="off" data-women-person-form>
        <?= csrf_field() ?>
        <?php if ($isEdit && $returnUrl !== null): ?>
            <input type="hidden" name="return_url" value="<?= e($returnUrl) ?>">
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label" for="rut">RUT</label>
            <input class="form-control" id="rut" name="rut" value="<?= e((string) ($rut ?? '')) ?>" readonly data-rut-input required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="first_names">Nombres</label>
            <input class="form-control <?= has_error('first_names') ? 'is-invalid' : '' ?>" id="first_names" name="first_names" value="<?= e($v('first_names')) ?>" required>
            <?php if (has_error('first_names')): ?><div class="invalid-feedback"><?= e((string) error('first_names')) ?></div><?php endif; ?>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="paternal_surname">Apellido paterno</label>
                <input class="form-control <?= has_error('paternal_surname') ? 'is-invalid' : '' ?>" id="paternal_surname" name="paternal_surname" value="<?= e($v('paternal_surname')) ?>" required>
                <?php if (has_error('paternal_surname')): ?><div class="invalid-feedback"><?= e((string) error('paternal_surname')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="maternal_surname">Apellido materno</label>
                <input class="form-control <?= has_error('maternal_surname') ? 'is-invalid' : '' ?>" id="maternal_surname" name="maternal_surname" value="<?= e($v('maternal_surname')) ?>">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="birth_date">Fecha de nacimiento</label>
            <input type="date" class="form-control <?= has_error('birth_date') ? 'is-invalid' : '' ?>" id="birth_date" name="birth_date" value="<?= e($v('birth_date')) ?>" required>
            <div class="form-text">La edad se calcula desde la fecha de nacimiento; no se almacena.</div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="phone">Teléfono</label>
                <input class="form-control" id="phone" name="phone" value="<?= e($v('phone')) ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="email">Correo electrónico</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= e($v('email')) ?>">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="address">Dirección</label>
            <input class="form-control" id="address" name="address" value="<?= e($v('address')) ?>">
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="sector_id">Sector</label>
                <select class="form-select" id="sector_id" name="sector_id">
                    <option value="">Seleccione</option>
                    <?php foreach ($sectors ?? [] as $sector): ?>
                        <option value="<?= (int) $sector['id'] ?>" <?= $v('sector_id') === (string) $sector['id'] ? 'selected' : '' ?>><?= e((string) $sector['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="education_level_id">Nivel educacional</label>
                <select class="form-select" id="education_level_id" name="education_level_id">
                    <option value="">Seleccione</option>
                    <?php foreach ($educationLevels ?? [] as $level): ?>
                        <option value="<?= (int) $level['id'] ?>" <?= $v('education_level_id') === (string) $level['id'] ? 'selected' : '' ?>><?= e((string) $level['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="nationality">Nacionalidad</label>
                <input class="form-control" id="nationality" name="nationality" value="<?= e($v('nationality')) ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="occupation">Ocupación</label>
                <input class="form-control" id="occupation" name="occupation" value="<?= e($v('occupation')) ?>">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="safe_contact">¿Es seguro contactar a esta persona?</label>
            <select class="form-select" id="safe_contact" name="safe_contact" data-women-safe-contact-toggle>
                <option value="">Seleccione</option>
                <option value="yes" <?= $safeContact === 'yes' ? 'selected' : '' ?>>Sí</option>
                <option value="no" <?= $safeContact === 'no' ? 'selected' : '' ?>>No</option>
                <option value="restricted" <?= $safeContact === 'restricted' ? 'selected' : '' ?>>Con restricciones</option>
            </select>
        </div>
        <div class="mb-3" data-women-safe-contact-notes <?= $safeContact === 'restricted' ? '' : 'hidden' ?>>
            <label class="form-label" for="safe_contact_notes">Indicaciones de contacto seguro</label>
            <textarea class="form-control <?= has_error('safe_contact_notes') ? 'is-invalid' : '' ?>" id="safe_contact_notes" name="safe_contact_notes" rows="3"><?= e($v('safe_contact_notes')) ?></textarea>
            <?php if (has_error('safe_contact_notes')): ?><div class="invalid-feedback"><?= e((string) error('safe_contact_notes')) ?></div><?php endif; ?>
        </div>

        <button class="btn btn-navy" type="submit"><?= $isEdit ? 'Guardar cambios' : 'Guardar y continuar' ?></button>
        <?php if ($isEdit && $returnUrl !== null): ?>
            <a class="btn btn-outline-navy" href="<?= e($returnUrl) ?>">Volver al caso</a>
        <?php else: ?>
            <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/create/person')) ?>">Volver</a>
        <?php endif; ?>
    </form>
</div>
