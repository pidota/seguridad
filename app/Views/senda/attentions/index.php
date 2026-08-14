<?php
$filters = $filters ?? [];
$query = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');
$hasFilters = $query !== [];
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-1">Registro de Atención</h2>
        <p class="text-secondary mb-0"><?= (int) ($total ?? 0) ?> atenciones</p>
    </div>
    <div class="page-toolbar__actions">
        <form method="get" action="<?= e(url('/senda/attentions')) ?>" class="search-inline">
            <?php foreach ($filters as $key => $value): ?>
                <?php if ($key === 'q' || $value === '') continue; ?>
                <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>">
            <?php endforeach; ?>
            <input type="search" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" class="form-control" placeholder="Buscar N.º, RUT o nombre" data-rut-input>
            <button class="btn btn-outline-navy" type="submit">Buscar</button>
        </form>
        <?php if (hasPermission('senda.attentions.create')): ?>
            <a href="<?= e(url('/senda/attentions/create')) ?>" class="btn btn-navy">Nueva atención</a>
        <?php endif; ?>
    </div>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<form method="get" action="<?= e(url('/senda/attentions')) ?>" class="page-card senda-filters mb-3">
    <?php if (($filters['q'] ?? '') !== ''): ?>
        <input type="hidden" name="q" value="<?= e((string) $filters['q']) ?>">
    <?php endif; ?>
    <div class="senda-filters__grid">
        <div>
            <label class="form-label" for="filter_rut">RUT</label>
            <input class="form-control" id="filter_rut" name="rut" value="<?= e((string) ($filters['rut'] ?? '')) ?>" placeholder="12.345.678-5" data-rut-input>
        </div>
        <div>
            <label class="form-label" for="filter_name">Nombre</label>
            <input class="form-control" id="filter_name" name="name" value="<?= e((string) ($filters['name'] ?? '')) ?>">
        </div>
        <div>
            <label class="form-label" for="filter_date_from">Fecha desde</label>
            <input type="date" class="form-control" id="filter_date_from" name="date_from" value="<?= e((string) ($filters['date_from'] ?? '')) ?>">
        </div>
        <div>
            <label class="form-label" for="filter_date_to">Fecha hasta</label>
            <input type="date" class="form-control" id="filter_date_to" name="date_to" value="<?= e((string) ($filters['date_to'] ?? '')) ?>">
        </div>
        <div>
            <label class="form-label" for="filter_entry_type">Tipo de ingreso</label>
            <select class="form-select" id="filter_entry_type" name="entry_type">
                <option value="">Todos</option>
                <?php foreach ($entryTypes ?? [] as $type): ?>
                    <option value="<?= e($type['value']) ?>" <?= ($filters['entry_type'] ?? '') === $type['value'] ? 'selected' : '' ?>><?= e($type['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="filter_created_by">Funcionario</label>
            <select class="form-select" id="filter_created_by" name="created_by">
                <option value="">Todos</option>
                <?php foreach ($staff ?? [] as $user): ?>
                    <option value="<?= e((string) $user['id']) ?>" <?= (string) ($filters['created_by'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>><?= e((string) $user['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="filter_ficha">Ficha</label>
            <select class="form-select" id="filter_ficha" name="ficha">
                <option value="">Todas</option>
                <option value="con" <?= ($filters['ficha'] ?? '') === 'con' ? 'selected' : '' ?>>Con ficha</option>
                <option value="sin" <?= ($filters['ficha'] ?? '') === 'sin' ? 'selected' : '' ?>>Sin ficha</option>
            </select>
        </div>
    </div>
    <div class="senda-filters__actions">
        <button class="btn btn-navy" type="submit">Filtrar</button>
        <?php if ($hasFilters): ?>
            <a class="btn btn-outline-navy" href="<?= e(url('/senda/attentions')) ?>">Limpiar</a>
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
                    <th>Funcionario</th>
                    <th>Ficha</th>
                    <th>Seguimiento</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($attentions === []): ?>
                    <tr>
                        <td colspan="10" class="text-secondary">No hay atenciones para mostrar.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($attentions as $item): ?>
                        <?php $meta = \App\Services\Senda\EntryType::meta((string) $item['entry_type']); ?>
                        <tr>
                            <td><code><?= e((string) $item['attention_number']) ?></code></td>
                            <td><?= e(date('d-m-Y', strtotime((string) $item['attention_date']))) ?></td>
                            <td>
                                <?php if (!empty($item['senda_person_id'])): ?>
                                    <a href="<?= e(url('/senda/people/' . $item['senda_person_id'])) ?>"><?= e((string) ($item['person_full_name'] ?: 'Persona')) ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) ($item['person_rut'] ?: '—')) ?></td>
                            <td><?= $item['person_age'] !== null ? e((string) $item['person_age']) . ' años' : '—' ?></td>
                            <td>
                                <span class="senda-badge senda-badge--<?= e($meta['tone']) ?>"><?= e($meta['label']) ?></span>
                            </td>
                            <td><?= e((string) ($item['created_by_name'] ?? '—')) ?></td>
                            <td>
                                <?php if (!empty($item['has_ficha']) && !empty($item['ficha_id']) && hasPermission('senda.referrals.edit')): ?>
                                    <a class="status-pill is-on" href="<?= e(url('/senda/referrals/' . $item['ficha_id'] . '/edit')) ?>">Sí</a>
                                <?php elseif (!empty($item['has_ficha'])): ?>
                                    <span class="status-pill is-on">Sí</span>
                                <?php elseif (hasPermission('senda.referrals.create')): ?>
                                    <a class="status-pill is-off" href="<?= e(url('/senda/referrals/create') . '?attention=' . $item['id']) ?>">Crear</a>
                                <?php else: ?>
                                    <span class="status-pill is-off">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($item['has_followup']) && hasPermission('senda.followups.view')): ?>
                                    <a class="status-pill is-on" href="<?= e(url('/senda/follow-ups') . '?attention=' . $item['id']) ?>"><?= (int) $item['followup_count'] ?></a>
                                <?php elseif (!empty($item['has_followup'])): ?>
                                    <span class="status-pill is-on"><?= (int) $item['followup_count'] ?></span>
                                <?php elseif (hasPermission('senda.followups.create')): ?>
                                    <a class="status-pill is-off" href="<?= e(url('/senda/follow-ups/create') . '?attention=' . $item['id']) ?>">Crear</a>
                                <?php else: ?>
                                    <span class="status-pill is-off">No</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if (!empty($item['senda_person_id']) && hasPermission('senda.followups.view')): ?>
                                    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/senda/follow-ups/person/' . $item['senda_person_id'])) ?>">Historial</a>
                                <?php elseif (!empty($item['senda_person_id'])): ?>
                                    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/senda/people/' . $item['senda_person_id'])) ?>">Historial</a>
                                <?php endif; ?>
                                <?php if (hasPermission('senda.attentions.view')): ?>
                                    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/senda/attentions/' . $item['id'])) ?>">Ver</a>
                                <?php endif; ?>
                                <?php if (hasPermission('senda.attentions.edit')): ?>
                                    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/senda/attentions/' . $item['id'] . '/edit')) ?>">Editar</a>
                                <?php endif; ?>
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
        'base' => url('/senda/attentions'),
        'query' => $query,
    ]) ?>
</div>
