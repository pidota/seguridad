<div class="page-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h3 class="page-card__title mb-0">Seguimientos</h3>
        <?php if (!empty($canEdit)): ?>
            <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/follow-ups')) ?>">Editar seguimientos</a>
        <?php endif; ?>
    </div>
    <?php if (!empty($case['has_followups'])): ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Contacto</th>
                        <th>Resultado</th>
                        <th>Próximo</th>
                        <th>Funcionario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($case['followups'] as $followup): ?>
                        <tr>
                            <td><?= e(!empty($followup['follow_up_date']) ? date('d-m-Y', strtotime((string) $followup['follow_up_date'])) : '—') ?></td>
                            <td><?= e((string) ($followup['follow_up_time_short'] ?? '—')) ?></td>
                            <td>
                                <?= e((string) ($followup['contact_type_name'] ?? '—')) ?>
                                <?php if (!empty($followup['contact_type_other'])): ?>
                                    (<?= e((string) $followup['contact_type_other']) ?>)
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e((string) ($followup['result_name'] ?? '—')) ?>
                                <?php if (!empty($followup['result_other'])): ?>
                                    (<?= e((string) $followup['result_other']) ?>)
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($followup['is_pending']) && !empty($followup['next_follow_up_date'])): ?>
                                    <?= e(date('d-m-Y', strtotime((string) $followup['next_follow_up_date']))) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) ($followup['created_by_name'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-secondary mb-0">No hay seguimientos registrados en este caso.</p>
    <?php endif; ?>
</div>
