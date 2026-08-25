<?php
$openShift = $openShift ?? null;
$shiftStats = $shiftStats ?? [];
$recentEntries = $recentEntries ?? [];
$incomingCoordinations = $incomingCoordinations ?? [];
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Módulo operativo</p>
        <h2 class="page-card__title mb-1">Guardias Municipales</h2>
        <p class="text-secondary mb-0">Turnos, rondas y novedades en terreno<?= !empty($openShiftsCount) ? ' · ' . (int) $openShiftsCount . ' turno(s) activo(s)' : '' ?></p>
    </div>
    <?php if (!empty($canStartShift)): ?>
        <a href="<?= e(url('/guards/shifts/create')) ?>" class="btn btn-navy">Iniciar turno</a>
    <?php endif; ?>
</section>

<?= guards_nav($guardsNav ?? []) ?>

<?php if (!empty($openShift)): ?>
    <section class="guards-active-shift mb-3" id="turno-activo">
        <div class="guards-active-shift__header">
            <div>
                <p class="welcome-kicker mb-1">Servicio en terreno</p>
                <h3 class="guards-active-shift__title mb-0">TURNO ACTIVO</h3>
            </div>
            <span class="camera-device-badge camera-device-badge--success">En curso</span>
        </div>

        <dl class="guards-active-shift__stats">
            <div>
                <dt>Guardia</dt>
                <dd><?= e((string) ($openShift['guard_label'] ?? '—')) ?></dd>
            </div>
            <div>
                <dt>Inicio</dt>
                <dd><?= e((string) ($openShift['started_time_formatted'] ?? '—')) ?></dd>
            </div>
            <div>
                <dt>Duración</dt>
                <dd><?= e((string) ($openShift['duration_label'] ?? '—')) ?></dd>
            </div>
            <div>
                <dt>Novedades</dt>
                <dd><?= (int) ($shiftStats['total_entries'] ?? 0) ?></dd>
            </div>
            <div>
                <dt>Incidencias</dt>
                <dd><?= (int) ($shiftStats['incidents'] ?? 0) ?></dd>
            </div>
            <div>
                <dt>Rondas</dt>
                <dd><?= (int) ($shiftStats['rounds'] ?? 0) ?></dd>
            </div>
            <div>
                <dt>Notificadas a CCTV</dt>
                <dd><?= (int) ($shiftStats['cctv_linked'] ?? 0) ?></dd>
            </div>
        </dl>

        <div class="guards-active-shift__actions">
            <?php if (!empty($canCreateLog)): ?>
                <a href="<?= e(url('/guards/log/create')) ?>" class="btn btn-navy">+ Registrar novedad</a>
            <?php endif; ?>
            <a href="<?= e(url('/guards/shifts/' . (int) ($openShift['id'] ?? 0))) ?>" class="btn btn-outline-navy">Ver turno</a>
            <?php if (!empty($canCloseShift)): ?>
                <a href="<?= e(url('/guards/shifts/close')) ?>" class="btn btn-outline-danger">Finalizar turno</a>
            <?php endif; ?>
        </div>

        <?php if ($recentEntries !== []): ?>
            <div class="guards-active-shift__recent">
                <h4 class="h6 mb-3">Últimas novedades del turno</h4>
                <?php foreach ($recentEntries as $item): ?>
                    <article class="guards-active-shift__recent-item">
                        <div class="d-flex justify-content-between gap--2 mb-1">
                            <time><?= e((string) ($item['time_label'] ?? '—')) ?></time>
                            <span class="camera-device-badge camera-device-badge--<?= e((string) ($item['type_tone'] ?? 'other')) ?>">
                                <?= e((string) ($item['type_label'] ?? 'REGISTRO')) ?>
                            </span>
                        </div>
                        <p class="guards-log-summary mb-1"><?= e((string) ($item['summary'] ?? '—')) ?></p>
                        <a href="<?= e(url('/guards/log/' . (int) ($item['id'] ?? 0))) ?>">Ver detalle</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php elseif (!empty($canStartShift)): ?>
    <div class="page-card mb-3">
        <p class="mb-3">No tiene un turno abierto. Inicie su turno para registrar novedades y rondas en terreno.</p>
        <a href="<?= e(url('/guards/shifts/create')) ?>" class="btn btn-navy">Iniciar turno</a>
    </div>
<?php endif; ?>

<div class="page-card guards-cctv-panel">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h3 class="h5 mb-1">Coordinaciones desde monitoreo CCTV</h3>
            <p class="text-secondary mb-0">Incidentes donde operadores CCTV notificaron a Guardias Municipales.</p>
        </div>
        <?php if ((int) ($incomingCount ?? 0) > 0): ?>
            <a href="<?= e(url('/guards/cctv-coordinations')) ?>" class="btn btn-outline-navy btn-sm">Ver todas (<?= (int) $incomingCount ?>)</a>
        <?php endif; ?>
    </div>

    <?php if ($incomingCoordinations === []): ?>
        <p class="text-secondary mb-0">No hay coordinaciones recientes desde la bitácora de operadores.</p>
    <?php else: ?>
        <?php foreach ($incomingCoordinations as $item): ?>
            <article class="guards-cctv-panel__item">
                <div class="d-flex justify-content-between gap-2 mb-1">
                    <strong><?= e((string) ($item['log_type_name'] ?? 'Incidente')) ?></strong>
                    <time class="text-secondary"><?= e((string) ($item['occurred_at_formatted'] ?? '—')) ?></time>
                </div>
                <p class="guards-log-summary mb-1"><?= e((string) ($item['summary'] ?? '—')) ?></p>
                <p class="small text-secondary mb-1">
                    Operador CCTV: <?= e((string) ($item['cctv_operator_name'] ?? '—')) ?>
                    · Sector: <?= e((string) ($item['sector_label'] ?? '—')) ?>
                </p>
                <?php if (!empty($canViewCctv) && !empty($item['cctv_log_url'])): ?>
                    <a href="<?= e((string) $item['cctv_log_url']) ?>">Ver en bitácora CCTV</a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
