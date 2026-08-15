<?php

$record = $record ?? [];
$status = (string) ($record['status'] ?? '');
$hasComplaint = !empty($record['has_complaint']);
$complaintVerified = !empty($record['complaint_verified']);

?>
<section class="page-toolbar">
    <div>
        <h2 class="page-card__title mb-1">Detalle de Solicitud de Grabación</h2>
        <p class="text-secondary mb-0"><code><?= e((string) ($record['request_number'] ?? '—')) ?></code></p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/cctv/recording-requests/' . ($record['id'] ?? '') . '/print')) ?>" target="_blank">Imprimir ficha</a>
        <?php if ($status === 'delivered'): ?>
            <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/cctv/recording-requests/' . ($record['id'] ?? '') . '/receipt')) ?>" target="_blank">Constancia de entrega</a>
        <?php endif; ?>
        <span class="senda-badge senda-badge--<?= e((string) ($record['status_tone'] ?? 'attention')) ?>">
            <?= e((string) ($record['status_label'] ?? '—')) ?>
        </span>
    </div>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<?php if (!$hasComplaint): ?>
    <div class="alert alert-warning">
        Solicitud registrada. La grabación <strong>NO puede ser entregada</strong> mientras no se registre una denuncia previa.
    </div>
<?php elseif (!$complaintVerified): ?>
    <div class="alert alert-warning">
        Denuncia informada, pero <strong>aún no verificada</strong>. Debe verificarse antes de revisar o entregar.
    </div>
<?php endif; ?>

<?php if (!empty($record['show_preserve_warning'])): ?>
    <div class="alert alert-warning">
        Grabación localizada, pero aún <strong>no registrada como preservada</strong>.
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <article class="page-card mb-3">
            <h3 class="h5">Solicitud</h3>
            <dl class="cctv-detail-grid">
                <div><dt>Número</dt><dd><code><?= e((string) ($record['request_number'] ?? '—')) ?></code></dd></div>
                <div><dt>Fecha</dt><dd><?= e(date('d/m/Y', strtotime((string) ($record['visit_date'] ?? '')))) ?></dd></div>
                <div><dt>Hora</dt><dd><?= e(substr((string) ($record['arrival_time'] ?? ''), 0, 5)) ?></dd></div>
                <div><dt>Recibió</dt><dd><?= e((string) ($record['received_by_name'] ?? $record['operator_name'] ?? '—')) ?></dd></div>
                <div><dt>Responsable</dt><dd><?= e((string) ($record['assigned_to_name'] ?? '—')) ?></dd></div>
            </dl>
        </article>

        <article class="page-card mb-3">
            <h3 class="h5">Solicitante</h3>
            <dl class="cctv-detail-grid">
                <div><dt>Nombre</dt><dd><?= e((string) ($record['requester_name'] ?? '—')) ?></dd></div>
                <div><dt>RUT</dt><dd><?= e((string) ($record['requester_rut'] ?? '—')) ?></dd></div>
                <div><dt>Teléfono</dt><dd><?= e((string) ($record['requester_phone'] ?? '—')) ?></dd></div>
                <div><dt>Correo</dt><dd><?= e((string) ($record['requester_email'] ?? '—')) ?></dd></div>
                <div><dt>Institución</dt><dd><?= e((string) ($record['organization'] ?? '—')) ?></dd></div>
            </dl>
            <div class="mt-2">
                <h4 class="h6">Motivo de la solicitud</h4>
                <p><?= nl2br(e((string) ($record['reason'] ?? ''))) ?></p>
            </div>
        </article>

        <article class="page-card mb-3">
            <h3 class="h5">Hecho</h3>
            <dl class="cctv-detail-grid">
                <div><dt>Fecha del hecho</dt><dd><?= e(date('d/m/Y', strtotime((string) ($record['incident_date'] ?? '')))) ?></dd></div>
                <div><dt>Hora desde</dt><dd><?= e(substr((string) ($record['time_from'] ?? ''), 0, 5)) ?></dd></div>
                <div><dt>Hora hasta</dt><dd><?= e(substr((string) ($record['time_to'] ?? ''), 0, 5)) ?></dd></div>
                <div><dt>Sector</dt><dd><?= e((string) ($record['sector_name'] ?? '—')) ?></dd></div>
                <div><dt>Cámara</dt><dd><?= e((string) ($record['camera_name'] ?? '—')) ?></dd></div>
            </dl>
            <p><?= nl2br(e((string) ($record['incident_description'] ?? ''))) ?></p>
        </article>

        <?php if (($record['cameras'] ?? []) !== []): ?>
            <article class="page-card mb-3">
                <h3 class="h5">Cámaras revisadas</h3>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($record['cameras'] as $cameraRow): ?>
                        <li class="mb-2">
                            <strong><?= e((string) ($cameraRow['camera_name'] ?? '—')) ?></strong>
                            <span class="senda-badge senda-badge--info"><?= e((string) ($cameraRow['review_status_label'] ?? '—')) ?></span>
                            <?php if (!empty($cameraRow['notes'])): ?><small class="d-block"><?= e((string) $cameraRow['notes']) ?></small><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
        <?php endif; ?>

        <article class="page-card mb-3">
            <h3 class="h5">Denuncia</h3>
            <p>
                <strong>Denuncia informada:</strong> <?= $hasComplaint ? 'Sí' : 'No' ?> ·
                <strong>Denuncia verificada:</strong> <?= $complaintVerified ? 'Sí' : 'No' ?>
            </p>
            <?php if ($hasComplaint): ?>
                <dl class="cctv-detail-grid">
                    <div><dt>Institución</dt><dd><?= e((string) ($record['complaint_institution_label'] ?? '—')) ?></dd></div>
                    <div><dt>N.º identificador</dt><dd><?= e((string) ($record['complaint_number'] ?? '—')) ?></dd></div>
                    <div><dt>Fecha</dt><dd><?= !empty($record['complaint_date']) ? e(date('d/m/Y', strtotime((string) $record['complaint_date']))) : '—' ?></dd></div>
                    <?php if ($complaintVerified): ?>
                        <div><dt>Verificada por</dt><dd><?= e((string) ($record['complaint_verified_by_name'] ?? '—')) ?></dd></div>
                    <?php endif; ?>
                </dl>
                <?php if (!empty($record['complaint_observations'])): ?>
                    <p><?= nl2br(e((string) $record['complaint_observations'])) ?></p>
                <?php endif; ?>
                <?php if (!empty($record['complaint_document_path']) && hasPermission('cctv.recordings.view_complaint_document')): ?>
                    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/cctv/recording-requests/' . ($record['id'] ?? '') . '/complaint-document')) ?>">Descargar documento</a>
                <?php endif; ?>
            <?php elseif (hasPermission('cctv.recordings.edit') && empty($record['is_immutable'])): ?>
                <form method="post" action="<?= e(url('/cctv/recording-requests/' . ($record['id'] ?? '') . '/complaint')) ?>" enctype="multipart/form-data" class="mt-3">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="complaint_institution">Institución</label>
                            <select class="form-select" id="complaint_institution" name="complaint_institution" required>
                                <option value="">Seleccione</option>
                                <?php foreach ($complaintInstitutions ?? [] as $institution): ?>
                                    <option value="<?= e((string) $institution['value']) ?>"><?= e((string) $institution['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="complaint_number">N.º de denuncia / parte / identificador</label>
                            <input type="text" class="form-control" id="complaint_number" name="complaint_number" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="complaint_date">Fecha de la denuncia</label>
                            <input type="date" class="form-control" id="complaint_date" name="complaint_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="complaint_observations">Observaciones</label>
                        <textarea class="form-control" id="complaint_observations" name="complaint_observations" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="complaint_document">Documento de respaldo</label>
                        <input type="file" class="form-control" id="complaint_document" name="complaint_document" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                    <button type="submit" class="btn btn-navy">Registrar denuncia</button>
                </form>
            <?php endif; ?>

            <?php if ($hasComplaint && !$complaintVerified && hasPermission('cctv.recordings.verify_complaint')): ?>
                <form method="post" action="<?= e(url('/cctv/recording-requests/' . ($record['id'] ?? '') . '/verify-complaint')) ?>" class="mt-3">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="verify_notes">Notas de verificación</label>
                        <textarea class="form-control" id="verify_notes" name="notes" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-navy">Verificar denuncia</button>
                </form>
            <?php endif; ?>
        </article>

        <?php if (($record['delivery'] ?? null) !== null): ?>
            <article class="page-card mb-3">
                <h3 class="h5">Entrega</h3>
                <dl class="cctv-detail-grid">
                    <div><dt>Fecha y hora</dt><dd><?= e(date('d/m/Y H:i', strtotime((string) ($record['delivery']['delivered_at'] ?? 'now')))) ?></dd></div>
                    <div><dt>Entregó</dt><dd><?= e((string) ($record['delivery']['delivered_by_name'] ?? '—')) ?></dd></div>
                    <div><dt>Recibió</dt><dd><?= e((string) ($record['delivery']['receiver_name'] ?? '—')) ?></dd></div>
                    <div><dt>RUT receptor</dt><dd><?= e((string) ($record['delivery']['receiver_rut'] ?? '—')) ?></dd></div>
                    <div><dt>Relación</dt><dd><?= e((string) ($record['delivery']['receiver_relationship_label'] ?? '—')) ?></dd></div>
                    <div><dt>Medio</dt><dd><?= e((string) ($record['delivery']['delivery_medium_label'] ?? '—')) ?></dd></div>
                    <?php if (!empty($record['delivery']['authorization_document'])): ?>
                        <div><dt>Autorización</dt><dd><?= e((string) $record['delivery']['authorization_document']) ?></dd></div>
                    <?php endif; ?>
                </dl>
            </article>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <?php if (($record['allowed_statuses'] ?? []) !== [] && hasPermission('cctv.recordings.review')): ?>
            <article class="page-card mb-3">
                <h3 class="h5">Cambiar estado</h3>
                <form method="post" action="<?= e(url('/cctv/recording-requests/' . ($record['id'] ?? '') . '/status')) ?>" id="cctv-status-form">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="status">Nuevo estado</label>
                        <select class="form-select" id="status" name="status" required data-status-select>
                            <?php foreach ($record['allowed_statuses'] as $option): ?>
                                <option value="<?= e($option) ?>"><?= e((new \App\Services\Cctv\RecordingRequestStatusCatalog())->label($option)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" data-status-extra="rejected" hidden>
                        <label class="form-label" for="rejection_reason">Motivo de rechazo</label>
                        <select class="form-select" id="rejection_reason" name="rejection_reason">
                            <?php foreach ($rejectionReasons ?? [] as $reason): ?>
                                <option value="<?= e((string) $reason['value']) ?>"><?= e((string) $reason['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" data-status-extra="recording_not_found" hidden>
                        <label class="form-label" for="not_found_reason">Motivo</label>
                        <select class="form-select" id="not_found_reason" name="not_found_reason">
                            <?php foreach ($notFoundReasons ?? [] as $reason): ?>
                                <option value="<?= e((string) $reason['value']) ?>"><?= e((string) $reason['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label mt-2" for="not_found_cameras_reviewed">Cámaras revisadas</label>
                        <textarea class="form-control" id="not_found_cameras_reviewed" name="not_found_cameras_reviewed" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="notes">Notas</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-navy">Actualizar estado</button>
                </form>
            </article>
        <?php endif; ?>

        <?php if ($status === 'recording_found' && hasPermission('cctv.recordings.review') && empty($record['recording_preserved'])): ?>
            <article class="page-card mb-3">
                <h3 class="h5">Preservar grabación</h3>
                <form method="post" action="<?= e(url('/cctv/recording-requests/' . ($record['id'] ?? '') . '/preserve')) ?>">
                    <?= csrf_field() ?>
                    <p class="text-secondary small">Registro administrativo para evitar eliminación del material mientras la solicitud esté en trámite.</p>
                    <button type="submit" class="btn btn-outline-navy">Marcar como preservada</button>
                </form>
            </article>
        <?php endif; ?>

        <?php if ($status === 'approved' && hasPermission('cctv.recordings.deliver')): ?>
            <article class="page-card mb-3">
                <h3 class="h5">Registrar entrega</h3>
                <form method="post"
                      action="<?= e(url('/cctv/recording-requests/' . ($record['id'] ?? '') . '/deliver')) ?>"
                      id="cctv-delivery-form"
                      data-delivery-summary="<?= e(json_encode($record['delivery_summary'] ?? [], JSON_UNESCAPED_UNICODE)) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="receiver_name">Persona que retira</label>
                        <input type="text" class="form-control" id="receiver_name" name="receiver_name" value="<?= e((string) ($record['requester_name'] ?? '')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="receiver_rut">RUT de quien retira</label>
                        <input type="text" class="form-control" id="receiver_rut" name="receiver_rut" value="<?= e((string) ($record['requester_rut'] ?? '')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="receiver_relationship">Relación con el solicitante</label>
                        <select class="form-select" id="receiver_relationship" name="receiver_relationship" required>
                            <?php foreach ($receiverRelationships ?? [] as $relationship): ?>
                                <option value="<?= e((string) $relationship['value']) ?>" <?= ($relationship['value'] ?? '') === 'solicitante' ? 'selected' : '' ?>>
                                    <?= e((string) $relationship['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="authorization_document">Documento o autorización presentada</label>
                        <input type="text" class="form-control" id="authorization_document" name="authorization_document">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="delivery_medium">Medio de entrega</label>
                        <select class="form-select" id="delivery_medium" name="delivery_medium" required>
                            <?php foreach ($deliveryMedia ?? [] as $medium): ?>
                                <option value="<?= e((string) $medium['value']) ?>"><?= e((string) $medium['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="delivery_notes">Observaciones</label>
                        <textarea class="form-control" id="delivery_notes" name="delivery_notes" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-navy">Confirmar entrega</button>
                </form>
            </article>
        <?php endif; ?>

        <?php if (hasPermission('cctv.recordings.cancel') && !in_array($status, ['delivered', 'cancelled'], true)): ?>
            <article class="page-card mb-3">
                <h3 class="h5">Anular solicitud</h3>
                <form method="post" action="<?= e(url('/cctv/recording-requests/' . ($record['id'] ?? '') . '/cancel')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="cancellation_reason">Motivo</label>
                        <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="2" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-danger">Anular solicitud</button>
                </form>
            </article>
        <?php endif; ?>

        <article class="page-card">
            <h3 class="h5">Historial</h3>
            <?php if (($record['history'] ?? []) === []): ?>
                <p class="text-secondary mb-0">Sin movimientos registrados.</p>
            <?php else: ?>
                <ul class="cctv-visit-history">
                    <?php foreach ($record['history'] as $item): ?>
                        <li>
                            <strong><?= e(date('d/m/Y H:i', strtotime((string) ($item['created_at'] ?? 'now')))) ?></strong>
                            <span><?= e((string) ($item['event_label'] ?? $item['new_status_label'] ?? '—')) ?></span>
                            <?php if (!empty($item['notes'])): ?><p><?= e((string) $item['notes']) ?></p><?php endif; ?>
                            <small>Operador: <?= e((string) ($item['changed_by_name'] ?? '—')) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </div>
</div>
