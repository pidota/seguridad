<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-0">Personas</h2>
    </div>
    <div class="page-toolbar__actions">
        <form method="get" action="<?= e(url('/senda/people')) ?>" class="search-inline">
            <input type="search" name="q" value="<?= e((string) ($search ?? '')) ?>" class="form-control" placeholder="Buscar RUT o nombre">
            <button class="btn btn-outline-navy" type="submit">Buscar</button>
        </form>
        <?php if (hasPermission('senda.people.create')): ?>
            <a href="<?= e(url('/senda/people/create')) ?>" class="btn btn-navy">Nueva persona</a>
        <?php endif; ?>
    </div>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<div class="page-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>RUT</th>
                    <th>Edad</th>
                    <th>Teléfono</th>
                    <th>Atenciones</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($people === []): ?>
                    <tr>
                        <td colspan="6" class="text-secondary">No hay personas registradas.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($people as $item): ?>
                        <tr>
                            <td><?= e((string) $item['full_name']) ?></td>
                            <td><?= e((string) $item['rut']) ?></td>
                            <td><?= $item['age'] !== null ? e((string) $item['age']) . ' años' : '—' ?></td>
                            <td><?= e((string) ($item['phone'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['attentions_count'] ?? '0')) ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/senda/people/' . $item['id'])) ?>">Ver</a>
                                <?php if (hasPermission('senda.people.edit')): ?>
                                    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/senda/people/' . $item['id'] . '/edit')) ?>">Editar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
