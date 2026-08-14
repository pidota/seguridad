<section class="page-toolbar">
    <div>
        <h2 class="page-card__title mb-1">Usuarios</h2>
        <p class="text-secondary mb-0"><?= (int) $total ?> cuentas registradas</p>
    </div>
    <div class="page-toolbar__actions">
        <form method="get" action="<?= e(url('/users')) ?>" class="search-inline">
            <input type="search" name="q" value="<?= e((string) $search) ?>" class="form-control" placeholder="Buscar nombre o correo">
            <button class="btn btn-outline-navy" type="submit">Buscar</button>
        </form>
        <?php if (hasPermission('users.create')): ?>
            <a href="<?= e(url('/users/create')) ?>" class="btn btn-navy">Nuevo usuario</a>
        <?php endif; ?>
    </div>
</section>

<div class="page-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Roles</th>
                    <th>Estado</th>
                    <th>Último acceso</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $item): ?>
                    <tr>
                        <td><?= e($item['name']) ?></td>
                        <td><?= e($item['email']) ?></td>
                        <td>
                            <?php foreach ($item['roles'] as $role): ?>
                                <span class="role-badge"><?= e($role['name']) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <span class="status-pill <?= !empty($item['is_active']) ? 'is-on' : 'is-off' ?>">
                                <?= !empty($item['is_active']) ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td><?= e($item['last_login_at'] ? date('d-m-Y H:i', strtotime($item['last_login_at'])) : '—') ?></td>
                        <td class="text-end text-nowrap">
                            <?php if (hasPermission('users.update')): ?>
                                <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/users/' . $item['id'] . '/edit')) ?>">Editar</a>
                            <?php endif; ?>
                            <?php if (hasPermission('users.delete') && (int) $item['id'] !== (int) (user()['id'] ?? 0)): ?>
                                <form method="post" action="<?= e(url('/users/' . $item['id'])) ?>" class="d-inline" data-confirm="Esta acción cambia el estado de la cuenta." data-confirm-title="Confirmar cambio de estado">
                                    <?= csrf_field() ?>
                                    <?= method_field('DELETE') ?>
                                    <button type="submit" class="btn btn-outline-navy btn-sm">
                                        <?= !empty($item['is_active']) ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($users === []): ?>
                    <tr><td colspan="6" class="text-secondary">No hay usuarios para mostrar.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= component('pagination', ['page' => $page, 'pages' => $pages, 'base' => url('/users'), 'query' => array_filter(['q' => $search])]) ?>
</div>
