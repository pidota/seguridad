<div class="page-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h3 class="page-card__title mb-0">Derivaciones institucionales</h3>
        <?php if (!empty($canEdit)): ?>
            <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/referrals')) ?>">Editar derivaciones</a>
        <?php endif; ?>
    </div>
    <?php if (!empty($case['has_referrals'])): ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Institución</th>
                        <th>Área / programa</th>
                        <th>Estado</th>
                        <th>Motivo</th>
                        <th>Contacto</th>
                        <th>Funcionario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($case['referrals'] as $referral): ?>
                        <tr>
                            <td><?= e(!empty($referral['referral_date']) ? date('d-m-Y', strtotime((string) $referral['referral_date'])) : '—') ?></td>
                            <td><?= e((string) ($referral['institution_name'] ?? '—')) ?></td>
                            <td><?= e((string) ($referral['program_area'] ?? '—')) ?></td>
                            <td><?= e((string) ($referral['referral_status_name'] ?? '—')) ?></td>
                            <td><?= e((string) ($referral['reason'] ?? '—')) ?></td>
                            <td><?= e((string) ($referral['contact_person'] ?? '—')) ?></td>
                            <td><?= e((string) ($referral['created_by_name'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-secondary mb-0">No hay derivaciones registradas en este caso.</p>
    <?php endif; ?>
</div>
