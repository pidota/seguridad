<?php
$record = $record ?? [];
$openShift = $openShift ?? [];
$isEdit = !empty($isEdit);
$formAction = (string) ($formAction ?? url('/cctv/log/incident'));
$cancelUrl = (string) ($cancelUrl ?? url('/cctv#bitacora-turno'));
$selectedIncidentType = (string) old('incident_type_id', (string) ($record['incident_type_id'] ?? ''));
$showOtherIncident = false;
foreach ($incidentTypes ?? [] as $typeOption) {
    if ((string) ($typeOption['id'] ?? '') === $selectedIncidentType && !empty($typeOption['allows_other'])) {
        $showOtherIncident = true;
        break;
    }
}
$policeArrived = (string) old('police_arrived', (string) ($record['police_arrived'] ?? ''));
$coordinationNotified = (string) old('coordination_notified', (string) ($record['coordination_notified'] ?? ''));
$contactRows = old('contacts', []);
if (!is_array($contactRows) || $contactRows === []) {
    $contactRows = $record['contacts_form'] ?? [[
        'contact_type' => '',
        'contact_name' => '',
        'contacted_at' => '',
        'notes' => '',
    ]];
}
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Central de Cámaras</p>
        <h2 class="page-card__title mb-0"><?= $isEdit ? 'Editar incidente' : 'Registrar incidente' ?></h2>
    </div>
    <a class="btn btn-outline-navy" href="<?= e($cancelUrl) ?>" data-cctv-leave-without-save>Volver<?= $isEdit ? ' al detalle' : ' al dashboard' ?></a>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<div class="page-card page-card--lg">
    <div class="cctv-log-create-meta mb-4">
        <div>
            <p class="welcome-kicker mb-1">Turno operativo</p>
            <p class="mb-0">
                Turno activo iniciado el
                <strong><?= e((string) ($openShift['started_at_formatted'] ?? '—')) ?></strong>
            </p>
        </div>
        <div>
            <p class="welcome-kicker mb-1">Operador</p>
            <p class="mb-0"><strong><?= e((string) ($operatorName ?? '—')) ?></strong></p>
        </div>
    </div>

    <form method="post" action="<?= e($formAction) ?>" novalidate data-cctv-log-incident-form data-cctv-unsaved-guard>
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <?= method_field('PUT') ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label" for="event_date">Fecha</label>
                <input
                    type="date"
                    class="form-control <?= has_error('event_date') ? 'is-invalid' : '' ?>"
                    id="event_date"
                    name="event_date"
                    value="<?= e((string) old('event_date', (string) ($record['event_date'] ?? ''))) ?>"
                    required
                >
                <?php if (has_error('event_date')): ?>
                    <div class="invalid-feedback"><?= e((string) error('event_date')) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="event_time">Hora del suceso</label>
                <input
                    type="time"
                    class="form-control <?= has_error('event_time') ? 'is-invalid' : '' ?>"
                    id="event_time"
                    name="event_time"
                    data-cctv-event-time
                    value="<?= e((string) old('event_time', (string) ($record['event_time'] ?? ''))) ?>"
                    required
                >
                <?php if (has_error('event_time')): ?>
                    <div class="invalid-feedback"><?= e((string) error('event_time')) ?></div>
                <?php endif; ?>
                <div class="form-text">Puede ajustar la hora si el hecho ocurrió minutos antes.</div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="status">Estado</label>
                <select class="form-select <?= has_error('status') ? 'is-invalid' : '' ?>" id="status" name="status" required>
                    <?php foreach ($statuses ?? [] as $status): ?>
                        <option
                            value="<?= e((string) ($status['value'] ?? '')) ?>"
                            <?= (string) old('status', (string) ($record['status'] ?? '')) === (string) ($status['value'] ?? '') ? 'selected' : '' ?>
                        >
                            <?= e((string) ($status['label'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('status')): ?>
                    <div class="invalid-feedback"><?= e((string) error('status')) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="incident_type_id">Tipo de incidente</label>
                <select
                    class="form-select <?= has_error('incident_type_id') ? 'is-invalid' : '' ?>"
                    id="incident_type_id"
                    name="incident_type_id"
                    data-incident-type-toggle
                    required
                >
                    <option value="">Seleccione</option>
                    <?php foreach ($incidentTypes ?? [] as $option): ?>
                        <option
                            value="<?= (int) ($option['id'] ?? 0) ?>"
                            data-allows-other="<?= !empty($option['allows_other']) ? '1' : '0' ?>"
                            <?= $selectedIncidentType === (string) ($option['id'] ?? '') ? 'selected' : '' ?>
                        >
                            <?= e((string) ($option['label'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('incident_type_id')): ?>
                    <div class="invalid-feedback"><?= e((string) error('incident_type_id')) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-6 mb-3" data-incident-other-panel <?= $showOtherIncident ? '' : 'hidden' ?>>
                <label class="form-label" for="incident_type_other">Especifique el incidente</label>
                <input
                    class="form-control <?= has_error('incident_type_other') ? 'is-invalid' : '' ?>"
                    id="incident_type_other"
                    name="incident_type_other"
                    value="<?= e((string) old('incident_type_other', (string) ($record['incident_type_other'] ?? ''))) ?>"
                    maxlength="180"
                >
                <?php if (has_error('incident_type_other')): ?>
                    <div class="invalid-feedback"><?= e((string) error('incident_type_other')) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="sector_id">Sector</label>
                <select class="form-select <?= has_error('sector_id') ? 'is-invalid' : '' ?>" id="sector_id" name="sector_id">
                    <option value="">Seleccione sector</option>
                    <?php foreach ($sectors ?? [] as $option): ?>
                        <option
                            value="<?= (int) ($option['id'] ?? 0) ?>"
                            <?= (string) old('sector_id', (string) ($record['sector_id'] ?? '')) === (string) ($option['id'] ?? '') ? 'selected' : '' ?>
                        >
                            <?= e((string) ($option['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('sector_id')): ?>
                    <div class="invalid-feedback"><?= e((string) error('sector_id')) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="camera_id">Cámara</label>
                <select class="form-select <?= has_error('camera_id') ? 'is-invalid' : '' ?>" id="camera_id" name="camera_id">
                    <option value="">Seleccione cámara</option>
                    <?php foreach ($cameras ?? [] as $option): ?>
                        <option
                            value="<?= (int) ($option['id'] ?? 0) ?>"
                            <?= (string) old('camera_id', (string) ($record['camera_id'] ?? '')) === (string) ($option['id'] ?? '') ? 'selected' : '' ?>
                        >
                            <?= e((string) ($option['label'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('camera_id')): ?>
                    <div class="invalid-feedback"><?= e((string) error('camera_id')) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label" for="observations">Observaciones</label>
            <textarea
                class="form-control <?= has_error('observations') ? 'is-invalid' : '' ?>"
                id="observations"
                name="observations"
                rows="5"
                maxlength="5000"
                required
            ><?= e((string) old('observations', (string) ($record['observations'] ?? ''))) ?></textarea>
            <?php if (has_error('observations')): ?>
                <div class="invalid-feedback"><?= e((string) error('observations')) ?></div>
            <?php endif; ?>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label" for="coordination_notified">¿Se realizó aviso o coordinación?</label>
                <select
                    class="form-select <?= has_error('coordination_notified') ? 'is-invalid' : '' ?>"
                    id="coordination_notified"
                    name="coordination_notified"
                    data-coordination-toggle
                >
                    <option value="">Sin registrar</option>
                    <option value="1" <?= $coordinationNotified === '1' ? 'selected' : '' ?>>Sí</option>
                    <option value="0" <?= $coordinationNotified === '0' ? 'selected' : '' ?>>No</option>
                </select>
                <?php if (has_error('coordination_notified')): ?>
                    <div class="invalid-feedback"><?= e((string) error('coordination_notified')) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="cctv-log-contacts mb-4" data-coordination-contacts-panel <?= $coordinationNotified === '1' ? '' : 'hidden' ?>>
            <div class="cctv-log-contacts__header">
                <div>
                    <h3 class="page-card__title h5 mb-1">Avisos y coordinaciones</h3>
                    <p class="cctv-log-contacts__hint mb-0">Registre cada contacto realizado con su hora.</p>
                </div>
                <button class="btn btn-outline-navy btn-sm" type="button" data-add-contact>
                    Agregar contacto
                </button>
            </div>

            <?php if (has_error('contacts')): ?>
                <div class="text-danger small fw-semibold mb-2"><?= e((string) error('contacts')) ?></div>
            <?php endif; ?>

            <div class="cctv-log-contacts__list" data-contacts-list>
                <?php foreach ($contactRows as $index => $contactRow): ?>
                    <?php
                    $contactType = (string) ($contactRow['contact_type'] ?? '');
                    $contactName = (string) ($contactRow['contact_name'] ?? '');
                    $contactTime = (string) ($contactRow['contacted_at'] ?? '');
                    $contactNotes = (string) ($contactRow['notes'] ?? '');
                    $typeErrorKey = 'contacts.' . $index . '.contact_type';
                    $timeErrorKey = 'contacts.' . $index . '.contacted_at';
                    $nameErrorKey = 'contacts.' . $index . '.contact_name';
                    $notesErrorKey = 'contacts.' . $index . '.notes';
                    ?>
                    <div class="cctv-log-contact-row" data-contact-row>
                        <div class="row align-items-end">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Tipo de contacto</label>
                                <select
                                    class="form-select <?= has_error($typeErrorKey) ? 'is-invalid' : '' ?>"
                                    name="contacts[<?= (int) $index ?>][contact_type]"
                                    data-contact-type
                                >
                                    <option value="">Seleccione</option>
                                    <?php foreach ($contactTypes ?? [] as $option): ?>
                                        <option
                                            value="<?= e((string) ($option['value'] ?? '')) ?>"
                                            <?= $contactType === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
                                        >
                                            <?= e((string) ($option['label'] ?? '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (has_error($typeErrorKey)): ?>
                                    <div class="invalid-feedback"><?= e((string) error($typeErrorKey)) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3 mb-3" data-contact-name-panel <?= $contactType === 'otro' ? '' : 'hidden' ?>>
                                <label class="form-label">Nombre del contacto</label>
                                <input
                                    type="text"
                                    class="form-control <?= has_error($nameErrorKey) ? 'is-invalid' : '' ?>"
                                    name="contacts[<?= (int) $index ?>][contact_name]"
                                    value="<?= e($contactName) ?>"
                                    maxlength="150"
                                    data-contact-name
                                >
                                <?php if (has_error($nameErrorKey)): ?>
                                    <div class="invalid-feedback"><?= e((string) error($nameErrorKey)) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Hora del aviso</label>
                                <input
                                    type="time"
                                    class="form-control <?= has_error($timeErrorKey) ? 'is-invalid' : '' ?>"
                                    name="contacts[<?= (int) $index ?>][contacted_at]"
                                    value="<?= e($contactTime) ?>"
                                    data-contact-time
                                >
                                <?php if (has_error($timeErrorKey)): ?>
                                    <div class="invalid-feedback"><?= e((string) error($timeErrorKey)) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-2 mb-3">
                                <button class="btn btn-outline-danger w-100" type="button" data-remove-contact>
                                    Quitar
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notas (opcional)</label>
                            <input
                                type="text"
                                class="form-control <?= has_error($notesErrorKey) ? 'is-invalid' : '' ?>"
                                name="contacts[<?= (int) $index ?>][notes]"
                                value="<?= e($contactNotes) ?>"
                                maxlength="2000"
                            >
                            <?php if (has_error($notesErrorKey)): ?>
                                <div class="invalid-feedback"><?= e((string) error($notesErrorKey)) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <template data-contact-row-template>
                <div class="cctv-log-contact-row" data-contact-row>
                    <div class="row align-items-end">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tipo de contacto</label>
                            <select class="form-select" name="contacts[__INDEX__][contact_type]" data-contact-type>
                                <option value="">Seleccione</option>
                                <?php foreach ($contactTypes ?? [] as $option): ?>
                                    <option value="<?= e((string) ($option['value'] ?? '')) ?>">
                                        <?= e((string) ($option['label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3" data-contact-name-panel hidden>
                            <label class="form-label">Nombre del contacto</label>
                            <input
                                type="text"
                                class="form-control"
                                name="contacts[__INDEX__][contact_name]"
                                maxlength="150"
                                data-contact-name
                            >
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Hora del aviso</label>
                            <input
                                type="time"
                                class="form-control"
                                name="contacts[__INDEX__][contacted_at]"
                                data-contact-time
                            >
                        </div>
                        <div class="col-md-2 mb-3">
                            <button class="btn btn-outline-danger w-100" type="button" data-remove-contact>
                                Quitar
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notas (opcional)</label>
                        <input
                            type="text"
                            class="form-control"
                            name="contacts[__INDEX__][notes]"
                            maxlength="2000"
                        >
                    </div>
                </div>
            </template>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label" for="police_arrived">¿Llegó Carabineros?</label>
                <select
                    class="form-select <?= has_error('police_arrived') ? 'is-invalid' : '' ?>"
                    id="police_arrived"
                    name="police_arrived"
                    data-police-arrival-toggle
                    required
                >
                    <option value="">Seleccione</option>
                    <?php foreach ($policeArrivalOptions ?? [] as $option): ?>
                        <option
                            value="<?= e((string) ($option['value'] ?? '')) ?>"
                            <?= $policeArrived === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
                        >
                            <?= e((string) ($option['label'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('police_arrived')): ?>
                    <div class="invalid-feedback"><?= e((string) error('police_arrived')) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-4 mb-3" data-police-arrival-panel <?= $policeArrived === '1' ? '' : 'hidden' ?>>
                <label class="form-label" for="police_arrival_time">Hora de llegada</label>
                <input
                    type="time"
                    class="form-control <?= has_error('police_arrival_time') ? 'is-invalid' : '' ?>"
                    id="police_arrival_time"
                    name="police_arrival_time"
                    value="<?= e((string) old('police_arrival_time', (string) ($record['police_arrival_time'] ?? ''))) ?>"
                    data-police-arrival-time
                    <?= $policeArrived === '1' ? 'required' : '' ?>
                >
                <?php if (has_error('police_arrival_time')): ?>
                    <div class="invalid-feedback"><?= e((string) error('police_arrival_time')) ?></div>
                <?php endif; ?>
                <div class="invalid-feedback" data-police-arrival-feedback hidden></div>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-navy" type="submit"><?= $isEdit ? 'Guardar cambios' : 'Registrar incidente' ?></button>
            <a class="btn btn-outline-navy" href="<?= e($cancelUrl) ?>" data-cctv-leave-without-save>Cancelar</a>
        </div>
    </form>
</div>
