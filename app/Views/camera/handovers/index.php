<?php
$pendingHandovers = $pendingHandovers ?? [];
$pendingCount = (int) ($pendingCount ?? count($pendingHandovers));
$openShift = $openShift ?? [];
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Continuidad operativa</p>
        <h2 class="page-card__title mb-1">Pendientes recibidos</h2>
        <p class="text-secondary mb-0">Turno activo · <?= e((string) ($openShift['started_at_formatted'] ?? '—')) ?></p>
    </div>
    <a class="btn btn-outline-navy" href="<?= e(url('/cctv#turno-activo')) ?>">Volver al panel</a>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<?php if ($pendingCount > 0): ?>
    <div class="cctv-handover-alert mb-3" role="status">
        <i class="bi bi-exclamation-triangle"></i>
        <span>Tiene <strong><?= $pendingCount ?></strong> procedimiento(s) heredado(s) pendiente(s) de revisión.</span>
    </div>
<?php endif; ?>

<div class="page-card page-card--lg">
    <?php if ($pendingHandovers === []): ?>
        <p class="text-secondary mb-0">No hay pendientes recibidos del turno anterior.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Registro</th>
                        <th>Fecha / Hora</th>
                        <th>Tipo</th>
                        <th>Sector</th>
                        <th>Operador anterior</th>
                        <th>Última actuación</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingHandovers as $item): ?>
                        <tr>
                            <td>
                                <strong><?= e((string) ($item['entry_reference'] ?? '')) ?></strong>
                                <div class="text-secondary small"><?= e((string) ($item['incident_label'] ?? '')) ?></div>
                            </td>
                            <td>
                                <?= e((string) ($item['event_date_label'] ?? '')) ?><br>
                                <span class="text-secondary"><?= e((string) ($item['event_time_label'] ?? '')) ?></span>
                            </td>
                            <td><?= e((string) ($item['type_label'] ?? '')) ?></td>
                            <td><?= e((string) ($item['sector_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['from_operator_label'] ?? '—')) ?></td>
                            <td class="small">
                                <?php if (!empty($item['last_action'])): ?>
                                    <?= e((string) ($item['last_action']['time_label'] ?? '')) ?> —
                                    <?= e((string) ($item['last_action']['description'] ?? '')) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-navy" href="<?= e(url('/cctv/handovers/' . (int) ($item['id'] ?? 0))) ?>">Revisar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
