<?php
$filters = $filters ?? [];
$query = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');
$hasFilters = $query !== [];
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Central de Cámaras</p>
        <h2 class="page-card__title mb-1">Historial de turnos</h2>
        <p class="text-secondary mb-0"><?= (int) ($total ?? 0) ?> turnos registrados</p>
    </div>
    <?php if (hasPermission('cctv.shifts.create')): ?>
        <a href="<?= e(url('/cctv/shifts/create')) ?>" class="btn btn-navy">Iniciar turno</a>
    <?php endif; ?>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<form method="get" action="<?= e(url('/cctv/shifts')) ?>" class="page-card camera-filters mb-3">
    <div class="camera-filters__grid">
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
                <label class="form-label" for="filter_operator">Operador</label>
                <select class="form-select" id="filter_operator" name="operator_id">
                    <option value="">Todos</option>
                    <?php foreach ($operators ?? [] as $operator): ?>
                        <option
                            value="<?= e((string) ($operator['id'] ?? '')) ?>"
                            <?= (string) ($filters['operator_id'] ?? '') === (string) ($operator['id'] ?? '') ? 'selected' : '' ?>
                        >
                            <?= e((string) ($operator['name'] ?? '—')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
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
            <a class="btn btn-outline-navy" href="<?= e(url('/cctv/shifts')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="page-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Operador</th>
                    <th>Inicio</th>
                    <th>Término</th>
                    <th>Duración</th>
                    <th>Registros</th>
                    <th>Incidentes</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (($shifts ?? []) === []): ?>
                    <tr>
                        <td colspan="9" class="text-secondary">No hay turnos para mostrar con los filtros seleccionados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($shifts as $item): ?>
                        <tr>
                            <td><?= e((string) ($item['shift_date_formatted'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['operator_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['started_time_formatted'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['ended_time_formatted'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['duration_label'] ?? '—')) ?></td>
                            <td><?= (int) ($item['total_entries'] ?? 0) ?></td>
                            <td><?= (int) ($item['incidents'] ?? 0) ?></td>
                            <td>
                                <span class="camera-device-badge camera-device-badge--<?= e((string) ($item['status_tone'] ?? 'other')) ?>">
                                    <?= e((string) ($item['status_label'] ?? '—')) ?>
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                <?= \Core\View::make('camera/shifts/_actions', [
                                    'item' => $item,
                                    'canViewLog' => !empty($canViewLog),
                                ], null) ?>
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
        'base' => url('/cctv/shifts'),
        'query' => $query,
    ]) ?>
</div>
