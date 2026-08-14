<?php

$record = $record ?? [];

$id = (int) ($record['id'] ?? 0);

$shiftId = (int) ($record['shift_id'] ?? 0);

$contacts = is_array($record['contacts'] ?? null) ? $record['contacts'] : [];

$showCoordinations = !empty($record['show_coordinations']) || $contacts !== [];

$showPolice = !empty($record['show_police']);

$observations = trim((string) ($record['observations'] ?? ''));
$incidentFieldLabel = (($record['log_type_slug'] ?? '') === 'novedad_tecnica')
    ? 'Tipo de problema'
    : 'Tipo de incidente';

?>

<section class="page-toolbar">

    <div>

        <p class="welcome-kicker mb-1">Central de Cámaras</p>

        <h2 class="page-card__title mb-1">Detalle de registro CCTV</h2>

        <p class="mb-0">

            <span class="camera-device-badge camera-device-badge--<?= e((string) ($record['log_type_tone'] ?? 'other')) ?>">

                <?= e((string) ($record['type_label'] ?? '—')) ?>

            </span>

        </p>

    </div>

    <div class="d-flex flex-wrap gap-2">

        <?php if (!empty($canEditLogEntry)): ?>

            <a class="btn btn-outline-navy" href="<?= e(url('/cctv/log/' . $id . '/edit')) ?>">Editar</a>

        <?php endif; ?>

        <?= \Core\View::make('camera/log/_cancel-form', [
            'id' => $id,
            'canCancelLogEntry' => !empty($canCancelLogEntry),
            'buttonClass' => 'btn btn-outline-danger',
            'formClass' => 'd-inline',
        ], null) ?>

        <a class="btn btn-outline-navy" href="<?= e(url('/cctv/log')) ?>">Volver a la bitácora</a>

    </div>

</section>



<?= cameras_nav($camerasNav ?? []) ?>



<section class="page-card cctv-log-detail mb-3">

    <h3 class="cctv-log-detail__title">Datos generales</h3>

    <dl class="camera-detail-grid">

        <div>

            <dt>Fecha</dt>

            <dd><?= e((string) ($record['event_date_formatted'] ?? '—')) ?></dd>

        </div>

        <div>

            <dt>Hora</dt>

            <dd><?= e((string) ($record['event_time_formatted'] ?? '—')) ?></dd>

        </div>

        <div>

            <dt>Operador</dt>

            <dd><?= e((string) ($record['operator_label'] ?? '—')) ?></dd>

        </div>

        <div>

            <dt>Turno</dt>

            <dd>

                <?php if ($shiftId > 0): ?>

                    <a href="<?= e(url('/cctv/shifts/' . $shiftId)) ?>"><?= e((string) ($record['shift_label'] ?? '—')) ?></a>

                <?php else: ?>

                    <?= e((string) ($record['shift_label'] ?? '—')) ?>

                <?php endif; ?>

            </dd>

        </div>

    </dl>

</section>



<section class="page-card cctv-log-detail mb-3">

    <h3 class="cctv-log-detail__title">Evento</h3>

    <dl class="camera-detail-grid">

        <div>

            <dt>Tipo</dt>

            <dd><?= e((string) ($record['type_label'] ?? '—')) ?></dd>

        </div>

        <div>

            <dt><?= e($incidentFieldLabel) ?></dt>

            <dd><?= e((string) ($record['incident_label'] ?? '—')) ?></dd>

        </div>

        <div>

            <dt>Sector</dt>

            <dd><?= e((string) ($record['sector_label'] ?? '—')) ?></dd>

        </div>

        <div>

            <dt>Cámara</dt>

            <dd><?= e((string) ($record['camera_label'] ?? '—')) ?></dd>

        </div>

        <div>

            <dt>Estado</dt>

            <dd>

                <span class="camera-device-badge camera-device-badge--<?= e((string) ($record['status_tone'] ?? 'other')) ?>">

                    <?= e((string) ($record['status_label'] ?? '—')) ?>

                </span>

            </dd>

        </div>

    </dl>



    <div class="camera-detail-block">

        <h4 class="camera-detail-block__title">Observaciones</h4>

        <p class="camera-detail-block__text"><?= $observations !== '' ? nl2br(e($observations)) : '—' ?></p>

    </div>

</section>



<?php if ($showCoordinations): ?>

    <section class="page-card cctv-log-detail mb-3">

        <div class="cctv-log-detail__section-head">

            <h3 class="cctv-log-detail__title mb-0">Coordinaciones</h3>

            <?php if (!empty($record['coordination_notified_label'])): ?>

                <span class="cctv-log-detail__meta">Aviso o coordinación: <strong><?= e((string) $record['coordination_notified_label']) ?></strong></span>

            <?php endif; ?>

        </div>



        <?php if ($contacts === []): ?>

            <p class="cctv-log-detail__empty mb-0">No hay contactos registrados para este evento.</p>

        <?php else: ?>

            <div class="table-responsive">

                <table class="data-table cctv-log-detail__contacts">

                    <thead>

                        <tr>

                            <th>Institución</th>

                            <th>Persona de contacto</th>

                            <th>Hora</th>

                            <th>Observaciones</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($contacts as $contact): ?>

                            <tr>

                                <td><?= e((string) ($contact['institution_label'] ?? '—')) ?></td>

                                <td><?= e((string) ($contact['contact_person_label'] ?? '—')) ?></td>

                                <td><?= e((string) ($contact['contacted_at_formatted'] ?? '—')) ?></td>

                                <td><?= e((string) ($contact['notes_label'] ?? '—')) ?></td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

<?php endif; ?>



<?php if ($showPolice): ?>

    <section class="page-card cctv-log-detail mb-3">

        <h3 class="cctv-log-detail__title">Carabineros</h3>

        <dl class="camera-detail-grid">

            <div>

                <dt>Llegó</dt>

                <dd><?= e((string) ($record['police_arrived_label'] ?? '—')) ?></dd>

            </div>

            <?php if (\App\Services\Cctv\PoliceArrivalCatalog::isYes($record['police_arrived'] ?? null)): ?>

                <div>

                    <dt>Hora de llegada</dt>

                    <dd><?= e((string) ($record['police_arrival_time_formatted'] ?? '—')) ?></dd>

                </div>

            <?php endif; ?>

        </dl>

    </section>

<?php endif; ?>



<section class="page-card cctv-log-detail mb-3">

    <h3 class="cctv-log-detail__title">Sistema</h3>

    <dl class="camera-detail-grid">

        <div>

            <dt>Creado por</dt>

            <dd><?= e((string) ($record['created_by_name'] ?? '—')) ?></dd>

        </div>

        <div>

            <dt>Fecha de creación</dt>

            <dd><?= e((string) ($record['created_at_formatted'] ?? '—')) ?></dd>

        </div>

        <div>

            <dt>Última modificación</dt>

            <dd><?= e((string) ($record['updated_at_formatted'] ?? '—')) ?></dd>

        </div>

    </dl>

</section>

