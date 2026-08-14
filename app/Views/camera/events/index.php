<?php
$filters = $filters ?? [];
$query = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');
$hasFilters = $query !== [];
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Central de Cámaras</p>
        <h2 class="page-card__title mb-1">Bitácora histórica</h2>
        <p class="text-secondary mb-0"><?= (int) ($total ?? 0) ?> registros autorizados</p>
    </div>
    <?php if (!empty($canRegisterEntry)): ?>
        <a href="<?= e(url('/cctv/log/create')) ?>" class="btn btn-navy">+ Nueva Novedad</a>
    <?php endif; ?>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<form method="get" action="<?= e(url('/cctv/log')) ?>" class="page-card camera-filters mb-3">
    <div class="camera-filters__grid">
        <div class="camera-filters__field--wide">
            <label class="form-label" for="filter_q">Buscar en observaciones</label>
            <input
                class="form-control"
                id="filter_q"
                name="q"
                value="<?= e((string) ($filters['q'] ?? '')) ?>"
                placeholder="Mínimo 2 caracteres"
                maxlength="200"
            >
        </div>
        <div>
            <label class="form-label" for="filter_date_from">Fecha desde</label>
            <input
                type="date"
                class="form-control"
                id="filter_date_from"
                name="date_from"
                value="<?= e((string) ($filters['date_from'] ?? '')) ?>"
            >
        </div>
        <div>
            <label class="form-label" for="filter_date_to">Fecha hasta</label>
            <input
                type="date"
                class="form-control"
                id="filter_date_to"
                name="date_to"
                value="<?= e((string) ($filters['date_to'] ?? '')) ?>"
            >
        </div>
        <?php if (!empty($canViewAll)): ?>
            <div>
                <label class="form-label" for="filter_created_by">Operador</label>
                <select class="form-select" id="filter_created_by" name="created_by">
                    <option value="">Todos</option>
                    <?php foreach ($operators ?? [] as $user): ?>
                        <option
                            value="<?= e((string) ($user['id'] ?? '')) ?>"
                            <?= (string) ($filters['created_by'] ?? '') === (string) ($user['id'] ?? '') ? 'selected' : '' ?>
                        >
                            <?= e((string) ($user['name'] ?? '—')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div>
            <label class="form-label" for="filter_log_type">Tipo de registro</label>
            <select class="form-select" id="filter_log_type" name="log_type">
                <option value="">Todos</option>
                <?php foreach ($logTypes ?? [] as $option): ?>
                    <option
                        value="<?= e((string) ($option['value'] ?? '')) ?>"
                        <?= ($filters['log_type'] ?? '') === ($option['value'] ?? '') ? 'selected' : '' ?>
                    >
                        <?= e((string) ($option['label'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="filter_incident_type">Tipo de incidente</label>
            <select class="form-select" id="filter_incident_type" name="incident_type">
                <option value="">Todos</option>
                <?php foreach ($incidentTypes ?? [] as $option): ?>
                    <option
                        value="<?= e((string) ($option['value'] ?? '')) ?>"
                        <?= ($filters['incident_type'] ?? '') === ($option['value'] ?? '') ? 'selected' : '' ?>
                    >
                        <?= e((string) ($option['label'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="filter_sector">Sector</label>
            <select class="form-select" id="filter_sector" name="sector_id">
                <option value="">Todos</option>
                <?php foreach ($sectors ?? [] as $sector): ?>
                    <option
                        value="<?= e((string) ($sector['id'] ?? '')) ?>"
                        <?= (string) ($filters['sector_id'] ?? '') === (string) ($sector['id'] ?? '') ? 'selected' : '' ?>
                    >
                        <?= e((string) ($sector['name'] ?? '—')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="filter_camera">Cámara</label>
            <select class="form-select" id="filter_camera" name="camera_id">
                <option value="">Todas</option>
                <?php foreach ($cameras ?? [] as $camera): ?>
                    <option
                        value="<?= e((string) ($camera['id'] ?? '')) ?>"
                        <?= (string) ($filters['camera_id'] ?? '') === (string) ($camera['id'] ?? '') ? 'selected' : '' ?>
                    >
                        <?= e((string) ($camera['label'] ?? '—')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="filter_contact_type">Institución contactada</label>
            <select class="form-select" id="filter_contact_type" name="contact_type">
                <option value="">Todas</option>
                <?php foreach ($contactTypes ?? [] as $option): ?>
                    <option
                        value="<?= e((string) ($option['value'] ?? '')) ?>"
                        <?= ($filters['contact_type'] ?? '') === ($option['value'] ?? '') ? 'selected' : '' ?>
                    >
                        <?= e((string) ($option['label'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="filter_police">Llegó Carabineros</label>
            <select class="form-select" id="filter_police" name="police_arrived">
                <option value="">Todos</option>
                <?php foreach ($policeOptions ?? [] as $option): ?>
                    <option
                        value="<?= e((string) ($option['value'] ?? '')) ?>"
                        <?= ($filters['police_arrived'] ?? '') === ($option['value'] ?? '') ? 'selected' : '' ?>
                    >
                        <?= e((string) ($option['label'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="filter_status">Estado</label>
            <select class="form-select" id="filter_status" name="status">
                <option value="">Todos</option>
                <?php foreach ($statuses ?? [] as $option): ?>
                    <option
                        value="<?= e((string) ($option['value'] ?? '')) ?>"
                        <?= ($filters['status'] ?? '') === ($option['value'] ?? '') ? 'selected' : '' ?>
                    >
                        <?= e((string) ($option['label'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="camera-filters__actions">
        <button class="btn btn-navy" type="submit">Filtrar</button>
        <?php if ($hasFilters): ?>
            <a class="btn btn-outline-navy" href="<?= e(url('/cctv/log')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="page-card">
    <div class="table-responsive">
        <table class="data-table cctv-log-history">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Operador</th>
                    <th>Tipo</th>
                    <th>Incidente</th>
                    <th>Sector</th>
                    <th>Cámara</th>
                    <th>Coordinaciones</th>
                    <th>Llegó Carabineros</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (($entries ?? []) === []): ?>
                    <tr>
                        <td colspan="10" class="text-secondary">
                            No hay registros para mostrar con los filtros seleccionados.
                            <?php if (!empty($canRegisterEntry)): ?>
                                <a href="<?= e(url('/cctv/log/create')) ?>">Registrar uno</a>.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($entries as $item): ?>
                        <tr>
                            <td><?= e((string) ($item['event_date_formatted'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['event_time_formatted'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['operator_label'] ?? '—')) ?></td>
                            <td>
                                <span class="camera-device-badge camera-device-badge--<?= e((string) ($item['log_type_tone'] ?? 'other')) ?>">
                                    <?= e((string) ($item['type_label'] ?? '—')) ?>
                                </span>
                            </td>
                            <td><?= e((string) ($item['incident_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['sector_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['camera_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['coordination_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['police_label'] ?? '—')) ?></td>
                            <td class="text-end text-nowrap">
                                <?= \Core\View::make('camera/events/actions', ['item' => $item], null) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= component('pagination', [
        'page' => $page ?? 1,
        'pages' => $pages ?? 1,
        'base' => url('/cctv/log'),
        'query' => $query,
    ]) ?>
</div>
