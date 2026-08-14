<?php

$supervision = $supervisionDashboard ?? null;

if ($supervision === null) {
    return;
}

$openShift = $supervision['open_shift'] ?? null;
$openShiftId = (int) ($openShift['id'] ?? 0);
$openShiftsCount = (int) ($supervision['open_shifts_count'] ?? 0);
$today = (string) ($supervision['today'] ?? date('Y-m-d'));
$monthStart = (string) ($supervision['month_start'] ?? date('Y-m-01'));
$monthEnd = (string) ($supervision['month_end'] ?? date('Y-m-t'));
$monthLabel = (string) ($supervision['month_label'] ?? '');
$todayStats = $supervision['today_stats'] ?? [];
$monthStats = $supervision['month_stats'] ?? [];
$incidentsBySector = $supervision['incidents_by_sector'] ?? [];
$incidentsByType = $supervision['incidents_by_type'] ?? [];
$shiftsActivity = $supervision['shifts_activity'] ?? [];
$recentEntries = $supervision['recent_entries'] ?? [];
$policeResponse = $supervision['police_response_time'] ?? [];
$logTodayQuery = ['date_from' => $today, 'date_to' => $today];
$logMonthQuery = ['date_from' => $monthStart, 'date_to' => $monthEnd];

?>
<section class="cctv-supervision mb-3" id="supervision-cctv">
    <div class="cctv-supervision__header">
        <div>
            <p class="welcome-kicker mb-1">Monitoreo central</p>
            <h3 class="cctv-supervision__title mb-0">Supervisión</h3>
        </div>
        <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/cctv/shifts?' . http_build_query(['status' => 'open']))) ?>">
            Ver turnos abiertos<?= $openShiftsCount > 0 ? ' (' . $openShiftsCount . ')' : '' ?>
        </a>
    </div>

    <div class="cctv-supervision__grid">
        <article class="page-card cctv-supervision__panel">
            <div class="cctv-supervision__panel-head">
                <h4 class="cctv-supervision__panel-title mb-0">Turno actual</h4>
                <?php if ($openShiftId > 0): ?>
                    <a class="cctv-supervision__panel-link" href="<?= e(url('/cctv/shifts/' . $openShiftId)) ?>">Ver detalle</a>
                <?php endif; ?>
            </div>

            <?php if ($openShift === null): ?>
                <p class="cctv-supervision__empty mb-0">No hay turnos abiertos en la central.</p>
            <?php else: ?>
                <dl class="cctv-supervision__meta">
                    <div>
                        <dt>Operador</dt>
                        <dd><?= e((string) ($openShift['operator_label'] ?? '—')) ?></dd>
                    </div>
                    <div>
                        <dt>Inicio</dt>
                        <dd><?= e((string) ($openShift['started_at_formatted'] ?? '—')) ?></dd>
                    </div>
                    <div>
                        <dt>Duración</dt>
                        <dd><?= e((string) ($openShift['duration_label'] ?? '—')) ?></dd>
                    </div>
                </dl>
                <?php if ($openShiftsCount > 1): ?>
                    <p class="cctv-supervision__note mb-0"><?= $openShiftsCount ?> turnos abiertos en total. Se muestra el más reciente.</p>
                <?php endif; ?>
            <?php endif; ?>
        </article>

        <article class="page-card cctv-supervision__panel">
            <div class="cctv-supervision__panel-head">
                <h4 class="cctv-supervision__panel-title mb-0">Indicadores de hoy</h4>
                <span class="cctv-supervision__date"><?= e(date('d-m-Y', strtotime($today))) ?></span>
            </div>
            <div class="cctv-supervision__stats">
                <a class="cctv-supervision-stat" href="<?= e(url('/cctv/log?' . http_build_query(array_merge($logTodayQuery, ['log_type' => 'incidente'])))) ?>">
                    <span class="cctv-supervision-stat__label">Incidentes hoy</span>
                    <strong class="cctv-supervision-stat__value"><?= (int) ($todayStats['incidents'] ?? 0) ?></strong>
                </a>
                <a class="cctv-supervision-stat" href="<?= e(url('/cctv/log?' . http_build_query(array_merge($logMonthQuery, ['log_type' => 'incidente'])))) ?>">
                    <span class="cctv-supervision-stat__label">Incidentes del mes</span>
                    <strong class="cctv-supervision-stat__value"><?= (int) ($monthStats['incidents'] ?? 0) ?></strong>
                </a>
                <a class="cctv-supervision-stat" href="<?= e(url('/cctv/log?' . http_build_query(array_merge($logTodayQuery, ['log_type' => 'novedad_tecnica'])))) ?>">
                    <span class="cctv-supervision-stat__label">Novedades técnicas</span>
                    <strong class="cctv-supervision-stat__value"><?= (int) ($todayStats['technical_issues'] ?? 0) ?></strong>
                </a>
                <a class="cctv-supervision-stat" href="<?= e(url('/cctv/log?' . http_build_query(array_merge($logTodayQuery, ['contact_type' => 'carabineros'])))) ?>">
                    <span class="cctv-supervision-stat__label">Comunicaciones a Carabineros</span>
                    <strong class="cctv-supervision-stat__value"><?= (int) ($todayStats['police_communications'] ?? 0) ?></strong>
                </a>
                <a class="cctv-supervision-stat" href="<?= e(url('/cctv/log?' . http_build_query($logTodayQuery))) ?>">
                    <span class="cctv-supervision-stat__label">Registros hoy</span>
                    <strong class="cctv-supervision-stat__value"><?= (int) ($todayStats['total_entries'] ?? 0) ?></strong>
                </a>
                <a class="cctv-supervision-stat" href="<?= e(url('/cctv/cameras?' . http_build_query(['status' => 'con_problemas']))) ?>">
                    <span class="cctv-supervision-stat__label">Cámaras con problemas</span>
                    <strong class="cctv-supervision-stat__value"><?= (int) ($todayStats['cameras_with_issues'] ?? 0) ?></strong>
                </a>
            </div>
        </article>
    </div>

    <article class="page-card cctv-supervision__panel cctv-supervision__response">
        <div class="cctv-supervision__panel-head">
            <h4 class="cctv-supervision__panel-title mb-0">Tiempo de respuesta Carabineros</h4>
            <span class="cctv-supervision__date"><?= e($monthLabel) ?></span>
        </div>
        <p class="cctv-supervision__note mb-2">
            Llegada registrada menos aviso a Carabineros (o hora del suceso si no hay aviso).
        </p>
        <div class="cctv-supervision__stats cctv-supervision__stats--response">
            <div class="cctv-supervision-stat cctv-supervision-stat--static">
                <span class="cctv-supervision-stat__label">Casos calculables</span>
                <strong class="cctv-supervision-stat__value"><?= (int) ($policeResponse['eligible_count'] ?? 0) ?></strong>
            </div>
            <div class="cctv-supervision-stat cctv-supervision-stat--static">
                <span class="cctv-supervision-stat__label">Promedio</span>
                <strong class="cctv-supervision-stat__value"><?= e((string) ($policeResponse['average_label'] ?? '—')) ?></strong>
            </div>
            <div class="cctv-supervision-stat cctv-supervision-stat--static">
                <span class="cctv-supervision-stat__label">Mínimo</span>
                <strong class="cctv-supervision-stat__value"><?= e((string) ($policeResponse['min_label'] ?? '—')) ?></strong>
            </div>
            <div class="cctv-supervision-stat cctv-supervision-stat--static">
                <span class="cctv-supervision-stat__label">Máximo</span>
                <strong class="cctv-supervision-stat__value"><?= e((string) ($policeResponse['max_label'] ?? '—')) ?></strong>
            </div>
        </div>
        <?php if ((int) ($policeResponse['eligible_count'] ?? 0) === 0): ?>
            <p class="cctv-supervision__empty mb-0">No hay incidentes del mes con llegada de Carabineros y hora informada.</p>
        <?php else: ?>
            <p class="cctv-supervision__note mb-0">
                <?php
                $fromContact = (int) (($policeResponse['notification_sources']['carabineros_contact'] ?? 0));
                $fromIncident = (int) (($policeResponse['notification_sources']['incident_occurred_at'] ?? 0));
                ?>
                Referencia de inicio: <?= $fromContact ?> con aviso a Carabineros, <?= $fromIncident ?> con hora del suceso.
                <?php if (!empty($policeResponse['filter_url'])): ?>
                    <a class="cctv-supervision__panel-link ms-1" href="<?= e((string) $policeResponse['filter_url']) ?>">Ver incidentes</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </article>

    <div class="cctv-supervision__grid cctv-supervision__grid--analysis">
        <article class="page-card cctv-supervision__panel">
            <div class="cctv-supervision__panel-head">
                <h4 class="cctv-supervision__panel-title mb-0">Incidentes por sector</h4>
                <span class="cctv-supervision__date"><?= e($monthLabel) ?></span>
            </div>
            <?php if ($incidentsBySector === []): ?>
                <p class="cctv-supervision__empty mb-0">Sin incidentes registrados en el mes.</p>
            <?php else: ?>
                <ol class="cctv-supervision__breakdown">
                    <?php foreach ($incidentsBySector as $item): ?>
                        <li class="cctv-supervision__breakdown-item">
                            <a class="cctv-supervision__breakdown-link" href="<?= e((string) ($item['url'] ?? '#')) ?>">
                                <span><?= e((string) ($item['label'] ?? '—')) ?></span>
                                <strong><?= (int) ($item['count'] ?? 0) ?></strong>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </article>

        <article class="page-card cctv-supervision__panel">
            <div class="cctv-supervision__panel-head">
                <h4 class="cctv-supervision__panel-title mb-0">Incidentes por tipo</h4>
                <span class="cctv-supervision__date"><?= e($monthLabel) ?></span>
            </div>
            <?php if ($incidentsByType === []): ?>
                <p class="cctv-supervision__empty mb-0">Sin incidentes registrados en el mes.</p>
            <?php else: ?>
                <ol class="cctv-supervision__breakdown">
                    <?php foreach ($incidentsByType as $item): ?>
                        <li class="cctv-supervision__breakdown-item">
                            <a class="cctv-supervision__breakdown-link" href="<?= e((string) ($item['url'] ?? '#')) ?>">
                                <span><?= e((string) ($item['label'] ?? '—')) ?></span>
                                <strong><?= (int) ($item['count'] ?? 0) ?></strong>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </article>
    </div>

    <article class="page-card cctv-supervision__panel cctv-supervision__shifts">
        <div class="cctv-supervision__panel-head">
            <h4 class="cctv-supervision__panel-title mb-0">Registros por turno</h4>
            <a class="cctv-supervision__panel-link" href="<?= e(url('/cctv/shifts')) ?>">Ver historial</a>
        </div>
        <?php if ($shiftsActivity === []): ?>
            <p class="cctv-supervision__empty mb-0">Aún no hay turnos registrados.</p>
        <?php else: ?>
            <div class="cctv-supervision__shift-table-wrap">
                <table class="cctv-supervision__shift-table">
                    <thead>
                        <tr>
                            <th scope="col">Fecha</th>
                            <th scope="col">Operador</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Registros</th>
                            <th scope="col">Incidentes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shiftsActivity as $shift): ?>
                            <tr>
                                <td>
                                    <a href="<?= e((string) ($shift['url'] ?? '#')) ?>"><?= e((string) ($shift['shift_date_label'] ?? '—')) ?></a>
                                </td>
                                <td><?= e((string) ($shift['operator_label'] ?? '—')) ?></td>
                                <td><?= e((string) ($shift['status_label'] ?? '—')) ?></td>
                                <td><?= (int) ($shift['total_entries'] ?? 0) ?></td>
                                <td><?= (int) ($shift['incidents'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>

    <article class="page-card cctv-supervision__recent">
        <div class="cctv-supervision__panel-head">
            <h4 class="cctv-supervision__panel-title mb-0">Últimas novedades</h4>
            <?php if (!empty($canViewLog)): ?>
                <a class="cctv-supervision__panel-link" href="<?= e(url('/cctv/log?' . http_build_query($logTodayQuery))) ?>">Ver bitácora de hoy</a>
            <?php endif; ?>
        </div>

        <?php if ($recentEntries === []): ?>
            <p class="cctv-supervision__empty mb-0">Aún no hay registros recientes.</p>
        <?php else: ?>
            <div class="cctv-supervision__recent-list">
                <?php foreach ($recentEntries as $item): ?>
                    <article class="cctv-supervision__recent-item">
                        <div class="cctv-supervision__recent-head">
                            <time class="cctv-shift-log__time"><?= e((string) ($item['event_time_formatted'] ?? '—')) ?></time>
                            <span class="camera-device-badge camera-device-badge--<?= e((string) ($item['log_type_tone'] ?? 'other')) ?>">
                                <?= e((string) ($item['type_label'] ?? 'REGISTRO')) ?>
                            </span>
                            <span class="cctv-supervision__recent-operator"><?= e((string) ($item['operator_label'] ?? '—')) ?></span>
                        </div>
                        <p class="cctv-supervision__recent-summary mb-0"><?= e((string) ($item['summary'] ?? $item['observations'] ?? '—')) ?></p>
                        <?php if (!empty($canViewLog) && !empty($item['id'])): ?>
                            <a class="cctv-supervision__recent-link" href="<?= e(url('/cctv/log/' . (int) $item['id'])) ?>">Ver detalle</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>
