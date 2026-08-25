<?php
$handover = $handover ?? [];
$entry = $handover['entry'] ?? [];
$canAccept = !empty($canAccept);
$canDecline = !empty($canDecline);
$isPending = ($handover['handover_status'] ?? '') === 'pending';
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Revisión individual</p>
        <h2 class="page-card__title mb-1">Incidente / Novedad pendiente de revisión</h2>
        <p class="text-secondary mb-0"><?= e((string) ($handover['entry_reference'] ?? '')) ?></p>
    </div>
    <a class="btn btn-outline-navy" href="<?= e(url('/cctv/handovers')) ?>">Volver al listado</a>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<div class="page-card page-card--lg mb-3">
    <h3 class="h5 mb-3">Datos originales</h3>
    <dl class="cctv-handover-meta">
        <div><dt>Registro</dt><dd><?= e((string) ($handover['entry_reference'] ?? '')) ?></dd></div>
        <div><dt>Fecha creación</dt><dd><?= e((string) ($entry['event_date_formatted'] ?? '—')) ?></dd></div>
        <div><dt>Hora creación</dt><dd><?= e((string) ($entry['event_time_formatted'] ?? '—')) ?></dd></div>
        <div><dt>Operador creador</dt><dd><?= e((string) ($entry['operator_label'] ?? $entry['created_by_name'] ?? '—')) ?></dd></div>
        <div><dt>Tipo</dt><dd><?= e((string) ($entry['log_type_name'] ?? '—')) ?></dd></div>
        <div><dt>Sector</dt><dd><?= e((string) ($entry['sector_name'] ?? '—')) ?></dd></div>
        <div><dt>Cámara</dt><dd><?= e((string) ($entry['camera_name'] ?? '—')) ?></dd></div>
        <div><dt>Estado actual</dt><dd><?= e((string) ($entry['status_label'] ?? '—')) ?></dd></div>
    </dl>
    <p class="mb-0"><strong>Descripción inicial:</strong> <?= e((string) ($entry['observations'] ?? '—')) ?></p>
</div>

<div class="page-card page-card--lg mb-3 cctv-handover-context">
    <h3 class="h5 mb-3">Estado al momento del cambio de turno</h3>

    <div class="cctv-handover-context__block">
        <h4 class="h6">Última actuación registrada</h4>
        <?php if (!empty($handover['last_action'])): ?>
            <p class="mb-0">
                <strong><?= e((string) ($handover['last_action']['time_label'] ?? '')) ?></strong>
                <?= e((string) ($handover['last_action']['description'] ?? '')) ?>
            </p>
            <?php if (!empty($handover['elapsed_since_last_action'])): ?>
                <p class="text-secondary small mb-0"><?= e((string) $handover['elapsed_since_last_action']) ?></p>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-secondary mb-0">Sin actuaciones operacionales registradas.</p>
        <?php endif; ?>
    </div>

    <div class="cctv-handover-context__block">
        <h4 class="h6">Última coordinación</h4>
        <?php if (!empty($handover['last_coordination'])): ?>
            <p class="mb-0">
                Institución: <strong><?= e((string) ($handover['last_coordination']['institution'] ?? '')) ?></strong><br>
                Hora: <?= e((string) ($handover['last_coordination']['time_label'] ?? '')) ?><br>
                Resultado: <?= e((string) ($handover['last_coordination']['result'] ?? '')) ?>
            </p>
        <?php else: ?>
            <p class="text-secondary mb-0">Sin coordinaciones registradas.</p>
        <?php endif; ?>
    </div>

    <div class="cctv-handover-context__block">
        <h4 class="h6">Operador saliente</h4>
        <p class="mb-0">
            <?= e((string) ($handover['from_operator_label'] ?? '—')) ?><br>
            <span class="text-secondary">Entregado: <?= e((string) ($handover['handed_over_at_formatted'] ?? '—')) ?></span>
        </p>
    </div>

    <div class="cctv-handover-context__note">
        <h4 class="h6 mb-2">Observación de entrega</h4>
        <?php if (trim((string) ($handover['handover_notes'] ?? '')) !== ''): ?>
            <blockquote class="mb-0"><?= e((string) $handover['handover_notes']) ?></blockquote>
        <?php else: ?>
            <p class="text-secondary mb-0">Sin observación adicional del operador saliente.</p>
        <?php endif; ?>
    </div>
</div>

<div class="page-card page-card--lg mb-3">
    <h3 class="h5 mb-3">Historial del procedimiento</h3>
    <?php if (($handover['operational_history'] ?? []) === []): ?>
        <p class="text-secondary mb-0">Sin historial operacional.</p>
    <?php else: ?>
        <ul class="cctv-handover-timeline">
            <?php foreach ($handover['operational_history'] as $event): ?>
                <li>
                    <time><?= e((string) ($event['time_label'] ?? '')) ?></time>
                    <strong><?= e((string) ($event['action_label'] ?? '')) ?></strong>
                    <p class="mb-0"><?= e((string) ($event['description'] ?? '')) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php if ($isPending && ($canAccept || $canDecline)): ?>
    <div class="page-card page-card--lg" id="decision">
        <h3 class="h5 mb-3">¿Desea continuar con este procedimiento?</h3>

        <div class="cctv-handover-decision">
            <?php if ($canAccept): ?>
                <form method="post" action="<?= e(url('/cctv/handovers/' . (int) ($handover['id'] ?? 0) . '/accept')) ?>" data-cctv-handover-accept>
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-navy">Sí, continuar procedimiento</button>
                </form>
            <?php endif; ?>

            <?php if ($canDecline): ?>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#decline-form">
                    No continuar procedimiento
                </button>
            <?php endif; ?>
        </div>

        <?php if ($canDecline): ?>
            <div class="collapse mt-4" id="decline-form">
                <form method="post" action="<?= e(url('/cctv/handovers/' . (int) ($handover['id'] ?? 0) . '/decline')) ?>" data-cctv-handover-decline novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="decision_reason">Motivo de no continuidad *</label>
                        <select class="form-select" id="decision_reason" name="decision_reason" required data-decline-reason>
                            <option value="">Seleccione…</option>
                            <?php foreach ($declineReasons ?? [] as $reason): ?>
                                <option value="<?= e((string) ($reason['value'] ?? '')) ?>" <?= old('decision_reason') === ($reason['value'] ?? '') ? 'selected' : '' ?>>
                                    <?= e((string) ($reason['label'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" data-decline-other hidden>
                        <label class="form-label" for="decision_reason_other">Especifique motivo *</label>
                        <input type="text" class="form-control" id="decision_reason_other" name="decision_reason_other" value="<?= e(old('decision_reason_other')) ?>" maxlength="180">
                    </div>
                    <div class="mb-3" data-decline-institution hidden>
                        <label class="form-label" for="delegated_institution">Institución que quedó a cargo</label>
                        <select class="form-select" id="delegated_institution" name="delegated_institution">
                            <option value="">Seleccione…</option>
                            <?php foreach ($institutions ?? [] as $inst): ?>
                                <option value="<?= e((string) ($inst['value'] ?? '')) ?>"><?= e((string) ($inst['label'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" data-decline-approx-time hidden>
                        <label class="form-label" for="approx_completion_time">Hora aproximada de finalización (opcional)</label>
                        <input type="time" class="form-control" id="approx_completion_time" name="approx_completion_time" value="<?= e(old('approx_completion_time')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="decision_details">Justificación *</label>
                        <textarea class="form-control" id="decision_details" name="decision_details" rows="4" required maxlength="2000"><?= e(old('decision_details')) ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger">Confirmar no continuidad</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
<?php elseif (!$isPending): ?>
    <div class="page-card page-card--lg">
        <h3 class="h5 mb-3">Decisión registrada</h3>
        <dl class="cctv-handover-meta">
            <div><dt>Resultado</dt><dd><?= e((string) ($handover['handover_status_label'] ?? '')) ?></dd></div>
            <?php if (($handover['handover_status'] ?? '') === 'not_continued'): ?>
                <div><dt>Operador que finalizó el pendiente</dt><dd><?= e((string) ($handover['reviewed_by_label'] ?? '—')) ?></dd></div>
                <div><dt>Fecha de cierre</dt><dd><?= e((string) ($handover['reviewed_at_formatted'] ?? '—')) ?></dd></div>
                <div><dt>Motivo</dt><dd><?= e((string) ($handover['decision_reason_label'] ?? '—')) ?></dd></div>
            <?php elseif (($handover['handover_status'] ?? '') === 'accepted'): ?>
                <div><dt>Continuidad aceptada por</dt><dd><?= e((string) ($handover['reviewed_by_label'] ?? '—')) ?></dd></div>
                <div><dt>Fecha de revisión</dt><dd><?= e((string) ($handover['reviewed_at_formatted'] ?? '—')) ?></dd></div>
            <?php else: ?>
                <div><dt>Revisado por</dt><dd><?= e((string) ($handover['reviewed_by_label'] ?? '—')) ?></dd></div>
                <div><dt>Fecha</dt><dd><?= e((string) ($handover['reviewed_at_formatted'] ?? '—')) ?></dd></div>
            <?php endif; ?>
        </dl>
        <?php if (trim((string) ($handover['decision_details'] ?? '')) !== ''): ?>
            <p class="mb-3"><strong>Justificación:</strong> <?= e((string) $handover['decision_details']) ?></p>
        <?php endif; ?>
        <?php if (!empty($entry['id'])): ?>
            <a class="btn btn-outline-navy" href="<?= e(url('/cctv/log/' . (int) $entry['id'])) ?>">Ver registro en bitácora</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
