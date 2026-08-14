<?php
$filters = $filters ?? [];
$query = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '' && $value !== 'sin');
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-1">Nueva ficha de referencia</h2>
        <p class="text-secondary mb-0">La ficha debe pertenecer a una atención existente que aún no tenga ficha.</p>
    </div>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<form method="get" action="<?= e(url('/senda/referrals/create')) ?>" class="page-card senda-filters mb-3">
    <div class="senda-filters__grid">
        <div>
            <label class="form-label" for="filter_q">Buscar atención</label>
            <input class="form-control" id="filter_q" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="N.º, RUT o nombre" data-rut-input>
        </div>
    </div>
    <div class="senda-filters__actions">
        <button class="btn btn-navy" type="submit">Buscar</button>
        <?php if (($filters['q'] ?? '') !== ''): ?>
            <a class="btn btn-outline-navy" href="<?= e(url('/senda/referrals/create')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="page-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>N.º atención</th>
                    <th>Fecha</th>
                    <th>Persona</th>
                    <th>RUT</th>
                    <th>Edad</th>
                    <th>Tipo de ingreso</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (($attentions ?? []) === []): ?>
                    <tr>
                        <td colspan="7" class="text-secondary">
                            No hay atenciones sin ficha.
                            <?php if (hasPermission('senda.attentions.create')): ?>
                                <a href="<?= e(url('/senda/attentions/create')) ?>">Registrar una atención</a> primero.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($attentions as $item): ?>
                        <?php $meta = \App\Services\Senda\EntryType::meta((string) $item['entry_type']); ?>
                        <tr>
                            <td><code><?= e((string) $item['attention_number']) ?></code></td>
                            <td><?= e(date('d-m-Y', strtotime((string) $item['attention_date']))) ?></td>
                            <td><?= e((string) ($item['person_full_name'] ?: '—')) ?></td>
                            <td><?= e((string) ($item['person_rut'] ?: '—')) ?></td>
                            <td><?= $item['person_age'] !== null ? e((string) $item['person_age']) . ' años' : '—' ?></td>
                            <td><span class="senda-badge senda-badge--<?= e($meta['tone']) ?>"><?= e($meta['label']) ?></span></td>
                            <td class="text-end">
                                <a class="btn btn-navy btn-sm" href="<?= e(url('/senda/referrals/create') . '?attention=' . $item['id']) ?>">Usar esta atención</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= component('pagination', [
        'page' => $page ?? 1,
        'pages' => $pages ?? 1,
        'base' => url('/senda/referrals/create'),
        'query' => $query,
    ]) ?>
</div>
