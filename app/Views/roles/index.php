<section class="page-toolbar">
    <div>
        <h2 class="page-card__title mb-1">Roles</h2>
        <p class="text-secondary mb-0">Los roles de sistema no se eliminan. Un rol concentra uno o más permisos.</p>
    </div>
    <?php if (hasPermission('roles.create')): ?>
        <a href="<?= e(url('/roles/create')) ?>" class="btn btn-navy">Nuevo rol</a>
    <?php endif; ?>
</section>

<div class="page-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Identificador</th>
                    <th>Tipo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $item): ?>
                    <tr>
                        <td>
                            <strong><?= e($item['name']) ?></strong>
                            <div class="text-secondary"><?= e((string) ($item['description'] ?? '')) ?></div>
                        </td>
                        <td><code><?= e($item['slug']) ?></code></td>
                        <td><?= !empty($item['is_system']) ? 'Sistema' : 'Personalizado' ?></td>
                        <td class="text-end text-nowrap">
                            <?php if (hasPermission('roles.update')): ?>
                                <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/roles/' . $item['id'] . '/edit')) ?>">Editar</a>
                            <?php endif; ?>
                            <?php if (hasPermission('roles.delete') && empty($item['is_system'])): ?>
                                <form method="post" action="<?= e(url('/roles/' . $item['id'])) ?>" class="d-inline" data-confirm="Esta acción elimina el rol personalizado." data-confirm-title="Eliminar rol">
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
</div>
