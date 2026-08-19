<?php

$case = $case ?? [];
$metrics = $metrics ?? [];
$tabUrl = $tabUrl ?? static fn (string $name): string => '#';
$lastAction = $lastAction ?? null;
$nextFollowUp = $nextFollowUp ?? null;

?>
<div class="women-case-header">
    <div class="women-case-header__metrics">
        <div class="women-metric-card">
            <span class="women-metric-card__label">Prioridad</span>
            <span class="women-metric-card__value">
                <?php if (!empty($case['priority'])): ?>
                    <span class="women-priority-badge women-priority-badge--<?= e((string) $case['priority']) ?>">
                        <?= e((string) ($case['priority_label'] ?? '—')) ?>
                    </span>
                <?php else: ?>
                    —
                <?php endif; ?>
            </span>
        </div>
        <div class="women-metric-card">
            <span class="women-metric-card__label">Última actuación</span>
            <span class="women-metric-card__value">
                <?= e((string) ($lastAction['datetime_label'] ?? '—')) ?>
            </span>
            <?php if (!empty($lastAction['title'])): ?>
                <span class="women-metric-card__hint"><?= e((string) $lastAction['title']) ?></span>
            <?php endif; ?>
        </div>
        <a class="women-metric-card women-metric-card--link" href="<?= e($tabUrl('seguimientos')) ?>">
            <span class="women-metric-card__label">Próximo seguimiento</span>
            <span class="women-metric-card__value">
                <?= e((string) ($nextFollowUp['lines'][0] ?? 'Sin seguimiento pendiente')) ?>
            </span>
        </a>
        <a class="women-metric-card women-metric-card--link" href="<?= e($tabUrl('acciones')) ?>">
            <span class="women-metric-card__label">Acciones</span>
            <span class="women-metric-card__value"><?= (int) ($metrics['actions_count'] ?? 0) ?></span>
        </a>
        <a class="women-metric-card women-metric-card--link" href="<?= e($tabUrl('derivaciones')) ?>">
            <span class="women-metric-card__label">Derivaciones</span>
            <span class="women-metric-card__value"><?= (int) ($metrics['referrals_count'] ?? 0) ?></span>
        </a>
        <a class="women-metric-card women-metric-card--link" href="<?= e($tabUrl('seguimientos')) ?>">
            <span class="women-metric-card__label">Seguimientos</span>
            <span class="women-metric-card__value"><?= (int) ($metrics['followups_count'] ?? 0) ?></span>
        </a>
    </div>
</div>
