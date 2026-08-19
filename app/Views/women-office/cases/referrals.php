<?php
$case = $case ?? [];
$canEdit = !empty($canEdit);
$rows = $referralRows ?? [];
$pendingStatusId = null;
foreach ($referralStatuses ?? [] as $status) {
    if (($status['slug'] ?? '') === 'pending') {
        $pendingStatusId = (int) $status['id'];
        break;
    }
}
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1"><?= e((string) ($case['case_number'] ?? 'Caso')) ?></p>
        <h2 class="page-card__title mb-0">8. Derivaciones</h2>
        <p class="text-secondary mb-0">Registre derivaciones a instituciones, programas o áreas de atención.</p>
    </div>
    <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . ($case['id'] ?? ''))) ?>">Ver caso</a>
</section>

<?= women_nav($womenNav ?? []) ?>

<?= \Core\View::make('women-office/cases/_steps', ['currentStep' => $currentStep ?? 8], null) ?>

<div class="page-card page-card--md">
    <?php if (!$canEdit): ?>
        <p class="text-secondary">Este caso no puede modificarse con su perfil actual.</p>
    <?php else: ?>
    <form method="post" action="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/referrals')) ?>" novalidate autocomplete="off" data-women-case-referrals-form>
        <?= csrf_field() ?>

        <div class="alert alert-light border mb-4">
            Puede registrar una o más derivaciones. El funcionario que crea cada derivación nueva queda registrado automáticamente.
        </div>

        <div data-women-referrals-list>
            <?php foreach ($rows as $index => $row): ?>
                <?= \Core\View::make('women-office/cases/_referral_row', [
                    'index' => $index,
                    'row' => $row,
                    'referralInstitutions' => $referralInstitutions ?? [],
                    'referralStatuses' => $referralStatuses ?? [],
                ], null) ?>
            <?php endforeach; ?>
        </div>

        <button type="button" class="btn btn-outline-navy btn-sm mb-4" data-women-add-referral>Agregar derivación</button>

        <button class="btn btn-navy" type="submit">Guardar derivaciones</button>
        <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/actions')) ?>">Volver</a>
    </form>
    <?php endif; ?>
</div>

<template data-women-referral-template>
    <?= \Core\View::make('women-office/cases/_referral_row', [
        'index' => '__INDEX__',
        'row' => [
            'referral_date' => date('Y-m-d'),
            'referral_status_id' => $pendingStatusId,
        ],
        'referralInstitutions' => $referralInstitutions ?? [],
        'referralStatuses' => $referralStatuses ?? [],
    ], null) ?>
</template>
