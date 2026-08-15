<?php

$tab = ($tab ?? 'visits') === 'recordings' ? 'recordings' : 'visits';

?>
<section class="page-toolbar">
    <div>
        <h2 class="page-card__title mb-1">Visitas y Solicitudes</h2>
        <p class="text-secondary mb-0">Registro de visitas a la oficina CCTV y solicitudes de grabación.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-navy" href="<?= e(url('/cctv/visits/search-rut')) ?>">Buscar por RUT</a>
        <?php if (hasPermission('cctv.visits.create')): ?>
            <a class="btn btn-navy" href="<?= e(url('/cctv/visits/create')) ?>">Registrar Visita / Solicitud</a>
        <?php endif; ?>
    </div>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'visits' ? 'active' : '' ?>" href="<?= e(url('/cctv/visits')) ?>">Visitas</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'recordings' ? 'active' : '' ?>" href="<?= e(url('/cctv/visits?tab=recordings')) ?>">Solicitudes de Grabación</a>
    </li>
</ul>

<div class="page-card mb-3">
    <form method="get" action="<?= e(url('/cctv/visits')) ?>" class="row g-3">
        <?php if ($tab === 'recordings'): ?>
            <input type="hidden" name="tab" value="recordings">
        <?php endif; ?>
        <div class="col-md-3">
            <label class="form-label" for="date_from">Desde</label>
            <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e((string) ($filters['date_from'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="date_to">Hasta</label>
            <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e((string) ($filters['date_to'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="requester_name">Nombre</label>
            <input type="text" class="form-control" id="requester_name" name="requester_name" value="<?= e((string) ($filters['requester_name'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="requester_rut">RUT</label>
            <input type="text" class="form-control" id="requester_rut" name="requester_rut" value="<?= e((string) ($filters['requester_rut'] ?? '')) ?>">
        </div>
        <?php if ($tab === 'visits'): ?>
            <div class="col-md-3">
                <label class="form-label" for="visitor_type">Tipo</label>
                <select class="form-select" id="visitor_type" name="visitor_type">
                    <option value="">Todos</option>
                    <?php foreach ($visitorTypes ?? [] as $option): ?>
                        <option value="<?= e((string) $option['value']) ?>" <?= (string) ($filters['visitor_type'] ?? '') === (string) $option['value'] ? 'selected' : '' ?>>
                            <?= e((string) $option['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <div class="col-md-3">
                <label class="form-label" for="q">Búsqueda global</label>
                <input type="text" class="form-control" id="q" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="N.º, RUT, nombre, denuncia">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="status">Estado</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos</option>
                    <?php foreach ($statusOptions ?? [] as $option): ?>
                        <option value="<?= e((string) $option['value']) ?>" <?= (string) ($filters['status'] ?? '') === (string) $option['value'] ? 'selected' : '' ?>>
                            <?= e((string) $option['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-12">
            <button type="submit" class="btn btn-navy">Filtrar</button>
            <a class="btn btn-outline-navy" href="<?= e(url('/cctv/visits' . ($tab === 'recordings' ? '?tab=recordings' : ''))) ?>">Limpiar</a>
        </div>
    </form>
</div>

<div class="page-card">
    <?php if (($items ?? []) === []): ?>
        <p class="text-secondary mb-0">No hay registros para los filtros seleccionados.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <?php if ($tab === 'recordings'): ?>
                            <th>N.º solicitud</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Solicitante</th>
                            <th>Fecha hecho</th>
                            <th>Sector</th>
                            <th>Denuncia</th>
                            <th>Estado</th>
                            <th>Operador</th>
                            <th></th>
                        <?php else: ?>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Persona</th>
                            <th>RUT</th>
                            <th>Tipo</th>
                            <th>Motivo</th>
                            <th>Operador</th>
                            <th>Estado</th>
                            <th></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <?php if ($tab === 'recordings'): ?>
                                <td><code><?= e((string) ($item['request_number'] ?? '—')) ?></code></td>
                                <td><?= e(date('d/m/Y', strtotime((string) ($item['visit_date'] ?? '')))) ?></td>
                                <td><?= e(substr((string) ($item['arrival_time'] ?? ''), 0, 5)) ?></td>
                                <td><?= e((string) ($item['requester_name'] ?? '—')) ?></td>
                                <td><?= e(date('d/m/Y', strtotime((string) ($item['incident_date'] ?? '')))) ?></td>
                                <td><?= e((string) ($item['sector_name'] ?? '—')) ?></td>
                                <td><?= !empty($item['has_complaint']) ? 'Sí' : 'No' ?></td>
                                <td>
                                    <span class="senda-badge senda-badge--<?= e((string) ($item['status_tone'] ?? 'attention')) ?>">
                                        <?= e((string) ($item['status_label'] ?? '—')) ?>
                                    </span>
                                </td>
                                <td><?= e((string) ($item['operator_name'] ?? '—')) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/cctv/recording-requests/' . ($item['id'] ?? ''))) ?>">Ver</a>
                                </td>
                            <?php else: ?>
                                <td><?= e(date('d/m/Y', strtotime((string) ($item['visit_date'] ?? '')))) ?></td>
                                <td><?= e(substr((string) ($item['arrival_time'] ?? ''), 0, 5)) ?></td>
                                <td><?= e((string) ($item['requester_name'] ?? '—')) ?></td>
                                <td><?= e((string) ($item['requester_rut'] ?? '—')) ?></td>
                                <td><?= e((string) ($item['visitor_type_label'] ?? '—')) ?></td>
                                <td><?= e(mb_strimwidth((string) ($item['reason'] ?? ''), 0, 80, '…')) ?></td>
                                <td><?= e((string) ($item['operator_name'] ?? '—')) ?></td>
                                <td>
                                    <?php if (!empty($item['recording_status_label'])): ?>
                                        <span class="senda-badge senda-badge--<?= e((string) ($item['recording_status_tone'] ?? 'attention')) ?>">
                                            <?= e((string) $item['recording_status_label']) ?>
                                        </span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/cctv/visits/' . ($item['id'] ?? ''))) ?>">Ver</a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
