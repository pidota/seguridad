<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Reuniones</p>
        <h2 class="page-card__title mb-0">Pendientes de Mi Firma</h2>
        <?php if (($pendingCount ?? 0) > 0): ?>
            <p class="text-secondary mb-0 mt-1"><?= (int) $pendingCount ?> solicitud(es) activa(s)</p>
        <?php endif; ?>
    </div>
    <?php if (hasPermission('meetings.signature.manage')): ?>
        <a class="btn btn-outline-navy" href="<?= e(url('/meetings/profile/signature')) ?>">Mi firma simple</a>
    <?php endif; ?>
</section>

<div class="page-card">
    <?php if (($meetings ?? []) === []): ?>
        <p class="text-secondary mb-0">No tiene reuniones pendientes de firma.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>N° Reunión</th>
                        <th>Fecha</th>
                        <th>Origen</th>
                        <th>Estado</th>
                        <th>Firmas</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meetings as $row): ?>
                        <?php
                        $showPath = (($row['source_module'] ?? '') === 'senda')
                            ? url('/senda/meetings/' . (int) ($row['id'] ?? 0))
                            : url('/meetings/' . (int) ($row['id'] ?? 0));
                        $signPath = (($row['source_module'] ?? '') === 'senda')
                            ? url('/senda/meetings/' . (int) ($row['id'] ?? 0) . '/sign')
                            : url('/meetings/' . (int) ($row['id'] ?? 0) . '/sign');
                        ?>
                        <tr>
                            <td><?= e((string) ($row['meeting_number'] ?? '—')) ?></td>
                            <td><?= e(!empty($row['meeting_date']) ? date('d-m-Y', strtotime((string) $row['meeting_date'])) : '—') ?></td>
                            <td><?= e((string) ($row['source_module_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($row['status_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($row['signatures_label'] ?? '—')) ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-navy me-1" href="<?= e($showPath) ?>">Ver</a>
                                <a class="btn btn-sm btn-navy" href="<?= e($signPath) ?>">Firmar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
