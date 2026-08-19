<?php
$case = $case ?? [];
$canEdit = !empty($canEdit);
$rows = $followUpRows ?? [];
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1"><?= e((string) ($case['case_number'] ?? 'Caso')) ?></p>
        <h2 class="page-card__title mb-0">9. Seguimientos</h2>
        <p class="text-secondary mb-0">Registre contactos, resultados y próximas fechas de seguimiento.</p>
    </div>
    <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . ($case['id'] ?? ''))) ?>">Ver caso</a>
</section>

<?= women_nav($womenNav ?? []) ?>

<?= \Core\View::make('women-office/cases/_steps', ['currentStep' => $currentStep ?? 9], null) ?>

<div class="page-card page-card--md">
    <?php if (!$canEdit): ?>
        <p class="text-secondary">Este caso no puede modificarse con su perfil actual.</p>
    <?php else: ?>
    <form method="post" action="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/follow-ups')) ?>" novalidate autocomplete="off" data-women-case-followups-form>
        <?= csrf_field() ?>

        <div class="alert alert-light border mb-4">
            Puede registrar uno o más seguimientos. El funcionario que crea cada registro nuevo queda identificado automáticamente.
        </div>

        <div data-women-followups-list>
            <?php foreach ($rows as $index => $row): ?>
                <?= \Core\View::make('women-office/cases/_follow_up_row', [
                    'index' => $index,
                    'row' => $row,
                    'contactTypes' => $contactTypes ?? [],
                    'followUpResults' => $followUpResults ?? [],
                ], null) ?>
            <?php endforeach; ?>
        </div>

        <button type="button" class="btn btn-outline-navy btn-sm mb-4" data-women-add-followup>Agregar seguimiento</button>

        <button class="btn btn-navy" type="submit">Guardar seguimientos</button>
        <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/referrals')) ?>">Volver</a>
    </form>
    <?php endif; ?>
</div>

<template data-women-followup-template>
    <?= \Core\View::make('women-office/cases/_follow_up_row', [
        'index' => '__INDEX__',
        'row' => [
            'follow_up_date' => date('Y-m-d'),
            'follow_up_time' => date('H:i'),
            'requires_follow_up' => 'no',
        ],
        'contactTypes' => $contactTypes ?? [],
        'followUpResults' => $followUpResults ?? [],
    ], null) ?>
</template>
