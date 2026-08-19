<div class="page-card">
    <h3 class="page-card__title">Resumen del caso</h3>
    <dl class="women-case-summary">
        <div>
            <dt>Persona afectada</dt>
            <dd><?= e((string) ($case['person_full_name'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Canal de ingreso</dt>
            <dd>
                <?= e((string) ($case['report_channel_name'] ?? '—')) ?>
                <?php if (!empty($case['report_channel_other'])): ?>
                    — <?= e((string) $case['report_channel_other']) ?>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt>Funcionario registrador</dt>
            <dd><?= e((string) ($case['created_by_name'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Prioridad operativa</dt>
            <dd>
                <?php if (!empty($case['priority'])): ?>
                    <span class="women-priority-badge women-priority-badge--<?= e((string) $case['priority']) ?>">
                        <?= e((string) ($case['priority_label'] ?? '—')) ?>
                    </span>
                <?php else: ?>
                    —
                <?php endif; ?>
            </dd>
        </div>
        <div class="women-case-summary__wide">
            <dt>Avance del registro</dt>
            <dd>
                <ul class="women-tag-list mb-0">
                    <li><?= !empty($case['has_facts']) ? 'Hechos registrados' : 'Hechos pendientes' ?></li>
                    <li><?= !empty($case['has_aggressor']) ? 'Persona denunciada registrada' : 'Persona denunciada pendiente' ?></li>
                    <li><?= !empty($case['has_background']) ? 'Antecedentes registrados' : 'Antecedentes pendientes' ?></li>
                    <li><?= !empty($case['has_risk_assessment']) ? 'Riesgo evaluado' : 'Riesgo pendiente' ?></li>
                    <li><?= !empty($case['has_support_context']) ? 'Medidas y necesidades registradas' : 'Medidas pendientes' ?></li>
                </ul>
            </dd>
        </div>
    </dl>
</div>
