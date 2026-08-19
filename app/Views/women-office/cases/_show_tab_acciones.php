<div class="page-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h3 class="page-card__title mb-0">Acciones realizadas</h3>
        <?php if (!empty($canEdit)): ?>
            <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/actions')) ?>">Editar acciones</a>
        <?php endif; ?>
    </div>
    <?php if (!empty($case['has_actions'])): ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Institución</th>
                        <th>Funcionario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($case['actions'] as $action): ?>
                        <tr>
                            <td><?= e(!empty($action['action_date']) ? date('d-m-Y', strtotime((string) $action['action_date'])) : '—') ?></td>
                            <td><?= e((string) ($action['action_time_short'] ?? '—')) ?></td>
                            <td><?= e((string) ($action['action_type_name'] ?? '—')) ?></td>
                            <td><?= e((string) ($action['description'] ?? '—')) ?></td>
                            <td><?= e((string) ($action['institution'] ?? '—')) ?></td>
                            <td><?= e((string) ($action['created_by_name'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-secondary mb-0">No hay acciones registradas en este caso.</p>
    <?php endif; ?>
</div>
