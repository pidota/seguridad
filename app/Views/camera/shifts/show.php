<?php
$shift = $shift ?? [];
$stats = $stats ?? [];
$openingChecks = $openingChecks ?? [];
$closingChecks = $closingChecks ?? [];
$shiftId = (int) ($shift['id'] ?? 0);
$openingNotes = trim((string) ($shift['opening_notes'] ?? ''));
$closingNotes = trim((string) ($shift['closing_notes'] ?? ''));
$isClosed = !empty($shift['is_closed']);
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Central de Cámaras</p>
        <h2 class="page-card__title mb-1">Detalle de turno CCTV</h2>
        <p class="text-secondary mb-0">Reconstrucción completa de la jornada operativa</p>
    </div>
    <a href="<?= e(url('/cctv/shifts')) ?>" class="btn btn-outline-navy">Volver al historial</a>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<section class="page-card cctv-shift-detail mb-3">
    <div class="cctv-shift-detail__header">
        <div>
            <p class="welcome-kicker mb-1">Turno del <?= e((string) ($shift['shift_date_formatted'] ?? '—')) ?></p>
            <h3 class="page-card__title mb-0"><?= e((string) ($shift['operator_label'] ?? '—')) ?></h3>
        </div>
        <span class="camera-device-badge camera-device-badge--<?= e((string) ($shift['status_tone'] ?? 'other')) ?>">
            <?= e((string) ($shift['status_label'] ?? '—')) ?>
        </span>
    </div>

    <dl class="cctv-shift-detail__meta">
        <div>
            <dt>Operador</dt>
            <dd><?= e((string) ($shift['operator_label'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Inicio</dt>
            <dd><?= e((string) ($shift['started_at_formatted'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Término</dt>
            <dd><?= $isClosed ? e((string) ($shift['ended_at_formatted'] ?? '—')) : 'En curso' ?></dd>
        </div>
        <div>
            <dt>Duración</dt>
            <dd><?= e((string) ($shift['duration_label'] ?? '—')) ?></dd>
        </div>
    </dl>

    <dl class="cctv-shift-detail__stats">
        <div>
            <dt>Registros</dt>
            <dd><?= (int) ($stats['total_entries'] ?? 0) ?></dd>
        </div>
        <div>
            <dt>Incidentes</dt>
            <dd><?= (int) ($stats['incidents'] ?? 0) ?></dd>
        </div>
        <div>
            <dt>Novedades técnicas</dt>
            <dd><?= (int) ($stats['technical_issues'] ?? 0) ?></dd>
        </div>
        <div>
            <dt>Coordinaciones</dt>
            <dd><?= (int) ($stats['coordinations'] ?? 0) ?></dd>
        </div>
    </dl>
</section>

<div class="cctv-shift-detail__grid mb-3">
    <section class="page-card cctv-shift-detail__panel">
        <h3 class="cctv-shift-detail__panel-title">Recepción de equipos</h3>
        <p class="cctv-shift-detail__panel-hint mb-3">Estado del puesto al inicio del turno.</p>
        <?= \Core\View::make('camera/shifts/_equipment-checklist-readonly', [
            'checks' => $openingChecks,
            'emptyMessage' => 'No hay recepción de equipos registrada.',
        ], null) ?>
        <?php if ($openingNotes !== ''): ?>
            <div class="cctv-shift-detail__notes mt-3">
                <h4 class="cctv-shift-detail__notes-title">Observaciones de recepción</h4>
                <p class="mb-0"><?= nl2br(e($openingNotes)) ?></p>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($isClosed): ?>
        <section class="page-card cctv-shift-detail__panel">
            <h3 class="cctv-shift-detail__panel-title">Entrega de equipos</h3>
            <p class="cctv-shift-detail__panel-hint mb-3">Estado del puesto al cierre del turno.</p>
            <?= \Core\View::make('camera/shifts/_equipment-checklist-readonly', [
                'checks' => $closingChecks,
                'emptyMessage' => 'No hay entrega de equipos registrada.',
            ], null) ?>
            <?php if ($closingNotes !== ''): ?>
                <div class="cctv-shift-detail__notes mt-3">
                    <h4 class="cctv-shift-detail__notes-title">Observaciones de cierre</h4>
                    <p class="mb-0"><?= nl2br(e($closingNotes)) ?></p>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<section class="page-card cctv-shift-detail__summary mb-3">
    <div class="cctv-shift-detail__summary-head">
        <div>
            <h3 class="page-card__title mb-1">Resumen operativo</h3>
            <p class="cctv-shift-detail__panel-hint mb-0">Incidentes, novedades técnicas y coordinaciones del turno.</p>
        </div>
    </div>
    <div class="cctv-shift-detail__categories">
        <?= \Core\View::make('camera/shifts/_category-entries', [
            'items' => $incidents ?? [],
            'title' => 'Incidentes',
            'emptyMessage' => 'No se registraron incidentes.',
            'canViewLog' => !empty($canViewLog),
        ], null) ?>
        <?= \Core\View::make('camera/shifts/_category-entries', [
            'items' => $technicalIssues ?? [],
            'title' => 'Novedades técnicas',
            'emptyMessage' => 'No se registraron novedades técnicas.',
            'canViewLog' => !empty($canViewLog),
        ], null) ?>
        <?= \Core\View::make('camera/shifts/_category-entries', [
            'items' => $coordinations ?? [],
            'title' => 'Coordinaciones',
            'emptyMessage' => 'No se registraron coordinaciones.',
            'canViewLog' => !empty($canViewLog),
        ], null) ?>
    </div>
</section>

<?= \Core\View::make('camera/shifts/_shift-timeline', [
    'shiftTimeline' => $shiftTimeline ?? [],
    'logOrderOptions' => $logOrderOptions ?? [],
    'title' => 'Línea cronológica de bitácora',
    'hint' => 'Inicio, registros operativos y cierre en orden de la jornada.',
    'sectionId' => 'bitacora-turno-detalle',
    'showOrderToggle' => true,
    'formAction' => url('/cctv/shifts/' . $shiftId),
    'canViewLog' => !empty($canViewLog),
], null) ?>
