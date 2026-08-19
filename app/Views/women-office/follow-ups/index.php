<?php

$due = in_array($due ?? '', ['pending', 'today', 'overdue'], true) ? (string) $due : 'pending';
$items = $items ?? [];
$baseUrl = url('/women/follow-ups');
$tabUrl = static fn (string $filter): string => $baseUrl . '?' . http_build_query(['due' => $filter]);

?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Oficina de la Mujer</p>
        <h2 class="page-card__title mb-1">Agenda de seguimientos</h2>
        <p class="text-secondary mb-0"><?= (int) ($total ?? 0) ?> casos con seguimiento programado</p>
    </div>
    <a class="btn btn-outline-navy" href="<?= e(url('/women')) ?>">Volver al panel</a>
</section>

<?= women_nav($womenNav ?? []) ?>

<ul class="nav nav-tabs women-case-tabs mb-3" role="tablist">
    <li class="nav-item">
        <a class="nav-link <?= $due === 'pending' ? 'active' : '' ?>" href="<?= e($tabUrl('pending')) ?>">Todos pendientes</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $due === 'today' ? 'active' : '' ?>" href="<?= e($tabUrl('today')) ?>">Para hoy</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $due === 'overdue' ? 'active' : '' ?>" href="<?= e($tabUrl('overdue')) ?>">Atrasados</a>
    </li>
</ul>

<div class="page-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Próximo seguimiento</th>
                    <th>N.º caso</th>
                    <th>Persona</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($items === []): ?>
                    <tr>
                        <td colspan="6" class="text-secondary">No hay seguimientos en esta categoría.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <span class="women-followup-date women-followup-date--<?= e((string) ($item['next_follow_up_tone'] ?? 'scheduled')) ?>">
                                    <?= e((string) ($item['next_follow_up_label'] ?? '—')) ?>
                                </span>
                            </td>
                            <td><code><?= e((string) ($item['case_number'] ?? '')) ?></code></td>
                            <td><?= e((string) ($item['person_display_name'] ?? '—')) ?></td>
                            <td>
                                <?php if (!empty($item['priority'])): ?>
                                    <span class="women-priority-badge women-priority-badge--<?= e((string) $item['priority']) ?>">
                                        <?= e((string) ($item['priority_label'] ?? '—')) ?>
                                    </span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) ($item['case_status_name'] ?? '—')) ?></td>
                            <td class="text-end">
                                <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/women/cases/' . ($item['id'] ?? ''))) ?>">Ver caso</a>
                                <?php if (hasPermission('women.cases.edit')): ?>
                                    <a class="btn btn-navy btn-sm" href="<?= e(url('/women/cases/' . ($item['id'] ?? '') . '/follow-ups')) ?>">Registrar</a>
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
        'base' => $baseUrl,
        'query' => ['due' => $due],
    ]) ?>
</div>
