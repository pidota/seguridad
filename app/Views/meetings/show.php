<?php

$meeting = is_array($meeting ?? null) ? $meeting : [];
$sourceModule = (string) ($sourceModule ?? ($meeting['source_module'] ?? 'admin'));
$id = (int) ($meeting['id'] ?? 0);
$listUrl = (string) ($listUrl ?? url('/meetings'));
$canEdit = !empty($canEdit);

?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1"><?= $sourceModule === 'senda' ? 'SENDA' : 'Reuniones' ?></p>
        <h2 class="page-card__title mb-1"><?= e((string) ($meeting['meeting_number'] ?? 'Reunión')) ?></h2>
        <p class="text-secondary mb-0">
            Estado: <?= e((string) ($meeting['status_label'] ?? '—')) ?>
            · Fecha: <?= e(!empty($meeting['meeting_date']) ? date('d-m-Y', strtotime((string) $meeting['meeting_date'])) : '—') ?>
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($canFinalize ?? false): ?>
            <form method="post" action="<?= e((string) ($finalizeUrl ?? url('/meetings/' . $id . '/finalize'))) ?>"
                  data-confirm="Se finalizará el registro, se calculará el hash de contenido y se enviarán solicitudes de firma. Luego no podrá editarlo."
                  data-confirm-title="Finalizar y solicitar firmas">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-navy">Finalizar y solicitar firmas</button>
            </form>
        <?php endif; ?>
        <?php if ($canSign ?? false): ?>
            <a class="btn btn-navy" href="<?= e((string) ($signUrl ?? url('/meetings/' . $id . '/sign'))) ?>">Revisar y firmar</a>
        <?php endif; ?>
        <?php if ($canEdit): ?>
            <a class="btn btn-outline-navy" href="<?= e((string) ($editUrl ?? url('/meetings/' . $id . '/edit'))) ?>">Editar borrador</a>
        <?php endif; ?>
        <?php if ($canReopen ?? false): ?>
            <button type="button" class="btn btn-outline-navy" data-bs-toggle="modal" data-bs-target="#meeting-reopen-modal">Reabrir para corrección</button>
        <?php endif; ?>
        <?php if ($canCancel ?? false): ?>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#meeting-cancel-modal">Anular reunión</button>
        <?php endif; ?>
        <?php if ($canDelete ?? false): ?>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#meeting-delete-modal">Eliminar</button>
        <?php endif; ?>
        <a class="btn btn-outline-navy" href="<?= e($listUrl) ?>">Volver al listado</a>
    </div>
</section>

<?php if (!empty($sendaNav)): ?>
    <?= senda_nav($sendaNav) ?>
<?php endif; ?>

<div class="page-card mb-3">
    <h3 class="page-card__title">Datos generales</h3>
    <dl class="meetings-summary">
        <div><dt>Fecha</dt><dd><?= e(!empty($meeting['meeting_date']) ? date('d-m-Y', strtotime((string) $meeting['meeting_date'])) : '—') ?></dd></div>
        <div><dt>Hora</dt><dd><?= e(!empty($meeting['meeting_time']) ? substr((string) $meeting['meeting_time'], 0, 5) : '—') ?></dd></div>
        <div><dt>Lugar</dt><dd><?= e((string) ($meeting['meeting_place'] ?? '—')) ?></dd></div>
        <div><dt>Módulo origen</dt><dd><?= e((string) ($meeting['source_module_label'] ?? '—')) ?></dd></div>
        <div><dt>Creada por</dt><dd><?= e((string) ($meeting['created_by_name'] ?? '—')) ?></dd></div>
    </dl>
</div>

<div class="page-card mb-3">
    <h3 class="page-card__title">Participantes</h3>
    <ol class="mb-0">
        <?php foreach ($meeting['participants'] ?? [] as $participant): ?>
            <li class="mb-2">
                <strong><?= e((string) ($participant['display_name'] ?? '—')) ?></strong>
                <?php if (($participant['participant_type'] ?? '') === 'external'): ?>
                    <span class="text-secondary"> — <?= e(trim(implode(' · ', array_filter([
                        (string) ($participant['external_position'] ?? ''),
                        (string) ($participant['external_organization'] ?? ''),
                    ])))) ?></span>
                <?php endif; ?>
                <?php if (!empty($participant['signature_required'])): ?>
                    <span class="badge text-bg-light border ms-1">Requiere firma</span>
                <?php endif; ?>
                <?php if (!empty($participant['attendance_label'])): ?>
                    <span class="badge text-bg-light border ms-1"><?= e((string) $participant['attendance_label']) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</div>

<div class="page-card mb-3">
    <h3 class="page-card__title">Temas abordados</h3>
    <ol class="mb-0">
        <?php foreach ($meeting['topics'] ?? [] as $topic): ?>
            <li class="mb-2"><?= nl2br(e((string) ($topic['description'] ?? ''))) ?></li>
        <?php endforeach; ?>
    </ol>
</div>

<div class="page-card mb-3">
    <h3 class="page-card__title">Acuerdos</h3>
    <ol class="mb-0">
        <?php foreach ($meeting['agreements'] ?? [] as $agreement): ?>
            <li class="mb-2">
                <?= nl2br(e((string) ($agreement['description'] ?? ''))) ?>
                <?php if (($agreement['responsible_label'] ?? '—') !== '—' || !empty($agreement['due_date'])): ?>
                    <div class="text-secondary small mt-1">
                        Responsable: <?= e((string) ($agreement['responsible_label'] ?? '—')) ?>
                        <?php if (!empty($agreement['due_date'])): ?>
                            · Compromiso: <?= e(date('d-m-Y', strtotime((string) $agreement['due_date']))) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</div>

<?php if (trim((string) ($meeting['additional_notes'] ?? '')) !== ''): ?>
    <div class="page-card mb-3">
        <h3 class="page-card__title">Observaciones y compromisos adicionales</h3>
        <p class="mb-0"><?= nl2br(e((string) $meeting['additional_notes'])) ?></p>
    </div>
<?php endif; ?>

<?php if (!empty($meeting['next_meeting_required'])): ?>
    <div class="page-card mb-3">
        <h3 class="page-card__title">Próxima reunión o seguimiento</h3>
        <p class="mb-1">
            <?= e(!empty($meeting['next_meeting_date']) ? date('d-m-Y', strtotime((string) $meeting['next_meeting_date'])) : '—') ?>
            <?php if (!empty($meeting['next_meeting_time'])): ?>
                · <?= e(substr((string) $meeting['next_meeting_time'], 0, 5)) ?>
            <?php endif; ?>
        </p>
        <?php if (trim((string) ($meeting['next_meeting_notes'] ?? '')) !== ''): ?>
            <p class="text-secondary mb-0"><?= nl2br(e((string) $meeting['next_meeting_notes'])) ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (($signatures ?? []) !== []): ?>
    <div class="page-card mb-3">
        <h3 class="page-card__title">Firmas simples internas</h3>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Participante</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($signatures as $signature): ?>
                        <tr>
                            <td><?= e((string) ($signature['user_name'] ?? '—')) ?></td>
                            <td><?= e((string) ($signature['status_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($signature['signed_at_label'] ?? '—')) ?></td>
                            <td>
                                <?php if (($signature['status'] ?? '') === 'signed' && !empty($signature['signature_snapshot_path'])): ?>
                                    <img src="<?= e(url('/meetings/signatures/' . (int) ($signature['id'] ?? 0) . '/image')) ?>"
                                         alt="Firma de <?= e((string) ($signature['user_name'] ?? '')) ?>"
                                         class="meetings-signature-thumb">
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($meeting['content_hash'])): ?>
            <p class="text-secondary small mb-0 mt-3">
                Hash de contenido al finalizar: <code><?= e(substr((string) $meeting['content_hash'], 0, 16)) ?>…</code>
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!empty($timeline) || !empty($auditEntries)): ?>
    <div class="page-card mb-3">
        <h3 class="page-card__title">Historial</h3>
        <?php if (!empty($timeline)): ?>
            <div class="meetings-timeline mb-4">
                <?php foreach ($timeline as $event): ?>
                    <div class="meetings-timeline__item">
                        <div class="meetings-timeline__icon"><i class="bi <?= e((string) ($event['icon'] ?? 'bi-circle')) ?>"></i></div>
                        <div>
                            <strong><?= e((string) ($event['title'] ?? '')) ?></strong>
                            <div class="text-secondary small"><?= e((string) ($event['datetime_label'] ?? '—')) ?></div>
                            <?php foreach ($event['lines'] ?? [] as $line): ?>
                                <div class="small mb-0"><?= e((string) $line) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($auditEntries)): ?>
            <h4 class="h6">Registro de auditoría</h4>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Acción</th>
                            <th>Usuario</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditEntries as $entry): ?>
                            <tr>
                                <td><?= e((string) ($entry['datetime_label'] ?? '—')) ?></td>
                                <td><?= e((string) ($entry['action_label'] ?? '—')) ?></td>
                                <td><?= e((string) ($entry['user_name'] ?? '—')) ?></td>
                                <td><?= e((string) ($entry['ip_address'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($canCancel ?? false): ?>
    <div class="modal fade" id="meeting-cancel-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="<?= e((string) ($cancelUrl ?? url('/meetings/' . $id . '/cancel'))) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Anular reunión</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary">La anulación invalidará las firmas pendientes. Esta acción no elimina el registro.</p>
                    <label class="form-label" for="cancellation_reason">Motivo</label>
                    <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="4" minlength="10" maxlength="1000" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-navy" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-danger">Confirmar anulación</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($canReopen ?? false): ?>
    <div class="modal fade" id="meeting-reopen-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="<?= e((string) ($reopenUrl ?? url('/meetings/' . $id . '/reopen'))) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Reabrir reunión</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary">El registro volverá a borrador, se invalidarán las firmas previas y deberá finalizarlo nuevamente.</p>
                    <label class="form-label" for="reopen_reason">Motivo</label>
                    <textarea class="form-control" id="reopen_reason" name="reopen_reason" rows="4" minlength="10" maxlength="1000" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-navy" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-navy">Confirmar reapertura</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($canDelete ?? false): ?>
    <div class="modal fade" id="meeting-delete-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="<?= e((string) ($deleteUrl ?? url('/meetings/' . $id . '/delete'))) ?>"
                  data-confirm="Esta acción eliminará el registro de forma permanente. ¿Desea continuar?"
                  data-confirm-title="Eliminar reunión">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Eliminar reunión</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary mb-0">
                        Solo puede eliminar esta reunión porque ningún invitado externo ha confirmado su asistencia.
                        Se invalidarán las firmas pendientes y el registro dejará de estar disponible.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-navy" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-danger">Confirmar eliminación</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
