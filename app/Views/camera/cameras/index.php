<?php
$filters = $filters ?? [];
$query = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');
$hasFilters = $query !== [];
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Central de Cámaras</p>
        <h2 class="page-card__title mb-1">Inventario de cámaras</h2>
        <p class="text-secondary mb-0">
            <?= (int) ($total ?? 0) ?> cámaras
            <?php if (empty($canManage)): ?>
                activas en operación
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= e(url('/cctv/cameras/map')) ?>" class="btn btn-outline-navy">Ver mapa</a>
        <?php if (!empty($canManage)): ?>
            <a href="<?= e(url('/cctv/cameras/create')) ?>" class="btn btn-navy">Registrar cámara</a>
        <?php endif; ?>
    </div>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<form method="get" action="<?= e(url('/cctv/cameras')) ?>" class="page-card camera-filters mb-3">
    <div class="camera-filters__grid">
        <div>
            <label class="form-label" for="filter_q">Buscar</label>
            <input class="form-control" id="filter_q" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Código, nombre o ubicación">
        </div>
        <div>
            <label class="form-label" for="filter_sector">Sector</label>
            <select class="form-select" id="filter_sector" name="sector_id">
                <option value="">Todos</option>
                <?php foreach ($sectors ?? [] as $sector): ?>
                    <option value="<?= e((string) $sector['id']) ?>" <?= (string) ($filters['sector_id'] ?? '') === (string) $sector['id'] ? 'selected' : '' ?>>
                        <?= e((string) $sector['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="filter_camera_type">Tipo</label>
            <select class="form-select" id="filter_camera_type" name="camera_type">
                <option value="">Todos</option>
                <?php foreach ($cameraTypes ?? [] as $option): ?>
                    <option value="<?= e($option['value']) ?>" <?= ($filters['camera_type'] ?? '') === $option['value'] ? 'selected' : '' ?>>
                        <?= e($option['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="filter_status">Estado</label>
            <select class="form-select" id="filter_status" name="status">
                <option value="">Todos</option>
                <?php foreach ($statuses ?? [] as $option): ?>
                    <option value="<?= e($option['value']) ?>" <?= ($filters['status'] ?? '') === $option['value'] ? 'selected' : '' ?>>
                        <?= e($option['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (!empty($canManage)): ?>
            <div>
                <label class="form-label" for="filter_active">Activa</label>
                <select class="form-select" id="filter_active" name="active">
                    <option value="">Todas</option>
                    <option value="1" <?= ($filters['active'] ?? '') === '1' ? 'selected' : '' ?>>Sí</option>
                    <option value="0" <?= ($filters['active'] ?? '') === '0' ? 'selected' : '' ?>>No</option>
                </select>
            </div>
        <?php endif; ?>
    </div>
    <div class="camera-filters__actions">
        <button class="btn btn-navy" type="submit">Filtrar</button>
        <?php if ($hasFilters): ?>
            <a class="btn btn-outline-navy" href="<?= e(url('/cctv/cameras')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="page-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Sector</th>
                    <th>Ubicación</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <?php if (!empty($canManage)): ?>
                        <th>Activa</th>
                        <th>Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (($cameras ?? []) === []): ?>
                    <tr>
                        <td colspan="<?= !empty($canManage) ? 8 : 6 ?>" class="text-secondary">
                            No hay cámaras para mostrar.
                            <?php if (!empty($canManage)): ?>
                                <a href="<?= e(url('/cctv/cameras/create')) ?>">Registrar una</a>.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($cameras as $item): ?>
                        <tr>
                            <td><strong><?= e((string) $item['code']) ?></strong></td>
                            <td><?= e((string) $item['name']) ?></td>
                            <td><?= e((string) ($item['sector_label'] ?? '—')) ?></td>
                            <td><?= e((string) (($item['location'] ?? '') !== '' ? $item['location'] : '—')) ?></td>
                            <td><?= e((string) ($item['camera_type_label'] ?? '—')) ?></td>
                            <td>
                                <span class="camera-device-badge camera-device-badge--<?= e((string) ($item['status_tone'] ?? 'other')) ?>">
                                    <?= e((string) ($item['status_label'] ?? '—')) ?>
                                </span>
                            </td>
                            <?php if (!empty($canManage)): ?>
                                <td>
                                    <span class="status-pill <?= !empty($item['active']) ? 'is-on' : 'is-off' ?>">
                                        <?= e((string) ($item['active_label'] ?? '—')) ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/cctv/cameras/' . $item['id'] . '/edit')) ?>">Editar</a>
                                    <form method="post" action="<?= e(url('/cctv/cameras/' . $item['id'])) ?>" class="d-inline" data-confirm="Esta cámara se dará de baja del inventario." data-confirm-title="Confirmar baja">
                                        <?= csrf_field() ?>
                                        <?= method_field('DELETE') ?>
                                        <button type="submit" class="btn btn-outline-navy btn-sm">Eliminar</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= component('pagination', [
        'page' => $page ?? 1,
        'pages' => $pages ?? 1,
        'base' => url('/cctv/cameras'),
        'query' => $query,
    ]) ?>
</div>
