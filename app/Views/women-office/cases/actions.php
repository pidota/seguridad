<?php
$case = $case ?? [];
$canEdit = !empty($canEdit);
$rows = $actionRows ?? [];
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1"><?= e((string) ($case['case_number'] ?? 'Caso')) ?></p>
        <h2 class="page-card__title mb-0">7. Acciones realizadas</h2>
        <p class="text-secondary mb-0">Registre orientaciones, contenciones, derivaciones y otras acciones de atención.</p>
    </div>
    <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . ($case['id'] ?? ''))) ?>">Ver caso</a>
</section>

<?= women_nav($womenNav ?? []) ?>

<?= \Core\View::make('women-office/cases/_steps', ['currentStep' => $currentStep ?? 7], null) ?>

<div class="page-card page-card--md">
    <?php if (!$canEdit): ?>
        <p class="text-secondary">Este caso no puede modificarse con su perfil actual.</p>
    <?php else: ?>
    <form method="post" action="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/actions')) ?>" novalidate autocomplete="off" data-women-case-actions-form>
        <?= csrf_field() ?>

        <div class="alert alert-light border mb-4">
            Puede registrar una o más acciones. El funcionario que crea cada acción nueva queda registrado automáticamente.
        </div>

        <div data-women-actions-list>
            <?php foreach ($rows as $index => $row): ?>
                <?= \Core\View::make('women-office/cases/_action_row', [
                    'index' => $index,
                    'row' => $row,
                    'actionTypes' => $actionTypes ?? [],
                ], null) ?>
            <?php endforeach; ?>
        </div>

        <button type="button" class="btn btn-outline-navy btn-sm mb-4" data-women-add-action>Agregar acción</button>

        <button class="btn btn-navy" type="submit">Guardar acciones</button>
        <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/support')) ?>">Volver</a>
    </form>
    <?php endif; ?>
</div>

<template data-women-action-template>
    <?= \Core\View::make('women-office/cases/_action_row', [
        'index' => '__INDEX__',
        'row' => [
            'action_date' => date('Y-m-d'),
            'action_time' => date('H:i'),
        ],
        'actionTypes' => $actionTypes ?? [],
    ], null) ?>
</template>
