<?php

$meetings = is_array($meetings ?? null) ? $meetings : [];
$filters = is_array($filters ?? null) ? $filters : [];
$sourceModule = (string) ($sourceModule ?? 'admin');
$listUrl = $sourceModule === 'senda' ? url('/senda/meetings') : url('/meetings');
$createUrl = $sourceModule === 'senda' ? url('/senda/meetings/create') : url('/meetings/create');
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);

?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1"><?= $sourceModule === 'senda' ? 'SENDA' : 'Reuniones' ?></p>
        <h2 class="page-card__title mb-0">Registros de Reunión</h2>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (!empty($canCreate)): ?>
            <a class="btn btn-navy" href="<?= e($createUrl) ?>">Nueva Reunión</a>
        <?php endif; ?>
        <?php if (hasPermission('meetings.view_pending_signatures')): ?>
            <a class="btn btn-outline-navy" href="<?= e(url('/meetings/pending-signatures')) ?>">Firmas pendientes</a>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($sendaNav)): ?>
    <?= senda_nav($sendaNav) ?>
<?php endif; ?>

<div class="page-card mb-3">
    <form method="get" action="<?= e($listUrl) ?>" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label" for="date_from">Desde</label>
            <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e((string) ($filters['date_from'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="date_to">Hasta</label>
            <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e((string) ($filters['date_to'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="status">Estado</label>
            <select class="form-select" id="status" name="status">
                <option value="">Todos</option>
                <?php foreach ($statusOptions ?? [] as $option): ?>
                    <option value="<?= e((string) $option['value']) ?>" <?= (($filters['status'] ?? '') === $option['value']) ? 'selected' : '' ?>>
                        <?= e((string) $option['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" value="yes" id="pending_my_signature" name="pending_my_signature" <?= (($filters['pending_signature_user_id'] ?? null) ? 'checked' : '') ?>>
                <label class="form-check-label" for="pending_my_signature">Pendiente de mi firma</label>
            </div>
            <button class="btn btn-navy w-100" type="submit">Filtrar</button>
        </div>
    </form>
</div>

<div class="page-card">
    <?php if ($meetings === []): ?>
        <p class="text-secondary mb-0">No hay reuniones registradas con los filtros seleccionados.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>N.º</th>
                        <th>Fecha</th>
                        <th>Lugar</th>
                        <?php if ($sourceModule !== 'senda'): ?>
                            <th>Módulo</th>
                        <?php endif; ?>
                        <th>Participantes</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meetings as $meeting): ?>
                        <?php
                        $id = (int) ($meeting['id'] ?? 0);
                        $showHref = $sourceModule === 'senda' ? url('/senda/meetings/' . $id) : url('/meetings/' . $id);
                        $deleteHref = $sourceModule === 'senda' ? url('/senda/meetings/' . $id . '/delete') : url('/meetings/' . $id . '/delete');
                        ?>
                        <tr>
                            <td><?= e((string) ($meeting['meeting_number'] ?? '')) ?></td>
                            <td><?= e(!empty($meeting['meeting_date']) ? date('d-m-Y', strtotime((string) $meeting['meeting_date'])) : '—') ?></td>
                            <td><?= e((string) ($meeting['meeting_place'] ?? '—')) ?></td>
                            <?php if ($sourceModule !== 'senda'): ?>
                                <td><?= e((string) ($meeting['source_module_label'] ?? '—')) ?></td>
                            <?php endif; ?>
                            <td><?= e((string) ($meeting['participants_label'] ?? '—')) ?></td>
                            <td><span class="badge text-bg-secondary"><?= e((string) ($meeting['status_label'] ?? '—')) ?></span></td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    <a class="btn btn-sm btn-outline-navy" href="<?= e($showHref) ?>">Ver</a>
                                    <?php if (!empty($meeting['can_delete'])): ?>
                                        <form method="post" action="<?= e($deleteHref) ?>" class="d-inline"
                                              data-confirm="Esta acción eliminará el registro de forma permanente. ¿Desea continuar?"
                                              data-confirm-title="Eliminar reunión">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <p class="text-secondary mt-3 mb-0">Página <?= $page ?> de <?= $pages ?> · <?= (int) ($total ?? 0) ?> registros</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
