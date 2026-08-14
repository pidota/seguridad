<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-1">Estadísticas</h2>
        <p class="text-secondary mb-0">Agregaciones calculadas desde MySQL. Cada tabla corresponde a un indicador operativo.</p>
    </div>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<?php if (($tables ?? []) === []): ?>
    <div class="page-card">
        <p class="mb-0 text-secondary">No hay indicadores disponibles.</p>
    </div>
<?php else: ?>
    <section class="senda-stats-grid" aria-label="Indicadores estadísticos SENDA">
        <?php foreach ($tables as $table): ?>
            <article class="page-card senda-stats-card">
                <h3 class="page-card__title"><?= e((string) $table['title']) ?></h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <?php foreach ($table['columns'] as $column): ?>
                                    <th><?= e((string) $column) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (($table['rows'] ?? []) === []): ?>
                                <tr>
                                    <td colspan="<?= count($table['columns']) ?>" class="text-secondary">Sin registros.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($table['rows'] as $row): ?>
                                    <tr>
                                        <td><?= e((string) $row[0]) ?></td>
                                        <td class="text-end"><?= (int) $row[1] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
