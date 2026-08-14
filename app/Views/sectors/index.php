<section class="page-toolbar">
    <div>
        <h2 class="page-card__title mb-1">Sectores</h2>
        <p class="text-secondary mb-0">Catálogo territorial usado en cámaras, bitácora e incidentes CCTV.</p>
    </div>
    <?php if (hasPermission('sectors.create')): ?>
        <a href="<?= e(url('/sectors/create')) ?>" class="btn btn-navy">Nuevo sector</a>
    <?php endif; ?>
</section>

<div class="page-card">
    <?php if (($sectors ?? []) === []): ?>
        <p class="text-secondary mb-0">No hay sectores registrados. Cree el primero para usarlo en CCTV.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Identificador</th>
                        <th>Orden</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sectors as $item): ?>
                        <tr>
                            <td>
                                <strong><?= e($item['name']) ?></strong>
                                <?php if (!empty($item['description'])): ?>
                                    <div class="text-secondary"><?= e((string) $item['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><code><?= e($item['slug']) ?></code></td>
                            <td><?= (int) ($item['sort_order'] ?? 0) ?></td>
                            <td>
                                <span class="status-pill <?= !empty($item['is_active']) ? 'is-on' : 'is-off' ?>">
                                    <?= !empty($item['is_active']) ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if (hasPermission('sectors.update')): ?>
                                    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/sectors/' . $item['id'] . '/edit')) ?>">Editar</a>
                                <?php endif; ?>
                                <?php if (hasPermission('sectors.delete')): ?>
                                    <form method="post" action="<?= e(url('/sectors/' . $item['id'])) ?>" class="d-inline" data-confirm="Esta acción da de baja el sector." data-confirm-title="Eliminar sector">
                                        <?= csrf_field() ?>
                                        <?= method_field('DELETE') ?>
                                        <button type="submit" class="btn btn-outline-navy btn-sm">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
