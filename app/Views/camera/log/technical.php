<?php

$record = $record ?? [];

$openShift = $openShift ?? [];

$isEdit = !empty($isEdit);

$formAction = (string) ($formAction ?? url('/cctv/log/technical'));

$cancelUrl = (string) ($cancelUrl ?? url('/cctv#bitacora-turno'));

$targetType = (string) old('target_type', (string) ($record['target_type'] ?? 'camera'));

$selectedIssueType = (string) old('technical_issue_type_id', (string) ($record['technical_issue_type_id'] ?? ''));

$showOtherIssue = false;

foreach ($technicalIssueTypes ?? [] as $typeOption) {

    if ((string) ($typeOption['id'] ?? '') === $selectedIssueType && !empty($typeOption['allows_other'])) {

        $showOtherIssue = true;

        break;

    }

}

?>

<section class="page-toolbar">

    <div>

        <p class="welcome-kicker mb-1">Central de Cámaras</p>

        <h2 class="page-card__title mb-0"><?= $isEdit ? 'Editar novedad técnica' : 'Registrar novedad técnica' ?></h2>

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



    <p class="cctv-reception-intro mb-4">

        Registre fallas de equipos o cámaras. Este formulario no crea incidentes de seguridad.

    </p>



    <form method="post" action="<?= e($formAction) ?>" novalidate data-cctv-log-form data-cctv-log-technical-form>

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

                <label class="form-label" for="event_time">Hora</label>

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



        <div class="mb-3">

            <label class="form-label d-block">Elemento afectado</label>

            <div class="cctv-reception-item__statuses">

                <label class="cctv-reception-status">

                    <input

                        type="radio"

                        name="target_type"

                        value="camera"

                        data-target-type-toggle

                        <?= $targetType === 'camera' ? 'checked' : '' ?>

                    >

                    <span class="cctv-reception-status__label">Cámara</span>

                </label>

                <label class="cctv-reception-status">

                    <input

                        type="radio"

                        name="target_type"

                        value="equipment"

                        data-target-type-toggle

                        <?= $targetType === 'equipment' ? 'checked' : '' ?>

                    >

                    <span class="cctv-reception-status__label">Equipo del puesto</span>

                </label>

            </div>

            <?php if (has_error('target_type')): ?>

                <div class="text-danger small fw-semibold mt-1"><?= e((string) error('target_type')) ?></div>

            <?php endif; ?>

        </div>



        <div class="row">

            <div class="col-md-6 mb-3" data-target-camera-panel <?= $targetType === 'camera' ? '' : 'hidden' ?>>

                <label class="form-label" for="camera_id">Cámara</label>

                <select class="form-select <?= has_error('camera_id') ? 'is-invalid' : '' ?>" id="camera_id" name="camera_id" data-camera-select>

                    <option value="">Seleccione cámara</option>

                    <?php foreach ($cameras ?? [] as $option): ?>

                        <option

                            value="<?= (int) ($option['id'] ?? 0) ?>"

                            <?= (string) old('camera_id', (string) ($record['camera_id'] ?? '')) === (string) ($option['id'] ?? '') ? 'selected' : '' ?>

                        >

                            <?= e((string) ($option['label'] ?? '')) ?>

                            <?php if (!empty($option['status_label'])): ?>

                                (<?= e((string) $option['status_label']) ?>)

                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <?php if (has_error('camera_id')): ?>

                    <div class="invalid-feedback"><?= e((string) error('camera_id')) ?></div>

                <?php endif; ?>

            </div>

            <div class="col-md-6 mb-3" data-target-equipment-panel <?= $targetType === 'equipment' ? '' : 'hidden' ?>>

                <label class="form-label" for="equipment_id">Equipo</label>

                <select class="form-select <?= has_error('equipment_id') ? 'is-invalid' : '' ?>" id="equipment_id" name="equipment_id">

                    <option value="">Seleccione equipo</option>

                    <?php foreach ($equipment ?? [] as $option): ?>

                        <option

                            value="<?= (int) ($option['id'] ?? 0) ?>"

                            <?= (string) old('equipment_id', (string) ($record['equipment_id'] ?? '')) === (string) ($option['id'] ?? '') ? 'selected' : '' ?>

                        >

                            <?= e((string) ($option['label'] ?? '')) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <?php if (has_error('equipment_id')): ?>

                    <div class="invalid-feedback"><?= e((string) error('equipment_id')) ?></div>

                <?php endif; ?>

            </div>

        </div>



        <div class="row">

            <div class="col-md-6 mb-3">

                <label class="form-label" for="technical_issue_type_id">Tipo de problema</label>

                <select

                    class="form-select <?= has_error('technical_issue_type_id') ? 'is-invalid' : '' ?>"

                    id="technical_issue_type_id"

                    name="technical_issue_type_id"

                    data-technical-issue-toggle

                    required

                >

                    <option value="">Seleccione</option>

                    <?php foreach ($technicalIssueTypes ?? [] as $option): ?>

                        <option

                            value="<?= (int) ($option['id'] ?? 0) ?>"

                            data-allows-other="<?= !empty($option['allows_other']) ? '1' : '0' ?>"

                            <?= $selectedIssueType === (string) ($option['id'] ?? '') ? 'selected' : '' ?>

                        >

                            <?= e((string) ($option['label'] ?? '')) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <?php if (has_error('technical_issue_type_id')): ?>

                    <div class="invalid-feedback"><?= e((string) error('technical_issue_type_id')) ?></div>

                <?php endif; ?>

            </div>

            <div class="col-md-6 mb-3" data-technical-other-panel <?= $showOtherIssue ? '' : 'hidden' ?>>

                <label class="form-label" for="technical_issue_other">Especifique el problema</label>

                <input

                    class="form-control <?= has_error('technical_issue_other') ? 'is-invalid' : '' ?>"

                    id="technical_issue_other"

                    name="technical_issue_other"

                    value="<?= e((string) old('technical_issue_other', (string) ($record['technical_issue_other'] ?? ''))) ?>"

                    maxlength="180"

                >

                <?php if (has_error('technical_issue_other')): ?>

                    <div class="invalid-feedback"><?= e((string) error('technical_issue_other')) ?></div>

                <?php endif; ?>

            </div>

        </div>



        <div class="mb-4" data-camera-status-panel <?= $targetType === 'camera' ? '' : 'hidden' ?>>

            <label class="form-label" for="camera_status">Actualizar estado de la cámara (opcional)</label>

            <select class="form-select <?= has_error('camera_status') ? 'is-invalid' : '' ?>" id="camera_status" name="camera_status" data-camera-status-select>

                <option value="">Sin cambio</option>

                <?php foreach ($cameraStatuses ?? [] as $option): ?>

                    <option

                        value="<?= e((string) ($option['value'] ?? '')) ?>"

                        <?= (string) old('camera_status', (string) ($record['camera_status'] ?? '')) === (string) ($option['value'] ?? '') ? 'selected' : '' ?>

                    >

                        <?= e((string) ($option['label'] ?? '')) ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <?php if (has_error('camera_status')): ?>

                <div class="invalid-feedback"><?= e((string) error('camera_status')) ?></div>

            <?php endif; ?>

            <div class="form-text">Solo aplica cuando selecciona una cámara afectada.</div>

        </div>



        <div class="mb-4">

            <label class="form-label" for="observations">Descripción</label>

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



        <div class="form-actions">

            <button class="btn btn-navy" type="submit"><?= $isEdit ? 'Guardar cambios' : 'Registrar novedad técnica' ?></button>

            <a class="btn btn-outline-navy" href="<?= e($cancelUrl) ?>" data-cctv-leave-without-save>Cancelar</a>

        </div>

    </form>

</div>

