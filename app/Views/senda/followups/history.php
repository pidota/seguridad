<?php
$followups = $followups ?? [];
$attentionId = (int) ($attentionId ?? 0);
$returnTo = $returnTo ?? 'attention';
$canView = hasPermission('senda.followups.view');
$canCreate = hasPermission('senda.followups.create');
?>
<div class="page-card mt-3">
    <div class="page-toolbar mb-3">
        <div>
            <h3 class="page-card__title mb-1">Historial de Seguimientos</h3>
            <?php if ($canView): ?>
                <p class="text-secondary mb-0"><?= (int) count($followups) ?> seguimiento<?= count($followups) === 1 ? '' : 's' ?></p>
            <?php endif; ?>
        </div>
        <?php if ($canCreate && $attentionId > 0): ?>
            <a class="btn btn-navy" href="<?= e(url('/senda/follow-ups/create') . '?attention=' . $attentionId . ($returnTo === 'attention' ? '&return=attention' : '')) ?>">Nuevo seguimiento</a>
        <?php endif; ?>
    </div>

    <?php if ($canView): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Tipo</th>
                        <th>Resultado</th>
                        <th>Funcionario</th>
                        <th>Próximo seguimiento</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($followups === []): ?>
                        <tr>
                            <td colspan="7" class="text-secondary">
                                Esta atención aún no tiene seguimientos.
                                <?php if ($canCreate): ?>
                                    <a href="<?= e(url('/senda/follow-ups/create') . '?attention=' . $attentionId . ($returnTo === 'attention' ? '&return=attention' : '')) ?>">Registrar uno</a>.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($followups as $item): ?>
                            <tr>
                                <td><?= e(date('d-m-Y', strtotime((string) $item['follow_up_date']))) ?></td>
                                <td><?= e((string) ($item['follow_up_time'] !== '' ? $item['follow_up_time'] : '—')) ?></td>
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
                                        'returnTo' => $returnTo,
                                    ], null) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
