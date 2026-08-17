<?php
$completingDraft = !empty($completingDraft);
$hasRecord = $record !== null;
$isEdit = $hasRecord && !$completingDraft;
$isReferral = !empty($isReferral);
$defaults = $defaults ?? ['attention_date' => date('Y-m-d'), 'attention_time' => date('H:i')];
$institutionTypes = $institutionTypes ?? [];
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-0"><?= $isEdit ? 'Editar atención' : 'Registro de Atención' ?></h2>
        <?php if (($isEdit || $completingDraft) && !empty($record['attention_number'])): ?>
            <p class="mb-0"><span class="senda-badge senda-badge--referral"><?= e((string) $record['attention_number']) ?></span></p>
        <?php endif; ?>
    </div>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<?php if (!empty($person)): ?>
    <div class="mb-3">
        <?= \Core\View::make('senda/people/card', [
            'person' => $person,
            'showUse' => false,
            'compact' => true,
        ], null) ?>
        <?php if (!$isEdit): ?>
            <p class="mt-2 mb-0">
                <a href="<?= e(url('/senda')) ?>">Cambiar persona</a>
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="page-card page-card--lg">
    <?php if (!empty($entryType)): ?>
        <div class="senda-entry-inline mb-4">
            <span class="senda-entry-bar__kicker">Tipo de ingreso</span>
            <span class="senda-badge senda-badge--<?= e($entryType['tone']) ?>"><?= e($entryType['label']) ?></span>
            <?php if ($isEdit): ?>
                <span class="text-secondary small">Quedó registrado al crear la atención.</span>
            <?php elseif ($completingDraft): ?>
                <span class="text-secondary small">Complete los datos de la atención para finalizar el registro.</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form
        method="post"
        action="<?= e($isEdit ? url('/senda/attentions/' . $record['id']) : url('/senda/attentions')) ?>"
        novalidate
        data-senda-attention-form
        data-entry-type="<?= e((string) ($entryType['value'] ?? ($record['entry_type'] ?? ''))) ?>"
    >
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <?= method_field('PUT') ?>
        <?php else: ?>
            <input type="hidden" name="entry_type" value="<?= e((string) ($entryType['value'] ?? '')) ?>">
            <input type="hidden" name="senda_person_id" value="<?= e((string) ($person['id'] ?? '')) ?>">
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="attention_date">Fecha de atención</label>
                <input type="date" class="form-control <?= has_error('attention_date') ? 'is-invalid' : '' ?>" id="attention_date" name="attention_date" value="<?= e((string) old('attention_date', $hasRecord ? (string) $record['attention_date'] : (string) $defaults['attention_date'])) ?>" required>
                <?php if (has_error('attention_date')): ?><div class="invalid-feedback"><?= e((string) error('attention_date')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="attention_time">Hora de atención</label>
                <input type="time" class="form-control <?= has_error('attention_time') ? 'is-invalid' : '' ?>" id="attention_time" name="attention_time" value="<?= e((string) old('attention_time', $hasRecord ? (string) ($record['attention_time_short'] ?? '') : (string) $defaults['attention_time'])) ?>" required>
                <?php if (has_error('attention_time')): ?><div class="invalid-feedback"><?= e((string) error('attention_time')) ?></div><?php endif; ?>
            </div>
        </div>

        <fieldset class="senda-referral-fieldset mb-4" data-senda-referral-panel <?= $isReferral ? '' : 'hidden' ?>>
                <legend>Antecedentes de derivación</legend>
                <div class="mb-3">
                    <label class="form-label" for="referral_institution_type">Tipo de institución</label>
                    <select class="form-select <?= has_error('referral_institution_type') ? 'is-invalid' : '' ?>" id="referral_institution_type" name="referral_institution_type" <?= $isReferral ? '' : 'disabled' ?>>
                        <option value="">Seleccione</option>
                        <?php foreach ($institutionTypes as $option): ?>
                            <option value="<?= e($option['value']) ?>" <?= (string) old('referral_institution_type', $hasRecord ? (string) ($record['referral_institution_type'] ?? '') : '') === $option['value'] ? 'selected' : '' ?>>
                                <?= e($option['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('referral_institution_type')): ?><div class="invalid-feedback"><?= e((string) error('referral_institution_type')) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="referral_institution_name">Nombre de institución</label>
                    <input class="form-control <?= has_error('referral_institution_name') ? 'is-invalid' : '' ?>" id="referral_institution_name" name="referral_institution_name" value="<?= e((string) old('referral_institution_name', $hasRecord ? (string) ($record['referral_institution_name'] ?? '') : '')) ?>" <?= $isReferral ? '' : 'disabled' ?>>
                    <?php if (has_error('referral_institution_name')): ?><div class="invalid-feedback"><?= e((string) error('referral_institution_name')) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="referral_person">Persona/profesional que deriva</label>
                    <input class="form-control <?= has_error('referral_person') ? 'is-invalid' : '' ?>" id="referral_person" name="referral_person" value="<?= e((string) old('referral_person', $hasRecord ? (string) ($record['referral_person'] ?? '') : '')) ?>" <?= $isReferral ? '' : 'disabled' ?>>
                    <?php if (has_error('referral_person')): ?><div class="invalid-feedback"><?= e((string) error('referral_person')) ?></div><?php endif; ?>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="referral_phone">Teléfono</label>
                        <input class="form-control <?= has_error('referral_phone') ? 'is-invalid' : '' ?>" id="referral_phone" name="referral_phone" value="<?= e((string) old('referral_phone', $hasRecord ? (string) ($record['referral_phone'] ?? '') : '')) ?>" <?= $isReferral ? '' : 'disabled' ?>>
                        <?php if (has_error('referral_phone')): ?><div class="invalid-feedback"><?= e((string) error('referral_phone')) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="referral_email">Correo</label>
                        <input type="email" class="form-control <?= has_error('referral_email') ? 'is-invalid' : '' ?>" id="referral_email" name="referral_email" value="<?= e((string) old('referral_email', $hasRecord ? (string) ($record['referral_email'] ?? '') : '')) ?>" <?= $isReferral ? '' : 'disabled' ?>>
                        <?php if (has_error('referral_email')): ?><div class="invalid-feedback"><?= e((string) error('referral_email')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label" for="referral_notes">Observaciones</label>
                    <textarea class="form-control <?= has_error('referral_notes') ? 'is-invalid' : '' ?>" id="referral_notes" name="referral_notes" rows="3" <?= $isReferral ? '' : 'disabled' ?>><?= e((string) old('referral_notes', $hasRecord ? (string) ($record['referral_notes'] ?? '') : '')) ?></textarea>
                    <?php if (has_error('referral_notes')): ?><div class="invalid-feedback"><?= e((string) error('referral_notes')) ?></div><?php endif; ?>
                </div>
            </fieldset>

        <div class="mb-4">
            <label class="form-label" for="summary">Observaciones de la atención</label>
            <textarea class="form-control <?= has_error('summary') ? 'is-invalid' : '' ?>" id="summary" name="summary" rows="3"><?= e((string) old('summary', $hasRecord ? (string) ($record['summary'] ?? '') : '')) ?></textarea>
            <?php if (has_error('summary')): ?><div class="invalid-feedback"><?= e((string) error('summary')) ?></div><?php endif; ?>
        </div>
        <button class="btn btn-navy" type="submit">Guardar</button>
        <a class="btn btn-outline-navy" href="<?= e(url('/senda/attentions')) ?>">Cancelar</a>
    </form>
</div>

<?php if ($isEdit && (hasPermission('senda.followups.view') || hasPermission('senda.followups.create'))): ?>
    <?= \Core\View::make('senda/followups/history', [
        'followups' => $followups ?? [],
        'attentionId' => (int) $record['id'],
        'returnTo' => 'attention',
    ], null) ?>
<?php endif; ?>
