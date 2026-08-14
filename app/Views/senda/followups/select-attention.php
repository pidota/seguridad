<?php
$filters = $filters ?? [];
$query = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');
$scoped = !empty($scoped);
$returnTo = in_array($returnTo ?? '', ['attention', 'history'], true) ? (string) $returnTo : '';
$person = $person ?? [];
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-1"><?= $scoped ? '¿A qué atención corresponde este seguimiento?' : 'Nuevo seguimiento' ?></h2>
        <p class="text-secondary mb-0">
            <?php if ($scoped): ?>
                Seleccione la atención. El sistema no asignará el seguimiento de forma automática.
            <?php else: ?>
                El seguimiento debe pertenecer a una atención existente. Una atención puede tener varios seguimientos.
            <?php endif; ?>
        </p>
    </div>
    <?php if ($scoped && !empty($person['id'])): ?>
        <a class="btn btn-outline-navy" href="<?= e(url('/senda/follow-ups/person/' . $person['id'])) ?>">Volver a la persona</a>
    <?php endif; ?>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<?php if ($scoped && $person !== []): ?>
    <div class="mb-3">
        <?= \Core\View::make('senda/people/card', [
            'person' => $person,
            'showUse' => false,
            'compact' => true,
        ], null) ?>
    </div>
<?php endif; ?>

<?php if (!$scoped): ?>
<form method="get" action="<?= e(url('/senda/follow-ups/create')) ?>" class="page-card senda-filters mb-3">
    <div class="senda-filters__grid">
        <div>
            <label class="form-label" for="filter_q">Buscar atención</label>
            <input class="form-control" id="filter_q" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="N.º, RUT o nombre" data-rut-input>
        </div>
    </div>
    <div class="senda-filters__actions">
        <button class="btn btn-navy" type="submit">Buscar</button>
        <?php if (($filters['q'] ?? '') !== ''): ?>
            <a class="btn btn-outline-navy" href="<?= e(url('/senda/follow-ups/create')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>
<?php endif; ?>

<div class="page-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>N.º atención</th>
                    <th>Fecha</th>
                    <th>Persona</th>
                    <th>RUT</th>
                    <th>Tipo de ingreso</th>
                    <th>Seguimientos</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (($attentions ?? []) === []): ?>
                    <tr>
                        <td colspan="7" class="text-secondary">
                            No hay atenciones.
                            <?php if (hasPermission('senda.attentions.create')): ?>
                                <a href="<?= e(url('/senda/attentions/create')) ?>">Registrar una atención</a> primero.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($attentions as $item): ?>
                        <?php $meta = \App\Services\Senda\EntryType::meta((string) $item['entry_type']); ?>
                        <?php
                            $useUrl = url('/senda/follow-ups/create') . '?attention=' . (int) $item['id'];
                            if ($returnTo !== '') {
                                $useUrl .= '&return=' . rawurlencode($returnTo);
                            }
                        ?>
                        <tr>
                            <td><code><?= e((string) $item['attention_number']) ?></code></td>
                            <td><?= e(date('d-m-Y', strtotime((string) $item['attention_date']))) ?></td>
                            <td><?= e((string) ($item['person_full_name'] ?: '—')) ?></td>
                            <td><?= e((string) ($item['person_rut'] ?: '—')) ?></td>
                            <td>
                                <span class="senda-badge senda-badge--<?= e($meta['tone']) ?>">
                                    <i class="bi <?= $meta['value'] === \App\Services\Senda\EntryType::DERIVACION ? 'bi-signpost-split' : 'bi-person-walking' ?>"></i>
                                    <?= e($meta['label']) ?>
                                </span>
                            </td>
                            <td><?= (int) ($item['followup_count'] ?? 0) ?></td>
                            <td class="text-end">
                                <a class="btn btn-navy btn-sm" href="<?= e($useUrl) ?>">Usar esta atención</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$scoped): ?>
        <?= component('pagination', [
            'page' => $page ?? 1,
            'pages' => $pages ?? 1,
            'base' => url('/senda/follow-ups/create'),
            'query' => $query,
        ]) ?>
    <?php endif; ?>
</div>

