<?php
$isEdit = !empty($record['id']);
$locked = !empty($locked);
$suggestAssist = !empty($suggestAssist);
$missingPersonFields = $missingPersonFields ?? [];
$attention = $attention ?? [];
$person = $person ?? [];
$v = static function (string $key, mixed $fallback = '') use ($record): string {
    $value = $record[$key] ?? $fallback;
    if ($value === null) {
        $value = '';
    }

    return (string) old($key, $value);
};
$assist = old('assist', $record['assist'] ?? []);
if (!is_array($assist)) {
    $assist = \App\Services\Senda\AssistedReferralCatalog::emptyAssist();
}
$selectedSubstanceKeys = old('substance_keys', $record['substance_keys'] ?? []);
if (!is_array($selectedSubstanceKeys)) {
    $selectedSubstanceKeys = [];
}
$destinationCenterStored = $v('destination_center');
$destinationCenterSelect = (string) old('destination_center_select', '');
$destinationCenterOther = (string) old('destination_center_other', '');

if ($destinationCenterSelect === '') {
    if (\App\Services\Senda\AssistedReferralCatalog::isPresetDestinationCenter($destinationCenterStored)) {
        $destinationCenterSelect = $destinationCenterStored;
    } elseif ($destinationCenterStored !== '') {
        $destinationCenterSelect = 'otros';
        $destinationCenterOther = $destinationCenterStored;
    }
}
$applicantKind = $v('applicant_kind');
$relationshipOptions = \App\Services\Senda\AssistedReferralCatalog::applicantRelationshipsForKind($applicantKind);
$storedRelationship = $v('applicant_relationship');
if ($storedRelationship !== '') {
    $knownValues = array_column($relationshipOptions, 'value');
    if (!in_array($storedRelationship, $knownValues, true)) {
        array_unshift($relationshipOptions, [
            'value' => $storedRelationship,
            'label' => \App\Services\Senda\AssistedReferralCatalog::applicantRelationshipLabel($storedRelationship),
        ]);
    }
}
$steps = [
    1 => 'Datos de solicitud',
    2 => 'Quién realiza la solicitud',
    3 => 'Persona que requiere atención',
    4 => 'Antecedentes',
    5 => 'Tratamientos previos SENDA',
    6 => 'Evaluación de riesgo',
    7 => 'Riesgo de consumo',
    8 => 'Observaciones',
];
$hasError = static function (string $field): bool {
    return has_error($field);
};
$optionList = static function (array $options, string $selected): string {
    $html = '';
    foreach ($options as $option) {
        $value = (string) $option['value'];
        $isSelected = $selected === $value ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $isSelected . '>' . e((string) $option['label']) . '</option>';
    }

    return $html;
};
$screeningUsed = $v('screening_used');
$skipAfterScreening = $screeningUsed === 'no';
$riskEvalFields = [
    'suicide_risk',
    'violence_risk',
    'overall_risk',
    'street_situation',
    'pregnancy',
    'children_in_care',
    'risk_notes',
];
$showRiskEvalStep = false;
foreach ($riskEvalFields as $riskField) {
    if ($hasError($riskField)) {
        $showRiskEvalStep = true;
        break;
    }
}
if (!$showRiskEvalStep) {
    foreach ($riskEvalFields as $riskField) {
        if (trim($v($riskField)) !== '') {
            $showRiskEvalStep = true;
            break;
        }
    }
}
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-1"><?= $isEdit ? 'Editar ficha de referencia' : 'Ficha de Referencia Asistida a Tratamiento' ?></h2>
        <?php if (!empty($attention['attention_number'])): ?>
            <p class="mb-0">
                Atención
                <span class="senda-badge senda-badge--referral"><?= e((string) $attention['attention_number']) ?></span>
                <?php if (!empty($record['is_completed'])): ?>
                    <span class="status-pill is-on"><?= e((string) ($record['status_label'] ?? 'Finalizada')) ?></span>
                <?php else: ?>
                    <span class="status-pill is-off"><?= e((string) ($record['status_label'] ?? 'Borrador')) ?></span>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
    <a class="btn btn-outline-navy" href="<?= e((string) ($cancelUrl ?? url('/senda/referrals'))) ?>">
        <?= !empty($returnFlow) ? 'Volver' : 'Volver al listado' ?>
    </a>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<?php if ($locked): ?>
    <div class="alert alert-warning">Esta ficha está finalizada. Se requiere el permiso para modificar fichas finalizadas.</div>
<?php endif; ?>

<form
    method="post"
    action="<?= e($isEdit ? url('/senda/referrals/' . $record['id']) : url('/senda/referrals')) ?>"
    novalidate
    class="senda-wizard"
    data-senda-referral-form
    data-skip-observations="<?= $skipAfterScreening ? '1' : '0' ?>"
    data-show-risk-eval="<?= $showRiskEvalStep ? '1' : '0' ?>"
    data-applicant-person-name="<?= e((string) ($person['full_name'] ?? '')) ?>"
    data-applicant-person-phone="<?= e((string) ($person['phone'] ?? '')) ?>"
    data-applicant-person-email="<?= e((string) ($person['email'] ?? '')) ?>"
    data-applicant-referral-name="<?= e((string) ($attention['referral_person'] ?? '')) ?>"
    data-applicant-referral-phone="<?= e((string) ($attention['referral_phone'] ?? '')) ?>"
    data-applicant-referral-email="<?= e((string) ($attention['referral_email'] ?? '')) ?>"
    data-senda-family-relationships="<?= e(json_encode($familyRelationships ?? [], JSON_UNESCAPED_UNICODE)) ?>"
    data-senda-institutional-relationships="<?= e(json_encode($institutionalRelationships ?? [], JSON_UNESCAPED_UNICODE)) ?>"
>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <?= method_field('PUT') ?>
    <?php endif; ?>
    <input type="hidden" name="senda_attention_id" value="<?= e((string) ($attention['id'] ?? $v('senda_attention_id'))) ?>">
    <?php if (!empty($returnFlow)): ?>
        <input type="hidden" name="return_flow" value="entry">
    <?php endif; ?>

    <nav class="senda-steps" aria-label="Secciones de la ficha">
        <?php foreach ($steps as $number => $label): ?>
            <button type="button" class="senda-steps__item<?= $number === 1 ? ' is-active' : '' ?>" data-step-goto="<?= $number ?>"<?= $number === 6 && !$showRiskEvalStep ? ' hidden' : '' ?><?= $number === 8 && $skipAfterScreening ? ' hidden' : '' ?>>
                <span class="senda-steps__num"><?= $number ?></span>
                <span class="senda-steps__label"><?= e($label) ?></span>
            </button>
        <?php endforeach; ?>
    </nav>

    <fieldset <?= $locked ? 'disabled' : '' ?>>
        <section class="page-card senda-step is-active" data-step="1">
            <h3 class="page-card__title">1. Datos de solicitud</h3>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="request_date">Fecha</label>
                    <input type="date" class="form-control <?= $hasError('request_date') ? 'is-invalid' : '' ?>" id="request_date" name="request_date" value="<?= e($v('request_date')) ?>" required>
                    <?php if ($hasError('request_date')): ?><div class="invalid-feedback"><?= e((string) error('request_date')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label" for="demand_origin">Origen de demanda</label>
                    <?php if (!empty($demandOriginLocked)): ?>
                        <input type="hidden" name="demand_origin" value="<?= e(\App\Services\Senda\DemandOrigin::ESPONTANEA) ?>">
                        <input class="form-control" id="demand_origin" value="<?= e(\App\Services\Senda\DemandOrigin::label(\App\Services\Senda\DemandOrigin::ESPONTANEA)) ?>" readonly>
                        <div class="form-text">Se selecciona automáticamente porque la atención es demanda espontánea.</div>
                    <?php else: ?>
                        <select class="form-select <?= $hasError('demand_origin') ? 'is-invalid' : '' ?>" id="demand_origin" name="demand_origin" required>
                            <option value="">Seleccione</option>
                            <?= $optionList($demandOrigins ?? [], $v('demand_origin')) ?>
                        </select>
                        <?php if ($hasError('demand_origin')): ?><div class="invalid-feedback"><?= e((string) error('demand_origin')) ?></div><?php endif; ?>
                    <?php endif; ?>
                    <?php if ((string) ($attention['entry_type'] ?? '') === \App\Services\Senda\EntryType::DERIVACION && !empty($attention['referral_institution_name'])): ?>
                        <div class="form-text">Antecedente de la atención: <?= e((string) $attention['referral_institution_name']) ?><?= !empty($attention['referral_institution_type_label']) ? ' (' . e((string) $attention['referral_institution_type_label']) . ')' : '' ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="receiving_officer">Funcionario que acoge la demanda</label>
                    <input class="form-control <?= $hasError('receiving_officer') ? 'is-invalid' : '' ?>" id="receiving_officer" name="receiving_officer" value="<?= e($v('receiving_officer')) ?>" required>
                    <?php if ($hasError('receiving_officer')): ?><div class="invalid-feedback"><?= e((string) error('receiving_officer')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="demand_area">Área</label>
                    <input class="form-control <?= $hasError('demand_area') ? 'is-invalid' : '' ?>" id="demand_area" name="demand_area" value="<?= e($v('demand_area')) ?>">
                    <?php if ($hasError('demand_area')): ?><div class="invalid-feedback"><?= e((string) error('demand_area')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label" for="request_type">Tipo de solicitud</label>
                    <select class="form-select <?= $hasError('request_type') ? 'is-invalid' : '' ?>" id="request_type" name="request_type">
                        <option value="">Seleccione</option>
                        <?= $optionList($requestTypes ?? [], $v('request_type')) ?>
                    </select>
                    <?php if ($hasError('request_type')): ?><div class="invalid-feedback"><?= e((string) error('request_type')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="requesting_device">Dispositivo o programa que solicita</label>
                    <input type="hidden" name="requesting_device" value="<?= e(\App\Services\Senda\AssistedReferralCatalog::REQUESTING_DEVICE) ?>">
                    <input class="form-control" id="requesting_device" value="<?= e(\App\Services\Senda\AssistedReferralCatalog::REQUESTING_DEVICE) ?>" readonly>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="requesting_commune">Comuna de origen</label>
                    <input class="form-control <?= $hasError('requesting_commune') ? 'is-invalid' : '' ?>" id="requesting_commune" name="requesting_commune" value="<?= e($v('requesting_commune')) ?>">
                    <?php if ($hasError('requesting_commune')): ?><div class="invalid-feedback"><?= e((string) error('requesting_commune')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="destination_center_select">Centro o dispositivo de destino</label>
                    <select class="form-select <?= $hasError('destination_center_select') ? 'is-invalid' : '' ?>" id="destination_center_select" name="destination_center_select" data-senda-destination-toggle>
                        <option value="">Seleccione</option>
                        <?= $optionList($destinationCenters ?? [], $destinationCenterSelect) ?>
                    </select>
                    <?php if ($hasError('destination_center_select')): ?><div class="invalid-feedback"><?= e((string) error('destination_center_select')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3" data-senda-destination-other <?= $destinationCenterSelect === 'otros' ? '' : 'hidden' ?>>
                    <label class="form-label" for="destination_center_other">Especifique otro centro o dispositivo</label>
                    <input class="form-control <?= $hasError('destination_center_other') ? 'is-invalid' : '' ?>" id="destination_center_other" name="destination_center_other" value="<?= e($destinationCenterOther) ?>" maxlength="180">
                    <div class="invalid-feedback" data-senda-destination-other-error><?= $hasError('destination_center_other') ? e((string) error('destination_center_other')) : 'Indique el centro o dispositivo de destino.' ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="destination_commune">Comuna de destino</label>
                    <input class="form-control <?= $hasError('destination_commune') ? 'is-invalid' : '' ?>" id="destination_commune" name="destination_commune" value="<?= e($v('destination_commune')) ?>">
                    <?php if ($hasError('destination_commune')): ?><div class="invalid-feedback"><?= e((string) error('destination_commune')) ?></div><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="page-card senda-step" data-step="2">
            <h3 class="page-card__title">2. Indique quién realiza la solicitud</h3>
            <div class="senda-choice-grid senda-choice-grid--three mb-4<?= $hasError('applicant_kind') ? ' is-invalid' : '' ?>" role="radiogroup" aria-label="Quién realiza la solicitud">
                <?php foreach ($applicantKinds ?? [] as $kind): ?>
                    <label class="senda-choice-card<?= $v('applicant_kind') === $kind['value'] ? ' is-current' : '' ?>">
                        <input
                            class="form-check-input senda-choice-card__radio"
                            type="radio"
                            name="applicant_kind"
                            value="<?= e($kind['value']) ?>"
                            data-senda-applicant-kind
                            <?= $v('applicant_kind') === $kind['value'] ? 'checked' : '' ?>
                            required
                        >
                        <span class="senda-choice-card__label"><?= e($kind['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if ($hasError('applicant_kind')): ?>
                <p class="text-danger small"><?= e((string) error('applicant_kind')) ?></p>
            <?php endif; ?>

            <div data-senda-applicant-person <?= $v('applicant_kind') === 'persona_implicada' ? '' : 'hidden' ?>>
                <p class="text-secondary">La solicitud la realiza la persona atendida. Se reutilizan sus antecedentes.</p>
                <dl class="senda-person-card__meta senda-person-card__meta--wide mb-0">
                    <div>
                        <dt>Nombre completo</dt>
                        <dd><?= e((string) (($person['full_name'] ?? '') !== '' ? $person['full_name'] : '—')) ?></dd>
                    </div>
                    <div>
                        <dt>Teléfono</dt>
                        <dd><?= e((string) (($person['phone'] ?? '') !== '' ? $person['phone'] : '—')) ?></dd>
                    </div>
                    <div>
                        <dt>Correo electrónico</dt>
                        <dd><?= e((string) (($person['email'] ?? '') !== '' ? $person['email'] : '—')) ?></dd>
                    </div>
                </dl>
            </div>

            <div class="row" data-senda-applicant-extra <?= in_array($v('applicant_kind'), ['familiar', 'institucional'], true) ? '' : 'hidden' ?>>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="applicant_name">Nombre completo</label>
                    <input class="form-control <?= $hasError('applicant_name') ? 'is-invalid' : '' ?>" id="applicant_name" name="applicant_name" value="<?= e($v('applicant_name')) ?>">
                    <?php if ($hasError('applicant_name')): ?><div class="invalid-feedback"><?= e((string) error('applicant_name')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="applicant_relationship">Tipo de relación</label>
                    <select class="form-select <?= $hasError('applicant_relationship') ? 'is-invalid' : '' ?>" id="applicant_relationship" name="applicant_relationship" data-senda-applicant-relationship>
                        <option value="">Seleccione</option>
                        <?= $optionList($relationshipOptions, $storedRelationship) ?>
                    </select>
                    <?php if ($hasError('applicant_relationship')): ?><div class="invalid-feedback"><?= e((string) error('applicant_relationship')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="applicant_phone">Teléfono</label>
                    <input class="form-control <?= $hasError('applicant_phone') ? 'is-invalid' : '' ?>" id="applicant_phone" name="applicant_phone" value="<?= e($v('applicant_phone')) ?>">
                    <?php if ($hasError('applicant_phone')): ?><div class="invalid-feedback"><?= e((string) error('applicant_phone')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="applicant_email">Correo electrónico</label>
                    <input type="email" class="form-control <?= $hasError('applicant_email') ? 'is-invalid' : '' ?>" id="applicant_email" name="applicant_email" value="<?= e($v('applicant_email')) ?>">
                    <?php if ($hasError('applicant_email')): ?><div class="invalid-feedback"><?= e((string) error('applicant_email')) ?></div><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="page-card senda-step" data-step="3">
            <h3 class="page-card__title">3. Persona que requiere atención</h3>
            <p class="text-secondary">Datos de la persona asociada a la atención. No se vuelven a solicitar.</p>

            <?php if (!empty($person)): ?>
                <div class="mb-3">
                    <?= \Core\View::make('senda/people/card', [
                        'person' => $person,
                        'showUse' => false,
                        'compact' => false,
                    ], null) ?>
                </div>
                <dl class="senda-person-card__meta senda-person-card__meta--wide mb-3">
                    <div>
                        <dt>Domicilio</dt>
                        <dd><?= e((string) (($person['address'] ?? '') !== '' ? $person['address'] : '—')) ?></dd>
                    </div>
                    <div>
                        <dt>Correo electrónico</dt>
                        <dd><?= e((string) (($person['email'] ?? '') !== '' ? $person['email'] : '—')) ?></dd>
                    </div>
                    <div>
                        <dt>Educación</dt>
                        <dd><?= e((string) (($person['education'] ?? '') !== '' ? $person['education'] : '—')) ?></dd>
                    </div>
                    <div>
                        <dt>Ocupación</dt>
                        <dd><?= e((string) (($person['occupation'] ?? '') !== '' ? $person['occupation'] : '—')) ?></dd>
                    </div>
                </dl>
            <?php endif; ?>

            <?php if ($missingPersonFields !== [] && !empty($person['id'])): ?>
                <p class="small mb-3">
                    Faltan en la ficha de persona: <?= e(implode(', ', $missingPersonFields)) ?>.
                    <?php if (hasPermission('senda.people.edit')): ?>
                        <a href="<?= e(url('/senda/people/' . $person['id'] . '/edit')) ?>">Completar en el registro de la persona</a>.
                    <?php endif; ?>
                </p>
            <?php elseif (!empty($person['id']) && hasPermission('senda.people.edit')): ?>
                <p class="small mb-3"><a href="<?= e(url('/senda/people/' . $person['id'] . '/edit')) ?>">Actualizar datos de la persona</a></p>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="gender">Sexo</label>
                    <select class="form-select <?= $hasError('gender') ? 'is-invalid' : '' ?>" id="gender" name="gender">
                        <option value="">Seleccione</option>
                        <?= $optionList($genders ?? [], $v('gender')) ?>
                    </select>
                    <?php if ($hasError('gender')): ?><div class="invalid-feedback"><?= e((string) error('gender')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="health_insurance">Previsión</label>
                    <input class="form-control <?= $hasError('health_insurance') ? 'is-invalid' : '' ?>" id="health_insurance" name="health_insurance" value="<?= e($v('health_insurance')) ?>">
                    <?php if ($hasError('health_insurance')): ?><div class="invalid-feedback"><?= e((string) error('health_insurance')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="nationality">Nacionalidad</label>
                    <input class="form-control <?= $hasError('nationality') ? 'is-invalid' : '' ?>" id="nationality" name="nationality" value="<?= e($v('nationality')) ?>">
                    <?php if ($hasError('nationality')): ?><div class="invalid-feedback"><?= e((string) error('nationality')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="indigenous_people">Pueblo originario</label>
                    <input class="form-control <?= $hasError('indigenous_people') ? 'is-invalid' : '' ?>" id="indigenous_people" name="indigenous_people" value="<?= e($v('indigenous_people')) ?>">
                    <?php if ($hasError('indigenous_people')): ?><div class="invalid-feedback"><?= e((string) error('indigenous_people')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="emergency_contact_name">Contacto de emergencia</label>
                    <input class="form-control <?= $hasError('emergency_contact_name') ? 'is-invalid' : '' ?>" id="emergency_contact_name" name="emergency_contact_name" value="<?= e($v('emergency_contact_name')) ?>">
                    <?php if ($hasError('emergency_contact_name')): ?><div class="invalid-feedback"><?= e((string) error('emergency_contact_name')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="emergency_contact_phone">Teléfono de emergencia</label>
                    <input class="form-control <?= $hasError('emergency_contact_phone') ? 'is-invalid' : '' ?>" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= e($v('emergency_contact_phone')) ?>">
                    <?php if ($hasError('emergency_contact_phone')): ?><div class="invalid-feedback"><?= e((string) error('emergency_contact_phone')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="enrolled_health_center">Inscrita en Centro de Salud</label>
                    <select class="form-select <?= $hasError('enrolled_health_center') ? 'is-invalid' : '' ?>" id="enrolled_health_center" name="enrolled_health_center" data-senda-cesfam-toggle>
                        <option value="">Seleccione</option>
                        <?= $optionList($yesNo ?? [], $v('enrolled_health_center')) ?>
                    </select>
                    <div class="invalid-feedback" data-senda-cesfam-enrolled-error><?= $hasError('enrolled_health_center') ? e((string) error('enrolled_health_center')) : 'Indique si está inscrita en un centro de salud.' ?></div>
                </div>
                <div class="col-md-8 mb-3" data-senda-cesfam <?= $v('enrolled_health_center') === 'si' ? '' : 'hidden' ?>>
                    <label class="form-label" for="cesfam_name">Nombre del CESFAM</label>
                    <input class="form-control <?= $hasError('cesfam_name') ? 'is-invalid' : '' ?>" id="cesfam_name" name="cesfam_name" value="<?= e($v('cesfam_name')) ?>" maxlength="180">
                    <div class="invalid-feedback" data-senda-cesfam-name-error><?= $hasError('cesfam_name') ? e((string) error('cesfam_name')) : 'Indique el nombre del CESFAM.' ?></div>
                </div>
            </div>
        </section>

        <section class="page-card senda-step" data-step="4">
            <h3 class="page-card__title">4. Antecedentes</h3>
            <div class="mb-3">
                <span class="form-label d-block">Antecedentes de consumo</span>
                <p class="form-text mb-2">Seleccione todas las sustancias que apliquen.</p>
                <?php if (has_error('substance_keys')): ?>
                    <div class="text-danger small fw-semibold mb-2"><?= e((string) error('substance_keys')) ?></div>
                <?php endif; ?>
                <div class="senda-substance-checklist">
                    <?php foreach ($consumptionSubstances ?? [] as $substance): ?>
                        <?php
                        $substanceKey = (string) ($substance['key'] ?? '');
                        $isChecked = in_array($substanceKey, $selectedSubstanceKeys, true);
                        ?>
                        <label class="form-check senda-substance-checklist__item">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="substance_keys[]"
                                value="<?= e($substanceKey) ?>"
                                <?= $isChecked ? 'checked' : '' ?>
                            >
                            <span class="form-check-label"><?= e((string) ($substance['label'] ?? '')) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="age_of_onset">Edad de inicio</label>
                    <input class="form-control <?= $hasError('age_of_onset') ? 'is-invalid' : '' ?>" id="age_of_onset" name="age_of_onset" value="<?= e($v('age_of_onset')) ?>">
                    <?php if ($hasError('age_of_onset')): ?><div class="invalid-feedback"><?= e((string) error('age_of_onset')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="consumption_frequency">Frecuencia de consumo</label>
                    <select class="form-select <?= $hasError('consumption_frequency') ? 'is-invalid' : '' ?>" id="consumption_frequency" name="consumption_frequency">
                        <option value="">Seleccione</option>
                        <?= $optionList($frequencies ?? [], $v('consumption_frequency')) ?>
                    </select>
                    <?php if ($hasError('consumption_frequency')): ?><div class="invalid-feedback"><?= e((string) error('consumption_frequency')) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="mental_health_history">Antecedentes de salud mental</label>
                <textarea class="form-control <?= $hasError('mental_health_history') ? 'is-invalid' : '' ?>" id="mental_health_history" name="mental_health_history" rows="3"><?= e($v('mental_health_history')) ?></textarea>
                <?php if ($hasError('mental_health_history')): ?><div class="invalid-feedback"><?= e((string) error('mental_health_history')) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="physical_health_history">Antecedentes de salud física</label>
                <textarea class="form-control <?= $hasError('physical_health_history') ? 'is-invalid' : '' ?>" id="physical_health_history" name="physical_health_history" rows="3"><?= e($v('physical_health_history')) ?></textarea>
                <?php if ($hasError('physical_health_history')): ?><div class="invalid-feedback"><?= e((string) error('physical_health_history')) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="family_situation">Situación familiar</label>
                <textarea class="form-control <?= $hasError('family_situation') ? 'is-invalid' : '' ?>" id="family_situation" name="family_situation" rows="3"><?= e($v('family_situation')) ?></textarea>
                <?php if ($hasError('family_situation')): ?><div class="invalid-feedback"><?= e((string) error('family_situation')) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="legal_situation">Situación judicial</label>
                <textarea class="form-control <?= $hasError('legal_situation') ? 'is-invalid' : '' ?>" id="legal_situation" name="legal_situation" rows="3"><?= e($v('legal_situation')) ?></textarea>
                <?php if ($hasError('legal_situation')): ?><div class="invalid-feedback"><?= e((string) error('legal_situation')) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="support_network">Red de apoyo</label>
                <textarea class="form-control <?= $hasError('support_network') ? 'is-invalid' : '' ?>" id="support_network" name="support_network" rows="3"><?= e($v('support_network')) ?></textarea>
                <?php if ($hasError('support_network')): ?><div class="invalid-feedback"><?= e((string) error('support_network')) ?></div><?php endif; ?>
            </div>
        </section>

        <section class="page-card senda-step" data-step="5">
            <h3 class="page-card__title">5. Tratamientos Previos Sistema SENDA</h3>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="has_previous_treatments">¿Registra tratamientos previos en sistema SENDA?</label>
                    <select class="form-select <?= $hasError('has_previous_treatments') ? 'is-invalid' : '' ?>" id="has_previous_treatments" name="has_previous_treatments" data-senda-treatments-toggle>
                        <option value="">Seleccione</option>
                        <?= $optionList($yesNo ?? [], $v('has_previous_treatments')) ?>
                    </select>
                    <?php if ($hasError('has_previous_treatments')): ?><div class="invalid-feedback"><?= e((string) error('has_previous_treatments')) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="row" data-senda-treatments-detail <?= $v('has_previous_treatments') === 'si' ? '' : 'hidden' ?>>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="previous_treatments_count">Cantidad de tratamientos previos</label>
                    <input type="number" min="1" class="form-control <?= $hasError('previous_treatments_count') ? 'is-invalid' : '' ?>" id="previous_treatments_count" name="previous_treatments_count" value="<?= e($v('previous_treatments_count')) ?>">
                    <?php if ($hasError('previous_treatments_count')): ?><div class="invalid-feedback"><?= e((string) error('previous_treatments_count')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="previous_treatment_modality">Modalidad</label>
                    <select class="form-select <?= $hasError('previous_treatment_modality') ? 'is-invalid' : '' ?>" id="previous_treatment_modality" name="previous_treatment_modality">
                        <option value="">Seleccione</option>
                        <?= $optionList($treatmentModalities ?? [], $v('previous_treatment_modality')) ?>
                    </select>
                    <?php if ($hasError('previous_treatment_modality')): ?><div class="invalid-feedback"><?= e((string) error('previous_treatment_modality')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="previous_treatment_stay">Tiempo de permanencia</label>
                    <select class="form-select <?= $hasError('previous_treatment_stay') ? 'is-invalid' : '' ?>" id="previous_treatment_stay" name="previous_treatment_stay">
                        <option value="">Seleccione</option>
                        <?= $optionList($treatmentStayPeriods ?? [], $v('previous_treatment_stay')) ?>
                    </select>
                    <?php if ($hasError('previous_treatment_stay')): ?><div class="invalid-feedback"><?= e((string) error('previous_treatment_stay')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="previous_treatment_completed">Término de tratamiento</label>
                    <select class="form-select <?= $hasError('previous_treatment_completed') ? 'is-invalid' : '' ?>" id="previous_treatment_completed" name="previous_treatment_completed">
                        <option value="">Seleccione</option>
                        <?= $optionList($yesNo ?? [], $v('previous_treatment_completed')) ?>
                    </select>
                    <?php if ($hasError('previous_treatment_completed')): ?><div class="invalid-feedback"><?= e((string) error('previous_treatment_completed')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="previous_treatment_center">Nombre del centro</label>
                    <input class="form-control <?= $hasError('previous_treatment_center') ? 'is-invalid' : '' ?>" id="previous_treatment_center" name="previous_treatment_center" value="<?= e($v('previous_treatment_center')) ?>" maxlength="180">
                    <?php if ($hasError('previous_treatment_center')): ?><div class="invalid-feedback"><?= e((string) error('previous_treatment_center')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="previous_treatment_commune">Comuna</label>
                    <input class="form-control <?= $hasError('previous_treatment_commune') ? 'is-invalid' : '' ?>" id="previous_treatment_commune" name="previous_treatment_commune" value="<?= e($v('previous_treatment_commune')) ?>" maxlength="120">
                    <?php if ($hasError('previous_treatment_commune')): ?><div class="invalid-feedback"><?= e((string) error('previous_treatment_commune')) ?></div><?php endif; ?>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label" for="previous_treatments_detail">Observación</label>
                    <textarea class="form-control <?= $hasError('previous_treatments_detail') ? 'is-invalid' : '' ?>" id="previous_treatments_detail" name="previous_treatments_detail" rows="4" maxlength="2000"><?= e($v('previous_treatments_detail')) ?></textarea>
                    <?php if ($hasError('previous_treatments_detail')): ?><div class="invalid-feedback"><?= e((string) error('previous_treatments_detail')) ?></div><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="page-card senda-step" data-step="6" data-senda-risk-eval-step<?= $showRiskEvalStep ? '' : ' hidden' ?>>
            <h3 class="page-card__title">6. Evaluación de riesgo</h3>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="suicide_risk">Riesgo suicida</label>
                    <select class="form-select <?= $hasError('suicide_risk') ? 'is-invalid' : '' ?>" id="suicide_risk" name="suicide_risk">
                        <option value="">Seleccione</option>
                        <?= $optionList($riskLevels ?? [], $v('suicide_risk')) ?>
                    </select>
                    <?php if ($hasError('suicide_risk')): ?><div class="invalid-feedback"><?= e((string) error('suicide_risk')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="violence_risk">Riesgo de violencia</label>
                    <select class="form-select <?= $hasError('violence_risk') ? 'is-invalid' : '' ?>" id="violence_risk" name="violence_risk">
                        <option value="">Seleccione</option>
                        <?= $optionList($riskLevels ?? [], $v('violence_risk')) ?>
                    </select>
                    <?php if ($hasError('violence_risk')): ?><div class="invalid-feedback"><?= e((string) error('violence_risk')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="overall_risk">Nivel de riesgo global</label>
                    <select class="form-select <?= $hasError('overall_risk') ? 'is-invalid' : '' ?>" id="overall_risk" name="overall_risk">
                        <option value="">Seleccione</option>
                        <?= $optionList($riskLevels ?? [], $v('overall_risk')) ?>
                    </select>
                    <?php if ($hasError('overall_risk')): ?><div class="invalid-feedback"><?= e((string) error('overall_risk')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="street_situation">Situación de calle</label>
                    <select class="form-select" id="street_situation" name="street_situation">
                        <?= $optionList($yesNoUnknown ?? [], $v('street_situation')) ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="pregnancy">Embarazo</label>
                    <select class="form-select" id="pregnancy" name="pregnancy">
                        <?= $optionList($yesNoUnknown ?? [], $v('pregnancy')) ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="children_in_care">Niños o niñas a cargo</label>
                    <select class="form-select" id="children_in_care" name="children_in_care">
                        <?= $optionList($yesNoUnknown ?? [], $v('children_in_care')) ?>
                    </select>
                </div>
            </div>
            <div class="mb-0">
                <label class="form-label" for="risk_notes">Notas de riesgo</label>
                <textarea class="form-control <?= $hasError('risk_notes') ? 'is-invalid' : '' ?>" id="risk_notes" name="risk_notes" rows="4"><?= e($v('risk_notes')) ?></textarea>
                <?php if ($hasError('risk_notes')): ?><div class="invalid-feedback"><?= e((string) error('risk_notes')) ?></div><?php endif; ?>
            </div>
        </section>

        <section class="page-card senda-step" data-step="7">
            <h3 class="page-card__title">7. Evaluación de Nivel de Riesgo de Consumo</h3>
            <p class="form-label mb-2">Uso de instrumento de tamizaje</p>
            <div class="senda-choice-grid mb-3<?= $hasError('screening_used') ? ' is-invalid' : '' ?>" role="radiogroup" aria-label="Uso de instrumento de tamizaje">
                <?php foreach ($yesNo ?? [] as $option): ?>
                    <label class="senda-choice-card<?= $screeningUsed === $option['value'] ? ' is-current' : '' ?>">
                        <input
                            class="form-check-input senda-choice-card__radio"
                            type="radio"
                            name="screening_used"
                            value="<?= e($option['value']) ?>"
                            data-senda-screening
                            <?= $screeningUsed === $option['value'] ? 'checked' : '' ?>
                        >
                        <span class="senda-choice-card__label"><?= e($option['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if ($hasError('screening_used')): ?>
                <p class="text-danger small" data-senda-screening-error><?= e((string) error('screening_used')) ?></p>
            <?php else: ?>
                <p class="text-danger small" data-senda-screening-error hidden>Indique si se usó instrumento de tamizaje.</p>
            <?php endif; ?>

            <div data-senda-assist-panel <?= $screeningUsed === 'si' ? '' : 'hidden' ?>>
                <h4 class="h5 mb-3">Instrumento de Tamizaje ASSIST</h4>
                <?php if ($suggestAssist): ?>
                    <p class="text-secondary">Se sugiere completar ASSIST porque la persona tiene 18 años o más.</p>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="data-table senda-assist-table" data-senda-assist-table data-assist-rules="<?= e(json_encode($assistClassificationRules ?? [], JSON_UNESCAPED_UNICODE)) ?>">
                        <thead>
                            <tr>
                                <th>Sustancia</th>
                                <th>Puntaje</th>
                                <th>Clasificación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assistSubstances ?? [] as $substance): ?>
                                <?php
                                    $key = $substance['key'];
                                    $row = is_array($assist[$key] ?? null) ? $assist[$key] : ['score' => '', 'risk_level' => ''];
                                    $scoreField = 'assist.' . $key . '.score';
                                    $riskLabel = \App\Services\Senda\AssistedReferralCatalog::optionLabel(
                                        $assistClassifications ?? [],
                                        $row['risk_level'] ?? ''
                                    );
                                ?>
                                <tr data-assist-substance="<?= e($key) ?>">
                                    <td><?= e((string) $substance['label']) ?></td>
                                    <td>
                                        <input
                                            class="form-control <?= $hasError($scoreField) ? 'is-invalid' : '' ?>"
                                            name="assist[<?= e($key) ?>][score]"
                                            value="<?= e((string) ($row['score'] ?? '')) ?>"
                                            inputmode="numeric"
                                            min="0"
                                            max="39"
                                            data-assist-score
                                        >
                                        <?php if ($hasError($scoreField)): ?>
                                            <div class="invalid-feedback"><?= e((string) error($scoreField)) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="senda-assist-risk" data-assist-risk><?= e($riskLabel) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div data-senda-screening-end <?= $skipAfterScreening ? '' : 'hidden' ?>>
                <p class="text-secondary mb-3">Sin instrumento de tamizaje, la ficha termina aquí.</p>
                <?php if (!$locked && empty($record['is_completed'])): ?>
                    <button class="btn btn-outline-navy me-2" type="submit" name="save_draft" value="1">Guardar borrador</button>
                    <button class="btn btn-navy" type="submit" name="finalize_referral" value="1" data-senda-finalize>Guardar y finalizar</button>
                <?php endif; ?>
            </div>
        </section>

        <section class="page-card senda-step" data-step="8" data-senda-observations-step <?= $skipAfterScreening ? 'hidden' : '' ?>>
            <h3 class="page-card__title">8. Observaciones</h3>
            <div class="mb-4">
                <label class="form-label" for="observations">Observaciones</label>
                <textarea class="form-control senda-observations <?= $hasError('observations') ? 'is-invalid' : '' ?>" id="observations" name="observations" rows="12"><?= e($v('observations')) ?></textarea>
                <?php if ($hasError('observations')): ?><div class="invalid-feedback"><?= e((string) error('observations')) ?></div><?php endif; ?>
            </div>
            <?php if (!$locked && empty($record['is_completed'])): ?>
                <button class="btn btn-outline-navy me-2" type="submit" name="save_draft" value="1">Guardar borrador</button>
                <button class="btn btn-navy" type="submit" name="finalize_referral" value="1" data-senda-finalize>Guardar y finalizar</button>
            <?php endif; ?>
        </section>
    </fieldset>

    <div class="senda-step-nav">
        <button class="btn btn-outline-navy" type="button" data-step-prev>Anterior</button>
        <button class="btn btn-outline-navy" type="button" data-step-next>Siguiente</button>
        <?php if (!$locked): ?>
            <?php if (!empty($record['is_completed'])): ?>
                <button class="btn btn-navy" type="submit">Guardar cambios</button>
            <?php else: ?>
                <button class="btn btn-outline-navy" type="submit" name="save_draft" value="1" data-senda-save>Guardar borrador</button>
                <button class="btn btn-navy" type="submit" name="finalize_referral" value="1" data-senda-finalize>Guardar y finalizar</button>
            <?php endif; ?>
        <?php endif; ?>
        <a class="btn btn-outline-navy" href="<?= e(url('/senda/referrals')) ?>">Cancelar</a>
    </div>
</form>
