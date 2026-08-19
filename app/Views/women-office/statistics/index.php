<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Oficina de la Mujer</p>
        <h2 class="page-card__title mb-1">Estadísticas</h2>
        <p class="text-secondary mb-0">Indicadores agregados calculados desde MySQL. No incluyen nombres, RUT ni domicilios.</p>
    </div>
</section>

<?= women_nav($womenNav ?? []) ?>

<form class="page-card women-stats-filter" method="get" action="<?= e(url('/women/statistics')) ?>">
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
            <button class="btn btn-primary w-100" type="submit">Aplicar periodo</button>
        </div>
    </div>
</form>

<?php if (($summary ?? []) !== []): ?>
    <section class="women-metric-grid women-stats-summary" aria-label="Resumen del periodo">
        <?php foreach ($summary as $card): ?>
            <article class="women-metric women-metric--<?= e((string) ($card['tone'] ?? 'default')) ?>">
                <p class="women-metric__label"><?= e((string) ($card['label'] ?? '')) ?></p>
                <p class="women-metric__value"><?= (int) ($card['count'] ?? 0) ?></p>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if (($tables ?? []) === []): ?>
    <div class="page-card">
        <p class="mb-0 text-secondary">No hay indicadores disponibles.</p>
    </div>
<?php else: ?>
    <section class="women-stats-grid" aria-label="Indicadores estadísticos">
        <?php foreach ($tables as $table): ?>
            <article class="page-card women-stats-card">
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
