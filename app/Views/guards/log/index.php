<?php
$filters = $filters ?? [];
$query = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Guardias Municipales</p>
        <h2 class="page-card__title mb-1">Bitácora de terreno</h2>
        <p class="text-secondary mb-0"><?= (int) ($total ?? 0) ?> novedades registradas</p>
    </div>
    <?php if (!empty($canCreate)): ?>
        <a href="<?= e(url('/guards/log/create')) ?>" class="btn btn-navy">+ Registrar novedad</a>
    <?php endif; ?>
</section>

<?= guards_nav($guardsNav ?? []) ?>

<form method="get" action="<?= e(url('/guards/log')) ?>" class="page-card mb-3">
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
            <label class="form-label" for="filter_log_type">Tipo</label>
            <select class="form-select" id="filter_log_type" name="log_type_id">
                <option value="">Todos</option>
                <?php foreach ($logTypes ?? [] as $type): ?>
                    <option value="<?= e((string) ($type['id'] ?? '')) ?>" <?= (string) ($filters['log_type_id'] ?? '') === (string) ($type['id'] ?? '') ? 'selected' : '' ?>>
                        <?= e((string) ($type['name'] ?? '')) ?>
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
            <a href="<?= e(url('/guards/log')) ?>" class="btn btn-outline-navy">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="page-card">
    <?php if (($entries ?? []) === []): ?>
        <p class="text-secondary mb-0">No hay novedades que coincidan con los filtros.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha/hora</th>
                        <th>Guardia</th>
                        <th>Tipo</th>
                        <th>Sector</th>
                        <th>Resumen</th>
                        <th>CCTV</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?= e((string) ($entry['occurred_at_formatted'] ?? '—')) ?></td>
                            <td><?= e((string) ($entry['guard_label'] ?? '—')) ?></td>
                            <td><span class="camera-device-badge camera-device-badge--<?= e((string) ($entry['type_tone'] ?? 'other')) ?>"><?= e((string) ($entry['type_label'] ?? '—')) ?></span></td>
                            <td><?= e((string) ($entry['sector_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($entry['summary'] ?? '—')) ?></td>
                            <td><?= !empty($entry['has_cctv_link']) ? 'Sí' : '—' ?></td>
                            <td><a href="<?= e(url('/guards/log/' . (int) ($entry['id'] ?? 0))) ?>">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= component('pagination', [
            'page' => (int) ($page ?? 1),
            'pages' => (int) ($pages ?? 1),
            'baseUrl' => url('/guards/log'),
            'query' => $query,
        ]) ?>
    <?php endif; ?>
</div>
