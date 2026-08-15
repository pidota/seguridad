<?php

$record = $record ?? [];
$selectedType = (string) old('visitor_type', (string) ($record['visitor_type'] ?? 'general_visit'));

?>
<section class="page-toolbar">
    <div>
        <h2 class="page-card__title mb-1">Registrar Visita / Solicitud</h2>
        <p class="text-secondary mb-0">Seleccione el tipo de registro y complete los datos correspondientes.</p>
    </div>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<div class="page-card mb-3">
    <p class="mb-1"><strong>Turno activo:</strong> <?= e(date('d/m/Y H:i', strtotime((string) ($openShift['started_at'] ?? 'now')))) ?></p>
    <p class="text-secondary mb-0">Operador: <?= e(trim((string) (user()['name'] ?? ''))) ?></p>
</div>

<form method="post" action="<?= e(url('/cctv/visits')) ?>" enctype="multipart/form-data" class="page-card" id="cctv-visit-form" novalidate>
    <?= csrf_field() ?>

    <fieldset class="mb-4">
        <legend class="form-label">Tipo de Registro</legend>
        <div class="row">
            <?php foreach ($visitorTypes ?? [] as $option): ?>
                <div class="col-md-6 mb-2">
                    <label class="check-card">
                        <input type="radio" name="visitor_type" value="<?= e((string) $option['value']) ?>" data-visit-type="<?= e((string) $option['value']) ?>" <?= $selectedType === (string) $option['value'] ? 'checked' : '' ?>>
                        <strong><?= e((string) $option['label']) ?></strong>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (has_error('visitor_type')): ?><div class="invalid-feedback d-block"><?= e((string) error('visitor_type')) ?></div><?php endif; ?>
    </fieldset>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label" for="visit_date">Fecha</label>
            <input type="date" class="form-control <?= has_error('visit_date') ? 'is-invalid' : '' ?>" id="visit_date" name="visit_date" value="<?= e((string) old('visit_date', (string) ($record['visit_date'] ?? date('Y-m-d')))) ?>" required>
            <?php if (has_error('visit_date')): ?><div class="invalid-feedback"><?= e((string) error('visit_date')) ?></div><?php endif; ?>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label" for="arrival_time">Hora de ingreso / solicitud</label>
            <input type="time" class="form-control <?= has_error('arrival_time') ? 'is-invalid' : '' ?>" id="arrival_time" name="arrival_time" value="<?= e((string) old('arrival_time', (string) ($record['arrival_time'] ?? date('H:i')))) ?>" required>
            <?php if (has_error('arrival_time')): ?><div class="invalid-feedback"><?= e((string) error('arrival_time')) ?></div><?php endif; ?>
        </div>
        <div class="col-md-4 mb-3" data-general-only>
            <label class="form-label" for="departure_time">Hora de salida</label>
            <input type="time" class="form-control" id="departure_time" name="departure_time" value="<?= e((string) old('departure_time')) ?>">
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label" for="requester_name">Nombre de la persona</label>
            <input type="text" class="form-control <?= has_error('requester_name') ? 'is-invalid' : '' ?>" id="requester_name" name="requester_name" value="<?= e((string) old('requester_name')) ?>" required>
            <?php if (has_error('requester_name')): ?><div class="invalid-feedback"><?= e((string) error('requester_name')) ?></div><?php endif; ?>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label" for="requester_rut">RUT</label>
            <input type="text" class="form-control <?= has_error('requester_rut') ? 'is-invalid' : '' ?>" id="requester_rut" name="requester_rut" value="<?= e((string) old('requester_rut')) ?>" placeholder="12.345.678-5">
            <?php if (has_error('requester_rut')): ?><div class="invalid-feedback"><?= e((string) error('requester_rut')) ?></div><?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label" for="requester_phone">Teléfono</label>
            <input type="text" class="form-control" id="requester_phone" name="requester_phone" value="<?= e((string) old('requester_phone')) ?>">
        </div>
        <div class="col-md-4 mb-3" data-recording-only>
            <label class="form-label" for="requester_email">Correo electrónico</label>
            <input type="email" class="form-control" id="requester_email" name="requester_email" value="<?= e((string) old('requester_email')) ?>">
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label" for="organization">Institución / Organización</label>
            <input type="text" class="form-control" id="organization" name="organization" value="<?= e((string) old('organization')) ?>">
        </div>
    </div>

    <div class="row" data-general-only>
        <div class="col-md-6 mb-3">
            <label class="form-label" for="visit_reason">Motivo de visita</label>
            <select class="form-select" id="visit_reason" name="visit_reason" data-visit-reason>
                <option value="">Seleccione</option>
                <?php foreach ($visitReasons ?? [] as $reasonOption): ?>
                    <option value="<?= e((string) $reasonOption['value']) ?>" <?= (string) old('visit_reason') === (string) $reasonOption['value'] ? 'selected' : '' ?>>
                        <?= e((string) $reasonOption['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 mb-3" data-visit-reason-other hidden>
            <label class="form-label" for="visit_reason_other">Especifique</label>
            <input type="text" class="form-control" id="visit_reason_other" name="visit_reason_other" value="<?= e((string) old('visit_reason_other')) ?>">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label" for="reason">Descripción / detalle</label>
        <textarea class="form-control <?= has_error('reason') ? 'is-invalid' : '' ?>" id="reason" name="reason" rows="3" required><?= e((string) old('reason')) ?></textarea>
        <?php if (has_error('reason')): ?><div class="invalid-feedback"><?= e((string) error('reason')) ?></div><?php endif; ?>
    </div>

    <div class="cctv-visit-recording-panel" data-recording-only hidden>
        <h3 class="page-card__title h5">Datos del hecho solicitado</h3>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label" for="incident_date">Fecha del hecho</label>
                <input type="date" class="form-control" id="incident_date" name="incident_date" value="<?= e((string) old('incident_date', date('Y-m-d'))) ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="time_from">Hora desde</label>
                <input type="time" class="form-control" id="time_from" name="time_from" value="<?= e((string) old('time_from', date('H:i'))) ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="time_to">Hora hasta</label>
                <input type="time" class="form-control" id="time_to" name="time_to" value="<?= e((string) old('time_to', date('H:i'))) ?>">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="sector_id">Sector</label>
                <select class="form-select" id="sector_id" name="sector_id">
                    <option value="">Seleccione sector</option>
                    <?php foreach ($sectors ?? [] as $sector): ?>
                        <option value="<?= e((string) $sector['id']) ?>" <?= (string) old('sector_id') === (string) $sector['id'] ? 'selected' : '' ?>>
                            <?= e((string) $sector['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="camera_id">Cámara (opcional)</label>
                <select class="form-select" id="camera_id" name="camera_id">
                    <option value="">Desconocida / no indicada</option>
                    <?php foreach ($cameras ?? [] as $camera): ?>
                        <option value="<?= e((string) $camera['value']) ?>" <?= (string) old('camera_id') === (string) $camera['value'] ? 'selected' : '' ?>>
                            <?= e((string) $camera['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="incident_description">Descripción del hecho</label>
            <textarea class="form-control" id="incident_description" name="incident_description" rows="3"><?= e((string) old('incident_description')) ?></textarea>
        </div>

        <fieldset class="mb-3">
            <legend class="form-label">¿Posee denuncia registrada?</legend>
            <div class="d-flex gap-3">
                <label class="form-check">
                    <input class="form-check-input" type="radio" name="has_complaint" value="1" data-complaint-toggle="yes" <?= (string) old('has_complaint', '0') === '1' ? 'checked' : '' ?>>
                    <span class="form-check-label">Sí</span>
                </label>
                <label class="form-check">
                    <input class="form-check-input" type="radio" name="has_complaint" value="0" data-complaint-toggle="no" <?= (string) old('has_complaint', '0') !== '1' ? 'checked' : '' ?>>
                    <span class="form-check-label">No</span>
                </label>
            </div>
        </fieldset>

        <div class="alert alert-warning" data-complaint-warning hidden>
            Solicitud registrada sin denuncia. La grabación <strong>no puede ser entregada</strong> mientras no se registre una denuncia previa.
        </div>

        <div data-complaint-fields hidden>
            <h4 class="h6">Antecedentes de la Denuncia</h4>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="complaint_institution">Institución</label>
                    <select class="form-select" id="complaint_institution" name="complaint_institution">
                        <option value="">Seleccione</option>
                        <?php foreach ($complaintInstitutions ?? [] as $institution): ?>
                            <option value="<?= e((string) $institution['value']) ?>" <?= (string) old('complaint_institution') === (string) $institution['value'] ? 'selected' : '' ?>>
                                <?= e((string) $institution['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="complaint_number">N.º de denuncia / parte / identificador</label>
                    <input type="text" class="form-control" id="complaint_number" name="complaint_number" value="<?= e((string) old('complaint_number')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="complaint_date">Fecha de la denuncia</label>
                    <input type="date" class="form-control" id="complaint_date" name="complaint_date" value="<?= e((string) old('complaint_date')) ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="complaint_observations">Observaciones</label>
                <textarea class="form-control" id="complaint_observations" name="complaint_observations" rows="2"><?= e((string) old('complaint_observations')) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label" for="complaint_document">Documento de respaldo de denuncia</label>
                <input type="file" class="form-control" id="complaint_document" name="complaint_document" accept=".pdf,.jpg,.jpeg,.png">
                <div class="form-text">PDF, JPG, JPEG o PNG. Máximo 5 MB.</div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-navy">Guardar registro</button>
    <a href="<?= e(url('/cctv/visits')) ?>" class="btn btn-outline-navy">Cancelar</a>
</form>
