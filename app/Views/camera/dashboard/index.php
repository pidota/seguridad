<?php

$shiftPanel = $shiftPanel ?? [];

$openShift = $shiftPanel['open_shift'] ?? null;

$lastShift = $shiftPanel['last_shift'] ?? null;

$canStart = !empty($shiftPanel['can_start']);

$openingChecks = $shiftPanel['opening_checks'] ?? [];

$showShiftPanel = hasPermission('cctv.shifts.view');

?>

<section class="page-toolbar">

    <div>

        <h2 class="page-card__title mb-0">Central de Cámaras</h2>

    </div>

</section>



<?= cameras_nav($camerasNav ?? []) ?>

<?php require __DIR__ . '/cameras-map-card.php'; ?>

<?php require __DIR__ . '/supervision.php'; ?>

<?php require __DIR__ . '/visits-panel.php'; ?>

<?php if ($showShiftPanel): ?>

    <?php if ($openShift): ?>

        <?php require __DIR__ . '/active-shift.php'; ?>

    <?php else: ?>

        <div class="page-card cctv-shift-panel mb-3" id="turno-activo">

            <div class="cctv-shift-panel__empty">

                <div>

                    <p class="welcome-kicker mb-1">Turno operativo</p>

                    <p class="cctv-shift-panel__message mb-0">No tienes un turno activo</p>

                </div>

                <?php if ($canStart): ?>

                    <a href="<?= e(url('/cctv/shifts/create')) ?>" class="btn btn-navy">Iniciar Turno</a>

                <?php endif; ?>

            </div>



            <?php if ($lastShift): ?>

                <div class="cctv-shift-panel__last">

                    <h4 class="cctv-shift-panel__subtitle">Último turno</h4>

                    <dl class="cctv-shift-panel__meta cctv-shift-panel__meta--last">

                        <div>

                            <dt>Fecha</dt>

                            <dd><?= e((string) ($lastShift['shift_date_formatted'] ?? '—')) ?></dd>

                        </div>

                        <div>

                            <dt>Inicio</dt>

                            <dd><?= e((string) ($lastShift['started_at_formatted'] ?? '—')) ?></dd>

                        </div>

                        <div>

                            <dt>Término</dt>

                            <dd><?= e((string) ($lastShift['ended_at_formatted'] ?? '—')) ?></dd>

                        </div>

                        <div>

                            <dt>Estado</dt>

                            <dd><?= e((string) ($lastShift['status_label'] ?? '—')) ?></dd>

                        </div>

                    </dl>

                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>

<?php endif; ?>



<?php

require __DIR__ . '/shift-log.php';

?>



<?php if (!empty($canViewLog)): ?>

    <div class="camera-dashboard-grid">

        <a class="camera-stat-card camera-stat-card--link" href="<?= e(url('/cctv/log')) ?>">

            <p class="camera-stat-card__label">Bitácora CCTV</p>

            <p class="camera-stat-card__hint">Consultar novedades registradas</p>

            <span class="camera-stat-card__action">Ir a la bitácora <i class="bi bi-arrow-right"></i></span>

        </a>

    </div>

<?php endif; ?>

