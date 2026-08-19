<?php if (!empty($case['has_background'])): ?>
<div class="page-card">
    <h3 class="page-card__title">Antecedentes de recurrencia y denuncias</h3>
    <dl class="women-case-summary">
        <div>
            <dt>¿Primera vez?</dt>
            <dd><?= e((string) ($case['is_first_occurrence_label'] ?? '—')) ?></dd>
        </div>
        <?php if (($case['is_first_occurrence'] ?? '') === 'no'): ?>
            <div>
                <dt>Frecuencia</dt>
                <dd><?= e((string) ($case['occurrence_frequency'] ?? '—')) ?></dd>
            </div>
            <div>
                <dt>Desde cuándo</dt>
                <dd><?= e((string) ($case['occurring_since'] ?? '—')) ?></dd>
            </div>
        <?php endif; ?>
        <div>
            <dt>Denuncias anteriores</dt>
            <dd><?= e((string) ($case['has_previous_reports_label'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Denuncia formal actual</dt>
            <dd><?= e((string) ($case['has_formal_current_report_label'] ?? '—')) ?></dd>
        </div>
    </dl>

    <?php if (($case['previous_reports'] ?? []) !== []): ?>
        <div class="women-case-description">
            <h4 class="h6">Antecedentes de denuncias anteriores</h4>
            <ul class="women-tag-list mb-0">
                <?php foreach ($case['previous_reports'] as $report): ?>
                    <li>
                        <strong><?= e((string) ($report['institution_name'] ?? '')) ?></strong>
                        <?php if (!empty($report['report_date'])): ?>
                            · <?= e(date('d-m-Y', strtotime((string) $report['report_date']))) ?>
                        <?php endif; ?>
                        <?php if (!empty($report['reference_number'])): ?>
                            · N.º <?= e((string) $report['reference_number']) ?>
                        <?php endif; ?>
                        <?php if (!empty($report['notes'])): ?>
                            — <?= e((string) $report['notes']) ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php $formal = $case['formal_report'] ?? null; ?>
    <?php if (is_array($formal)): ?>
        <div class="women-case-description">
            <h4 class="h6">Denuncia formal del hecho actual</h4>
            <dl class="women-case-summary">
                <div>
                    <dt>Institución</dt>
                    <dd>
                        <?= e((string) ($formal['institution_name'] ?? '—')) ?>
                        <?php if (!empty($formal['institution_other'])): ?>
                            — <?= e((string) $formal['institution_other']) ?>
                        <?php endif; ?>
                    </dd>
                </div>
                <?php if (!empty($formal['reference_number'])): ?>
                    <div>
                        <dt>N.º denuncia / parte</dt>
                        <dd><?= e((string) $formal['reference_number']) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (!empty($formal['report_date'])): ?>
                    <div>
                        <dt>Fecha</dt>
                        <dd><?= e(date('d-m-Y', strtotime((string) $formal['report_date']))) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
            <?php if (!empty($formal['notes'])): ?>
                <p class="mb-0"><?= nl2br(e((string) $formal['notes'])) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($case['occurrence_notes']) && ($case['is_first_occurrence'] ?? '') === 'no'): ?>
        <div class="women-case-description">
            <h4 class="h6">Observaciones de recurrencia</h4>
            <p class="mb-0"><?= nl2br(e((string) $case['occurrence_notes'])) ?></p>
        </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="page-card mb-3">
    <p class="text-secondary mb-0">Aún no se registran antecedentes de recurrencia ni denuncias.</p>
</div>
<?php endif; ?>

<?php if (!empty($case['has_risk_assessment'])): ?>
<div class="page-card mt-3">
    <h3 class="page-card__title">Factores de riesgo y prioridad</h3>
    <dl class="women-case-summary">
        <div>
            <dt>Prioridad de atención</dt>
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
        <?php if (!empty($case['priority_assigned_by_name'])): ?>
            <div>
                <dt>Asignada por</dt>
                <dd><?= e((string) $case['priority_assigned_by_name']) ?></dd>
            </div>
        <?php endif; ?>
        <?php if (!empty($case['priority_assigned_at'])): ?>
            <div>
                <dt>Fecha asignación</dt>
                <dd><?= e(date('d-m-Y H:i', strtotime((string) $case['priority_assigned_at']))) ?></dd>
            </div>
        <?php endif; ?>
        <div class="women-case-summary__wide">
            <dt>Factores informados</dt>
            <dd>
                <?php if (($case['risk_factors'] ?? []) === []): ?>
                    —
                <?php else: ?>
                    <ul class="women-tag-list mb-0">
                        <?php foreach ($case['risk_factors'] as $factor): ?>
                            <li>
                                <?= e((string) ($factor['risk_factor_name'] ?? '')) ?>
                                <?php if (!empty($factor['other_text'])): ?>
                                    (<?= e((string) $factor['other_text']) ?>)
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </dd>
        </div>
    </dl>
</div>
<?php endif; ?>

<?php if (!empty($case['has_support_context'])): ?>
<div class="page-card mt-3">
    <h3 class="page-card__title">Medidas, necesidades y contexto</h3>
    <dl class="women-case-summary">
        <div>
            <dt>Medidas de protección informadas</dt>
            <dd><?= e((string) ($case['has_protective_measures_label'] ?? '—')) ?></dd>
        </div>
        <?php if (($case['protective_measures'] ?? []) !== []): ?>
            <div class="women-case-summary__wide">
                <dt>Detalle de medidas</dt>
                <dd>
                    <ul class="women-tag-list mb-0">
                        <?php foreach ($case['protective_measures'] as $measure): ?>
                            <li>
                                <?= e((string) ($measure['measure_type_name'] ?? 'Medida sin tipo')) ?>
                                <?php if (!empty($measure['institution'])): ?>
                                    — <?= e((string) $measure['institution']) ?>
                                <?php endif; ?>
                                <?php if (!empty($measure['cause_number'])): ?>
                                    (<?= e((string) $measure['cause_number']) ?>)
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </dd>
            </div>
        <?php endif; ?>
        <div class="women-case-summary__wide">
            <dt>Necesidades identificadas</dt>
            <dd>
                <?php if (($case['needs'] ?? []) === []): ?>
                    —
                <?php else: ?>
                    <ul class="women-tag-list mb-0">
                        <?php foreach ($case['needs'] as $need): ?>
                            <li>
                                <?= e((string) ($need['need_name'] ?? '')) ?>
                                <?php if (!empty($need['other_text'])): ?>
                                    (<?= e((string) $need['other_text']) ?>)
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt>NNA vinculados</dt>
            <dd><?= e((string) ($case['has_linked_minors_label'] ?? '—')) ?></dd>
        </div>
        <?php if (($case['linked_minors'] ?? []) !== []): ?>
            <div class="women-case-summary__wide">
                <dt>Registro de NNA</dt>
                <dd>
                    <ul class="women-tag-list mb-0">
                        <?php foreach ($case['linked_minors'] as $minor): ?>
                            <li>
                                <?= e((string) ($minor['age_range_name'] ?? 'Rango no informado')) ?>
                                · <?= e((string) ($minor['gender_label'] ?? '—')) ?>
                                <?php if (!empty($minor['notes'])): ?>
                                    (<?= e((string) $minor['notes']) ?>)
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </dd>
            </div>
        <?php endif; ?>
        <div>
            <dt>Personas dependientes</dt>
            <dd><?= e((string) ($case['has_dependents_label'] ?? '—')) ?></dd>
        </div>
        <?php if (!empty($case['dependents_count'])): ?>
            <div>
                <dt>Cantidad dependientes</dt>
                <dd><?= e((string) $case['dependents_count']) ?></dd>
            </div>
        <?php endif; ?>
        <?php if (!empty($case['dependents_notes'])): ?>
            <div class="women-case-summary__wide">
                <dt>Observaciones dependientes</dt>
                <dd><?= nl2br(e((string) $case['dependents_notes'])) ?></dd>
            </div>
        <?php endif; ?>
    </dl>
</div>
<?php endif; ?>
