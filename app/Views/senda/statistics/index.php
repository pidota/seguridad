<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-1">Estadísticas</h2>
        <p class="text-secondary mb-0">Indicadores agregados para rendición a jefatura. Sin datos personales identificables.</p>
    </div>
    <?php if (hasPermission('senda.statistics.export')): ?>
        <?php
            $exportQuery = http_build_query(array_filter([
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
            ]));
        ?>
        <a href="<?= e(url('/senda/statistics/export' . ($exportQuery !== '' ? '?' . $exportQuery : ''))) ?>" class="btn btn-outline-navy">
            Exportar CSV
        </a>
    <?php endif; ?>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<form class="page-card senda-stats-filter mb-3" method="get" action="<?= e(url('/senda/statistics')) ?>">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label" for="date_from">Desde</label>
            <input class="form-control" type="date" id="date_from" name="date_from" value="<?= e((string) ($filters['date_from'] ?? '')) ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="date_to">Hasta</label>
            <input class="form-control" type="date" id="date_to" name="date_to" value="<?= e((string) ($filters['date_to'] ?? '')) ?>" required>
        </div>
        <div class="col-md-4">
            <button class="btn btn-navy w-100" type="submit">Aplicar periodo</button>
        </div>
    </div>
</form>

<?php if (($summary ?? []) !== []): ?>
    <section class="senda-metric-grid senda-stats-summary mb-3" aria-label="Resumen del periodo">
        <?php foreach ($summary as $card): ?>
            <article class="senda-metric senda-metric--<?= e((string) ($card['tone'] ?? 'default')) ?>">
                <p class="senda-metric__label"><?= e((string) ($card['label'] ?? '')) ?></p>
                <p class="senda-metric__value"><?= (int) ($card['count'] ?? 0) ?></p>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

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
