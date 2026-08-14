<section class="page-toolbar">
    <div>
        <h2 class="page-card__title mb-1">Auditoría</h2>
        <p class="text-secondary mb-0">Bitácora inmutable. Estos registros no se pueden editar ni eliminar.</p>
    </div>
</section>

<form method="get" action="<?= e(url('/audit')) ?>" class="filter-bar page-card mb-3">
    <input type="search" name="q" class="form-control" value="<?= e((string) $filters['q']) ?>" placeholder="Usuario, recurso o ID">
    <select name="module" class="form-select">
        <option value="">Todos los módulos</option>
        <?php foreach ($modules as $module): ?>
            <option value="<?= e($module) ?>" <?= $filters['module'] === $module ? 'selected' : '' ?>><?= e(permission_module_label($module)) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="action" class="form-select">
        <option value="">Todas las acciones</option>
        <?php foreach ($actions ?? [] as $action => $label): ?>
            <option value="<?= e((string) $action) ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-navy" type="submit">Filtrar</button>
</form>

<div class="page-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Módulo</th>
                    <th>Recurso</th>
                    <th>ID</th>
                    <th>IP</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $item): ?>
                    <tr>
                        <td><?= e(date('d-m-Y H:i:s', strtotime($item['created_at']))) ?></td>
                        <td><?= e((string) ($item['user_name'] ?? 'Sistema')) ?></td>
                        <td><span class="role-badge"><?= e(audit_action_label((string) $item['action'])) ?></span></td>
                        <td><?= e(permission_module_label($item['module'])) ?></td>
                        <td><?= e(audit_resource_label(isset($item['resource']) ? (string) $item['resource'] : null)) ?></td>
                        <td><?= e((string) ($item['resource_id'] ?? '—')) ?></td>
                        <td><?= e((string) ($item['ip_address'] ?? '—')) ?></td>
                        <td class="text-end">
                            <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/audit/' . $item['id'])) ?>">Ver</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($logs === []): ?>
                    <tr><td colspan="8" class="text-secondary">No hay registros de auditoría.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= component('pagination', [
        'page' => $page,
        'pages' => $pages,
        'base' => url('/audit'),
        'query' => array_filter($filters),
    ]) ?>
</div>
