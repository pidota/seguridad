<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-1">Ficha de Referencia Asistida a Tratamiento</h2>
        <p class="text-secondary mb-0"><?= (int) count($referrals ?? []) ?> fichas</p>
    </div>
    <?php if (hasPermission('senda.referrals.create')): ?>
        <a href="<?= e(url('/senda/referrals/create')) ?>" class="btn btn-navy">Nueva ficha</a>
    <?php endif; ?>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<div class="page-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Atención</th>
                    <th>Persona</th>
                    <th>RUT</th>
                    <th>Origen</th>
                    <th>Riesgo</th>
                    <th>Estado</th>
                    <th>Funcionario</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (($referrals ?? []) === []): ?>
                    <tr>
                        <td colspan="9" class="text-secondary">No hay fichas de referencia. Seleccione una atención existente para crear una.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($referrals as $item): ?>
                        <tr>
                            <td><?= e(date('d-m-Y', strtotime((string) $item['request_date']))) ?></td>
                            <td>
                                <?php if (hasPermission('senda.attentions.view')): ?>
                                    <a href="<?= e(url('/senda/attentions/' . $item['senda_attention_id'])) ?>"><code><?= e((string) $item['attention_number']) ?></code></a>
                                <?php else: ?>
                                    <code><?= e((string) $item['attention_number']) ?></code>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (hasPermission('senda.followups.view')): ?>
                                    <a href="<?= e(url('/senda/follow-ups/person/' . $item['senda_person_id'])) ?>"><?= e((string) ($item['person_full_name'] ?: 'Persona')) ?></a>
                                <?php else: ?>
                                    <a href="<?= e(url('/senda/people/' . $item['senda_person_id'])) ?>"><?= e((string) ($item['person_full_name'] ?: 'Persona')) ?></a>
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) ($item['rut'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['demand_origin_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['overall_risk_label'] ?? '—')) ?></td>
                            <td>
                                <span class="status-pill <?= !empty($item['is_completed']) ? 'is-on' : 'is-off' ?>">
                                    <?= e((string) ($item['status_label'] ?? (!empty($item['is_completed']) ? 'Finalizada' : 'Borrador'))) ?>
                                </span>
                            </td>
                            <td><?= e((string) ($item['created_by_name'] ?? '—')) ?></td>
                            <td class="text-end text-nowrap">
                                <?php if (hasPermission('senda.referrals.view')): ?>
                                    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/senda/referrals/' . $item['id'])) ?>">Ver</a>
                                <?php endif; ?>
                                <?php if (hasPermission('senda.referrals.edit')): ?>
                                    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/senda/referrals/' . $item['id'] . '/edit')) ?>">
                                        <?= empty($item['is_completed']) || hasPermission('senda.referrals.edit_completed') ? 'Editar' : 'Ver' ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
