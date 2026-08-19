<?php
$index = $index ?? 0;
$row = is_array($row ?? null) ? $row : [];
$referralInstitutions = is_array($referralInstitutions ?? null) ? $referralInstitutions : [];
$referralStatuses = is_array($referralStatuses ?? null) ? $referralStatuses : [];
$id = old('referrals.' . $index . '.id', $row['id'] ?? '');
$referralDate = old('referrals.' . $index . '.referral_date', $row['referral_date'] ?? '');
$institutionId = old('referrals.' . $index . '.institution_id', $row['institution_id'] ?? '');
$programArea = old('referrals.' . $index . '.program_area', $row['program_area'] ?? '');
$reason = old('referrals.' . $index . '.reason', $row['reason'] ?? '');
$contactPerson = old('referrals.' . $index . '.contact_person', $row['contact_person'] ?? '');
$statusId = old('referrals.' . $index . '.referral_status_id', $row['referral_status_id'] ?? '');
$notes = old('referrals.' . $index . '.notes', $row['notes'] ?? '');
$showOtherInstitution = false;
foreach ($referralInstitutions as $institution) {
    if ((string) $institutionId === (string) $institution['id'] && ($institution['slug'] ?? '') === 'otra') {
        $showOtherInstitution = true;
        break;
    }
}
?>
<article class="women-repeat-row" data-women-referral-row>
    <div class="women-repeat-row__header">
        <strong class="women-repeat-row__title">Derivación</strong>
        <button type="button" class="btn btn-link btn-sm text-danger p-0" data-women-remove-referral>Eliminar</button>
    </div>
    <?php if ($id !== '' && $id !== null): ?>
        <input type="hidden" name="referrals[<?= e((string) $index) ?>][id]" value="<?= e((string) $id) ?>">
    <?php endif; ?>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">Fecha</label>
            <input
                type="date"
                class="form-control <?= has_error('referrals_' . $index . '_date') ? 'is-invalid' : '' ?>"
                name="referrals[<?= e((string) $index) ?>][referral_date]"
                value="<?= e((string) $referralDate) ?>"
            >
            <?php if (has_error('referrals_' . $index . '_date')): ?>
                <div class="invalid-feedback"><?= e((string) error('referrals_' . $index . '_date')) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-md-8 mb-3">
            <label class="form-label">Estado</label>
            <select
                class="form-select <?= has_error('referrals_' . $index . '_status') ? 'is-invalid' : '' ?>"
                name="referrals[<?= e((string) $index) ?>][referral_status_id]"
            >
                <option value="">Seleccione</option>
                <?php foreach ($referralStatuses as $status): ?>
                    <option value="<?= (int) $status['id'] ?>" <?= (string) $statusId === (string) $status['id'] ? 'selected' : '' ?>>
                        <?= e((string) $status['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (has_error('referrals_' . $index . '_status')): ?>
                <div class="invalid-feedback"><?= e((string) error('referrals_' . $index . '_status')) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Institución de destino</label>
            <select
                class="form-select <?= has_error('referrals_' . $index . '_institution') || has_error('referrals_' . $index . '_destination') ? 'is-invalid' : '' ?>"
                name="referrals[<?= e((string) $index) ?>][institution_id]"
            >
                <option value="">Seleccione</option>
                <?php foreach ($referralInstitutions as $institution): ?>
                    <option
                        value="<?= (int) $institution['id'] ?>"
                        data-slug="<?= e((string) $institution['slug']) ?>"
                        <?= (string) $institutionId === (string) $institution['id'] ? 'selected' : '' ?>
                    ><?= e((string) $institution['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (has_error('referrals_' . $index . '_institution')): ?>
                <div class="invalid-feedback"><?= e((string) error('referrals_' . $index . '_institution')) ?></div>
            <?php endif; ?>
            <?php if (has_error('referrals_' . $index . '_destination')): ?>
                <div class="invalid-feedback d-block"><?= e((string) error('referrals_' . $index . '_destination')) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Área / programa<?= $showOtherInstitution ? ' / especifique institución' : '' ?></label>
            <input
                class="form-control <?= has_error('referrals_' . $index . '_program_area') ? 'is-invalid' : '' ?>"
                name="referrals[<?= e((string) $index) ?>][program_area]"
                value="<?= e((string) $programArea) ?>"
                placeholder="Ej.: UCEJ, programa municipal, nombre institución"
            >
            <?php if (has_error('referrals_' . $index . '_program_area')): ?>
                <div class="invalid-feedback"><?= e((string) error('referrals_' . $index . '_program_area')) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Motivo de derivación</label>
        <textarea
            class="form-control <?= has_error('referrals_' . $index . '_reason') ? 'is-invalid' : '' ?>"
            name="referrals[<?= e((string) $index) ?>][reason]"
            rows="2"
        ><?= e((string) $reason) ?></textarea>
        <?php if (has_error('referrals_' . $index . '_reason')): ?>
            <div class="invalid-feedback"><?= e((string) error('referrals_' . $index . '_reason')) ?></div>
        <?php endif; ?>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Persona de contacto</label>
            <input
                class="form-control <?= has_error('referrals_' . $index . '_contact') ? 'is-invalid' : '' ?>"
                name="referrals[<?= e((string) $index) ?>][contact_person]"
                value="<?= e((string) $contactPerson) ?>"
            >
            <?php if (has_error('referrals_' . $index . '_contact')): ?>
                <div class="invalid-feedback"><?= e((string) error('referrals_' . $index . '_contact')) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Observaciones</label>
        <textarea
            class="form-control <?= has_error('referrals_' . $index . '_notes') ? 'is-invalid' : '' ?>"
            name="referrals[<?= e((string) $index) ?>][notes]"
            rows="2"
        ><?= e((string) $notes) ?></textarea>
        <?php if (has_error('referrals_' . $index . '_notes')): ?>
            <div class="invalid-feedback"><?= e((string) error('referrals_' . $index . '_notes')) ?></div>
        <?php endif; ?>
    </div>
</article>
