<?php
$filters = $filters ?? [];
$query = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');
$hasFilters = $query !== [];
$attentionId = (int) ($filters['attention'] ?? 0);
$status = (string) ($filters['status'] ?? '');
$statusLabel = $status !== '' ? \App\Services\Senda\FollowUpStatus::label($status) : '';
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-1">Seguimiento SENDA</h2>
        <p class="text-secondary mb-0"><?= (int) ($total ?? 0) ?> seguimientos</p>
    </div>
    <?php if (hasPermission('senda.followups.create')): ?>
        <a href="<?= e(url('/senda/follow-ups/create') . ($attentionId > 0 ? '?attention=' . $attentionId : '')) ?>" class="btn btn-navy">Nuevo seguimiento</a>
    <?php endif; ?>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<?php if ($statusLabel !== ''): ?>
    <p class="senda-filter-note">Mostrando: <strong><?= e($statusLabel) ?></strong></p>
<?php endif; ?>

<form method="get" action="<?= e(url('/senda/follow-ups')) ?>" class="page-card senda-filters mb-3">
    <?php if ($attentionId > 0): ?>
        <input type="hidden" name="attention" value="<?= $attentionId ?>">
    <?php endif; ?>
    <?php if ($status !== ''): ?>
        <input type="hidden" name="status" value="<?= e($status) ?>">
    <?php endif; ?>
    <div class="senda-filters__grid">
        <div>
            <label class="form-label" for="filter_name">Nombre</label>
            <input class="form-control" id="filter_name" name="name" value="<?= e((string) ($filters['name'] ?? '')) ?>">
        </div>
        <div>
            <label class="form-label" for="filter_rut">RUT</label>
            <input class="form-control" id="filter_rut" name="rut" value="<?= e((string) ($filters['rut'] ?? '')) ?>" placeholder="12.345.678-5" data-rut-input>
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
            <label class="form-label" for="filter_contact_type">Tipo</label>
            <select class="form-select" id="filter_contact_type" name="contact_type">
                <option value="">Todos</option>
                <?php foreach ($contactTypes ?? [] as $option): ?>
                    <option value="<?= e($option['value']) ?>" <?= ($filters['contact_type'] ?? '') === $option['value'] ? 'selected' : '' ?>><?= e($option['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="filter_result">Resultado</label>
            <select class="form-select" id="filter_result" name="result">
                <option value="">Todos</option>
                <?php foreach ($results ?? [] as $option): ?>
                    <option value="<?= e($option['value']) ?>" <?= ($filters['result'] ?? '') === $option['value'] ? 'selected' : '' ?>><?= e($option['label']) ?></option>
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
            <label class="form-label" for="filter_pending">Pendientes</label>
            <select class="form-select" id="filter_pending" name="pending">
                <option value="">Todos</option>
                <option value="si" <?= ($filters['pending'] ?? '') === 'si' ? 'selected' : '' ?>>Pendientes</option>
                <option value="no" <?= ($filters['pending'] ?? '') === 'no' ? 'selected' : '' ?>>Sin pendiente</option>
            </select>
        </div>
    </div>
    <div class="senda-filters__actions">
        <button class="btn btn-navy" type="submit">Filtrar</button>
        <?php if ($hasFilters): ?>
            <a class="btn btn-outline-navy" href="<?= e(url('/senda/follow-ups')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="page-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Persona</th>
                    <th>RUT</th>
                    <th>Atención</th>
                    <th>Fecha</th>
                    <th>Tipo de contacto</th>
                    <th>Resultado</th>
                    <th>Funcionario</th>
                    <th>Próximo seguimiento</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (($followups ?? []) === []): ?>
                    <tr>
                        <td colspan="9" class="text-secondary">
                            No hay seguimientos para mostrar.
                            <?php if (hasPermission('senda.followups.create')): ?>
                                <a href="<?= e(url('/senda/follow-ups/create') . ($attentionId > 0 ? '?attention=' . $attentionId : '')) ?>">Registrar uno</a>.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($followups as $item): ?>
                        <tr>
                            <td>
                                <?php if (!empty($item['senda_person_id'])): ?>
                                    <a href="<?= e(url('/senda/people/' . $item['senda_person_id'])) ?>"><?= e((string) ($item['person_full_name'] ?: 'Persona')) ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) ($item['person_rut'] ?: '—')) ?></td>
                            <td>
                                <?php if (hasPermission('senda.attentions.edit')): ?>
                                    <a href="<?= e(url('/senda/attentions/' . $item['senda_attention_id'] . '/edit')) ?>"><code><?= e((string) $item['attention_number']) ?></code></a>
                                <?php else: ?>
                                    <code><?= e((string) $item['attention_number']) ?></code>
                                <?php endif; ?>
                            </td>
                            <td><?= e(date('d-m-Y', strtotime((string) $item['follow_up_date']))) ?></td>
                            <td><?= e((string) ($item['contact_type_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['result_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['created_by_name'] ?? '—')) ?></td>
                            <td>
                                <?php if (!empty($item['is_pending']) && !empty($item['next_follow_up_date'])): ?>
                                    <?= e(date('d-m-Y', strtotime((string) $item['next_follow_up_date']))) ?>
                                    <?php if (!empty($item['is_overdue'])): ?>
                                        <span class="status-pill is-off">Atrasado</span>
                                    <?php elseif (!empty($item['is_due_today'])): ?>
                                        <span class="status-pill is-on">Hoy</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?= \Core\View::make('senda/followups/actions', [
                                    'item' => $item,
                                    'returnTo' => '',
                                ], null) ?>
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
        'base' => url('/senda/follow-ups'),
        'query' => $query,
    ]) ?>
</div>
