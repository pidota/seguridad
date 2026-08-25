<?php
$filters = $filters ?? [];
$query = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Guardias Municipales</p>
        <h2 class="page-card__title mb-1">Historial de turnos</h2>
        <p class="text-secondary mb-0"><?= (int) ($total ?? 0) ?> turnos registrados</p>
    </div>
    <?php if (hasPermission('guards.shifts.create')): ?>
        <a href="<?= e(url('/guards/shifts/create')) ?>" class="btn btn-navy">Iniciar turno</a>
    <?php endif; ?>
</section>

<?= guards_nav($guardsNav ?? []) ?>

<form method="get" action="<?= e(url('/guards/shifts')) ?>" class="page-card mb-3">
    <div class="guards-shift-filters__grid">
        <div>
            <label class="form-label" for="filter_date_from">Fecha desde</label>
            <input type="date" class="form-control" id="filter_date_from" name="date_from" value="<?= e((string) ($filters['date_from'] ?? '')) ?>">
        </div>
        <div>
            <label class="form-label" for="filter_date_to">Fecha hasta</label>
            <input type="date" class="form-control" id="filter_date_to" name="date_to" value="<?= e((string) ($filters['date_to'] ?? '')) ?>">
        </div>
        <div>
            <label class="form-label" for="filter_status">Estado</label>
            <select class="form-select" id="filter_status" name="status">
                <option value="">Todos</option>
                <?php foreach ($statuses ?? [] as $status): ?>
                    <option value="<?= e((string) ($status['value'] ?? '')) ?>" <?= (string) ($filters['status'] ?? '') === (string) ($status['value'] ?? '') ? 'selected' : '' ?>>
                        <?= e((string) ($status['label'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (!empty($canViewAll)): ?>
            <div>
                <label class="form-label" for="filter_guard">Guardia</label>
                <select class="form-select" id="filter_guard" name="guard_id">
                    <option value="">Todos</option>
                    <?php foreach ($guards ?? [] as $guard): ?>
                        <option value="<?= e((string) ($guard['id'] ?? '')) ?>" <?= (string) ($filters['guard_id'] ?? '') === (string) ($guard['id'] ?? '') ? 'selected' : '' ?>>
                            <?= e((string) ($guard['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-navy">Filtrar</button>
        <?php if ($query !== []): ?>
            <a href="<?= e(url('/guards/shifts')) ?>" class="btn btn-outline-navy">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="page-card">
    <?php if (($shifts ?? []) === []): ?>
        <p class="text-secondary mb-0">No hay turnos que coincidan con los filtros.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Guardia</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Estado</th>
                        <th>Novedades</th>
                        <th>Incidencias</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shifts as $shift): ?>
                        <tr>
                            <td><?= e((string) ($shift['shift_date_formatted'] ?? '—')) ?></td>
                            <td><?= e((string) ($shift['guard_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($shift['started_time_formatted'] ?? '—')) ?></td>
                            <td><?= e((string) ($shift['ended_time_formatted'] ?? '—')) ?></td>
                            <td>
                                <span class="camera-device-badge camera-device-badge--<?= e((string) ($shift['status_tone'] ?? 'other')) ?>">
                                    <?= e((string) ($shift['status_label'] ?? '—')) ?>
                                </span>
                            </td>
                            <td><?= (int) ($shift['total_entries'] ?? 0) ?></td>
                            <td><?= (int) ($shift['incidents'] ?? 0) ?></td>
                            <td><a href="<?= e(url('/guards/shifts/' . (int) ($shift['id'] ?? 0))) ?>">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= component('pagination', [
            'page' => (int) ($page ?? 1),
            'pages' => (int) ($pages ?? 1),
            'baseUrl' => url('/guards/shifts'),
            'query' => $query,
        ]) ?>
    <?php endif; ?>
</div>
