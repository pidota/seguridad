<?php

$timeline = is_array($timeline ?? null) ? $timeline : [];
$auditHistory = is_array($auditHistory ?? null) ? $auditHistory : [];
$order = ($order ?? 'desc') === 'asc' ? 'asc' : 'desc';
$baseUrl = (string) ($baseUrl ?? '#');

?>
<div class="page-card mb-3">
    <div class="page-toolbar mb-3">
        <h3 class="page-card__title mb-0">Línea de tiempo del caso</h3>
        <div class="btn-group">
            <a class="btn btn-sm <?= $order === 'desc' ? 'btn-navy' : 'btn-outline-navy' ?>" href="<?= e($baseUrl . '?' . http_build_query(['tab' => 'historial', 'order' => 'desc'])) ?>">Más reciente primero</a>
            <a class="btn btn-sm <?= $order === 'asc' ? 'btn-navy' : 'btn-outline-navy' ?>" href="<?= e($baseUrl . '?' . http_build_query(['tab' => 'historial', 'order' => 'asc'])) ?>">Más antiguo primero</a>
        </div>
    </div>

    <?php if ($timeline === []): ?>
        <p class="text-secondary mb-0">Aún no hay actuaciones registradas en este caso.</p>
    <?php else: ?>
        <ol class="women-timeline">
            <?php foreach ($timeline as $event): ?>
                <li class="women-timeline__item women-timeline__item--<?= e((string) ($event['tone'] ?? 'default')) ?>">
                    <div class="women-timeline__marker" aria-hidden="true">
                        <i class="bi <?= e((string) ($event['icon'] ?? 'bi-circle')) ?>"></i>
                    </div>
                    <div class="women-timeline__body">
                        <p class="women-timeline__when"><?= e((string) ($event['datetime_label'] ?? '')) ?></p>
                        <p class="women-timeline__title mb-1"><?= e((string) ($event['title'] ?? '')) ?></p>
                        <?php foreach ($event['lines'] ?? [] as $line): ?>
                            <p class="women-timeline__line mb-1"><?= e((string) $line) ?></p>
                        <?php endforeach; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>

<?php if (hasPermission('women.audit.view')): ?>
<div class="page-card">
    <h3 class="page-card__title">Registro de auditoría</h3>
    <?php if ($auditHistory === []): ?>
        <p class="text-secondary mb-0">No hay eventos de auditoría para este caso.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Detalle</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($auditHistory as $entry): ?>
                        <tr>
                            <td><?= e((string) ($entry['datetime_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($entry['user_name'] ?? '—')) ?></td>
                            <td><?= e((string) ($entry['action_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($entry['summary'] ?? '—')) ?></td>
                            <td><?= e((string) ($entry['ip_address'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
