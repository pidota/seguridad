<?php
$case = $case ?? [];
$canEdit = !empty($canEdit);
$selectedRisk = old('risk_factor_ids', $case['risk_factor_ids'] ?? []);
if (!is_array($selectedRisk)) {
    $selectedRisk = [];
}
$selectedRisk = array_map('intval', $selectedRisk);
$riskOther = old('risk_other', []);
if (!is_array($riskOther)) {
    $riskOther = [];
}
foreach ($case['risk_factors'] ?? [] as $item) {
    $typeId = (int) ($item['risk_factor_id'] ?? 0);
    if ($typeId > 0 && !isset($riskOther[$typeId]) && !empty($item['other_text'])) {
        $riskOther[$typeId] = (string) $item['other_text'];
    }
}
$priority = (string) old('priority', (string) ($case['priority'] ?? ''));
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1"><?= e((string) ($case['case_number'] ?? 'Caso')) ?></p>
        <h2 class="page-card__title mb-0">5. Factores de riesgo y prioridad</h2>
        <p class="text-secondary mb-0">Registre factores informados u observados. La prioridad es operativa, no un diagnóstico clínico o jurídico.</p>
    </div>
    <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . ($case['id'] ?? ''))) ?>">Ver caso</a>
</section>

<?= women_nav($womenNav ?? []) ?>

<?= \Core\View::make('women-office/cases/_steps', ['currentStep' => $currentStep ?? 5], null) ?>

<div class="page-card page-card--md">
    <?php if (!$canEdit): ?>
        <p class="text-secondary">Este caso no puede modificarse con su perfil actual.</p>
    <?php else: ?>
    <form method="post" action="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/risk-priority')) ?>" novalidate autocomplete="off" data-women-case-risk-form>
        <?= csrf_field() ?>

        <div class="alert alert-light border mb-4">
            El sistema no calcula riesgo automáticamente. Registre antecedentes informados por la persona o observados por el funcionario.
        </div>

        <h3 class="h6 mb-3">Factores de riesgo informados / observados</h3>
        <?php if (has_error('risk_factor_ids')): ?>
            <div class="alert alert-danger py-2"><?= e((string) error('risk_factor_ids')) ?></div>
        <?php endif; ?>

        <div class="women-violence-grid mb-4">
            <?php foreach ($riskFactors ?? [] as $factor): ?>
                <?php
                $factorId = (int) $factor['id'];
                $checked = in_array($factorId, $selectedRisk, true);
                $isOther = ($factor['slug'] ?? '') === 'otro';
                ?>
                <div class="women-violence-option">
                    <label class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="risk_factor_ids[]"
                            value="<?= $factorId ?>"
                            data-slug="<?= e((string) $factor['slug']) ?>"
                            data-women-risk-toggle
                            <?= $checked ? 'checked' : '' ?>
                        >
                        <span class="form-check-label"><?= e((string) $factor['name']) ?></span>
                    </label>
                    <?php if ($isOther): ?>
                        <div class="women-violence-option__other" data-women-risk-other="<?= $factorId ?>" <?= $checked ? '' : 'hidden' ?>>
                            <input
                                class="form-control form-control-sm <?= has_error('risk_other_' . $factorId) ? 'is-invalid' : '' ?>"
                                type="text"
                                name="risk_other[<?= $factorId ?>]"
                                value="<?= e((string) ($riskOther[$factorId] ?? '')) ?>"
                                placeholder="Especifique"
                            >
                            <?php if (has_error('risk_other_' . $factorId)): ?>
                                <div class="invalid-feedback d-block"><?= e((string) error('risk_other_' . $factorId)) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <h3 class="h6 mb-3">Prioridad de atención</h3>
        <p class="text-secondary small">Clasificación operativa para ordenar la atención. No equivale a un diagnóstico de riesgo.</p>
        <div class="mb-3">
            <label class="form-label" for="priority">Prioridad</label>
            <select class="form-select <?= has_error('priority') ? 'is-invalid' : '' ?>" id="priority" name="priority">
                <option value="">Sin priorizar</option>
                <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Baja</option>
                <option value="medium" <?= $priority === 'medium' ? 'selected' : '' ?>>Media</option>
                <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>Alta</option>
                <option value="urgent" <?= $priority === 'urgent' ? 'selected' : '' ?>>Urgente</option>
            </select>
            <?php if (has_error('priority')): ?><div class="invalid-feedback"><?= e((string) error('priority')) ?></div><?php endif; ?>
        </div>

        <?php if (!empty($case['priority_assigned_by_name']) && !empty($case['priority_assigned_at'])): ?>
            <p class="text-secondary small mb-4">
                Asignada por <?= e((string) $case['priority_assigned_by_name']) ?>
                el <?= e(date('d-m-Y H:i', strtotime((string) $case['priority_assigned_at']))) ?>.
            </p>
        <?php endif; ?>

        <button class="btn btn-navy" type="submit">Guardar evaluación</button>
        <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/background')) ?>">Volver</a>
    </form>
    <?php endif; ?>
</div>
