<section class="page-toolbar">
    <div>
        <h2 class="page-card__title mb-1">Detalle de visita</h2>
        <p class="text-secondary mb-0"><?= e((string) ($record['visitor_type_label'] ?? '')) ?></p>
    </div>
    <?php if ($recording !== null): ?>
        <a class="btn btn-outline-navy" href="<?= e(url('/cctv/recording-requests/' . ($recording['id'] ?? ''))) ?>">Ver solicitud</a>
    <?php endif; ?>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<div class="page-card">
    <dl class="cctv-detail-grid">
        <div><dt>Fecha</dt><dd><?= e(date('d/m/Y', strtotime((string) ($record['visit_date'] ?? '')))) ?></dd></div>
        <div><dt>Hora ingreso</dt><dd><?= e(substr((string) ($record['arrival_time'] ?? ''), 0, 5)) ?></dd></div>
        <div><dt>Hora salida</dt><dd><?= !empty($record['departure_time']) ? e(substr((string) $record['departure_time'], 0, 5)) : '—' ?></dd></div>
        <div><dt>Persona</dt><dd><?= e((string) ($record['requester_name'] ?? '—')) ?></dd></div>
        <div><dt>RUT</dt><dd><?= e((string) ($record['requester_rut'] ?? '—')) ?></dd></div>
        <div><dt>Teléfono</dt><dd><?= e((string) ($record['requester_phone'] ?? '—')) ?></dd></div>
        <div><dt>Institución</dt><dd><?= e((string) ($record['organization'] ?? '—')) ?></dd></div>
        <div><dt>Operador</dt><dd><?= e((string) ($record['operator_name'] ?? '—')) ?></dd></div>
        <?php if (!empty($record['visit_reason_label'])): ?>
            <div><dt>Motivo visita</dt><dd><?= e((string) $record['visit_reason_label']) ?></dd></div>
        <?php endif; ?>
    </dl>
    <div class="mt-3">
        <h3 class="h6">Descripción</h3>
        <p><?= nl2br(e((string) ($record['reason'] ?? ''))) ?></p>
    </div>

    <?php if (empty($record['departure_time']) && hasPermission('cctv.visits.edit') && empty($record['recording_requested'])): ?>
        <form method="post" action="<?= e(url('/cctv/visits/' . ($record['id'] ?? '') . '/departure')) ?>" class="mt-3">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-navy">Registrar salida</button>
        </form>
    <?php endif; ?>
</div>
