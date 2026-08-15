<?php

$visitsDashboard = $visitsDashboard ?? null;

if ($visitsDashboard === null) {
    return;
}

?>
<section class="cctv-visits-dashboard mb-3" id="visitas-solicitudes">
    <div class="cctv-supervision__header">
        <div>
            <p class="welcome-kicker mb-1">Oficina CCTV</p>
            <h3 class="cctv-supervision__title mb-0">Visitas y Solicitudes</h3>
        </div>
        <?php if (!empty($canCreateVisit)): ?>
            <a class="btn btn-navy btn-sm" href="<?= e(url('/cctv/visits/create')) ?>">Registrar Visita / Solicitud</a>
        <?php endif; ?>
    </div>

    <?php if (($visitsDashboard['current_visits_count'] ?? 0) > 0): ?>
        <article class="page-card mb-3">
            <h4 class="cctv-supervision__panel-title mb-2">Personas actualmente en la oficina</h4>
            <p class="mb-2"><strong><?= (int) ($visitsDashboard['current_visits_count'] ?? 0) ?> visitas actualmente</strong></p>
            <ul class="cctv-visits-alerts mb-0">
                <?php foreach ($visitsDashboard['current_visits'] ?? [] as $visit): ?>
                    <li>
                        <a href="<?= e(url('/cctv/visits/' . ($visit['id'] ?? ''))) ?>">
                            <?= e((string) ($visit['requester_name'] ?? '—')) ?> — ingreso <?= e(substr((string) ($visit['arrival_time'] ?? ''), 0, 5)) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </article>
    <?php endif; ?>

    <div class="cctv-supervision__grid">
        <article class="page-card cctv-supervision__panel">
            <h4 class="cctv-supervision__panel-title mb-3">Indicadores</h4>
            <div class="cctv-supervision__stats">
                <a class="cctv-supervision-stat" href="<?= e(url('/cctv/visits')) ?>">
                    <span class="cctv-supervision-stat__label">Visitas de hoy</span>
                    <strong class="cctv-supervision-stat__value"><?= (int) ($visitsDashboard['visits_today'] ?? 0) ?></strong>
                </a>
                <a class="cctv-supervision-stat" href="<?= e(url('/cctv/visits?tab=recordings')) ?>">
                    <span class="cctv-supervision-stat__label">Solicitudes de hoy</span>
                    <strong class="cctv-supervision-stat__value"><?= (int) ($visitsDashboard['recording_requests_today'] ?? 0) ?></strong>
                </a>
                <a class="cctv-supervision-stat" href="<?= e(url('/cctv/visits?tab=recordings&status=pending_complaint')) ?>">
                    <span class="cctv-supervision-stat__label">Pendientes de denuncia</span>
                    <strong class="cctv-supervision-stat__value"><?= (int) ($visitsDashboard['pending_complaint'] ?? 0) ?></strong>
                </a>
                <a class="cctv-supervision-stat" href="<?= e(url('/cctv/visits?tab=recordings&status=incomplete_documentation')) ?>">
                    <span class="cctv-supervision-stat__label">Documentación incompleta</span>
                    <strong class="cctv-supervision-stat__value"><?= (int) ($visitsDashboard['incomplete_documentation'] ?? 0) ?></strong>
                </a>
                <a class="cctv-supervision-stat" href="<?= e(url('/cctv/visits?tab=recordings&status=pending_review')) ?>">
                    <span class="cctv-supervision-stat__label">Pendientes de revisión</span>
                    <strong class="cctv-supervision-stat__value"><?= (int) ($visitsDashboard['pending_review'] ?? 0) ?></strong>
                </a>
                <a class="cctv-supervision-stat" href="<?= e(url('/cctv/visits?tab=recordings&status=recording_found')) ?>">
                    <span class="cctv-supervision-stat__label">Grabaciones localizadas</span>
                    <strong class="cctv-supervision-stat__value"><?= (int) ($visitsDashboard['recording_found'] ?? 0) ?></strong>
                </a>
                <a class="cctv-supervision-stat" href="<?= e(url('/cctv/visits?tab=recordings&status=approved')) ?>">
                    <span class="cctv-supervision-stat__label">Autorizadas para entrega</span>
                    <strong class="cctv-supervision-stat__value"><?= (int) ($visitsDashboard['approved_for_delivery'] ?? 0) ?></strong>
                </a>
                <a class="cctv-supervision-stat" href="<?= e(url('/cctv/visits?tab=recordings&status=delivered')) ?>">
                    <span class="cctv-supervision-stat__label">Entregadas hoy</span>
                    <strong class="cctv-supervision-stat__value"><?= (int) ($visitsDashboard['delivered_today'] ?? 0) ?></strong>
                </a>
            </div>
        </article>

        <article class="page-card cctv-supervision__panel">
            <h4 class="cctv-supervision__panel-title mb-3">Solicitudes prioritarias</h4>
            <?php if (($visitsDashboard['pending_alerts'] ?? []) === []): ?>
                <p class="cctv-supervision__empty mb-0">No hay solicitudes prioritarias pendientes.</p>
            <?php else: ?>
                <ul class="cctv-visits-alerts">
                    <?php foreach ($visitsDashboard['pending_alerts'] as $alert): ?>
                        <li>
                            <a href="<?= e(url('/cctv/recording-requests/' . ($alert['id'] ?? ''))) ?>">
                                <strong><?= e((string) ($alert['request_number'] ?? '—')) ?></strong>
                                <span class="senda-badge senda-badge--<?= e((string) ($alert['status_tone'] ?? 'attention')) ?>">
                                    <?= e((string) ($alert['status_label'] ?? '—')) ?>
                                </span>
                                <small>Fecha solicitud: <?= e(date('d/m/Y', strtotime((string) ($alert['visit_date'] ?? date('Y-m-d'))))) ?></small>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (($visitsDashboard['stale_requests'] ?? []) !== []): ?>
                <h5 class="h6 mt-3">Pendientes hace más de <?= (int) ($visitsDashboard['pending_alert_days'] ?? 3) ?> días</h5>
                <ul class="cctv-visits-alerts">
                    <?php foreach ($visitsDashboard['stale_requests'] as $stale): ?>
                        <li>
                            <a href="<?= e(url('/cctv/recording-requests/' . ($stale['id'] ?? ''))) ?>">
                                <strong><?= e((string) ($stale['request_number'] ?? '—')) ?></strong>
                                <small><?= e((string) ($stale['status_label'] ?? '—')) ?></small>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </div>

    <?php if (($visitsDashboard['supervision'] ?? []) !== [] && (hasPermission('cctv.recordings.assign') || hasPermission('cctv.recordings.approve'))): ?>
        <article class="page-card mt-3">
            <h4 class="cctv-supervision__panel-title mb-3">Panel de supervisión</h4>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Solicitud</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Responsable</th>
                            <th>Días pendiente</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visitsDashboard['supervision'] as $row): ?>
                            <tr>
                                <td><a href="<?= e(url('/cctv/recording-requests/' . ($row['id'] ?? ''))) ?>"><?= e((string) ($row['request_number'] ?? '—')) ?></a></td>
                                <td><span class="senda-badge senda-badge--<?= e((string) ($row['status_tone'] ?? 'attention')) ?>"><?= e((string) ($row['status_label'] ?? '—')) ?></span></td>
                                <td><?= e(date('d/m/Y', strtotime((string) ($row['visit_date'] ?? 'now')))) ?></td>
                                <td><?= e((string) ($row['assigned_to_name'] ?? '—')) ?></td>
                                <td><?= (int) ($row['pending_days'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
    <?php endif; ?>
</section>

<section class="camera-dashboard-grid mb-3">
    <a class="camera-stat-card camera-stat-card--link" href="<?= e(url('/cctv/visits')) ?>">
        <p class="camera-stat-card__label">Visitas y Solicitudes</p>
        <p class="camera-stat-card__hint">Registrar personas que visitan la oficina y solicitudes de revisión o entrega de grabaciones CCTV.</p>
        <?php if (!empty($canCreateVisit)): ?>
            <span class="camera-stat-card__action">Registrar Visita / Solicitud <i class="bi bi-arrow-right"></i></span>
        <?php else: ?>
            <span class="camera-stat-card__action">Ver listado <i class="bi bi-arrow-right"></i></span>
        <?php endif; ?>
    </a>
</section>
