<?php
$record = $record ?? [];
$person = $person ?? [];
$entryType = $entryType ?? [];
$isReferral = !empty($isReferral);
$personId = (int) ($person['id'] ?? $record['senda_person_id'] ?? 0);
$backUrl = $personId > 0 && hasPermission('senda.followups.view')
    ? url('/senda/follow-ups/person/' . $personId)
    : url('/senda/attentions');
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-1">Ver atención</h2>
        <?php if (!empty($record['attention_number'])): ?>
            <p class="mb-0"><span class="senda-badge senda-badge--referral"><?= e((string) $record['attention_number']) ?></span></p>
        <?php endif; ?>
    </div>
    <div class="page-toolbar__actions">
        <?php if (hasPermission('senda.attentions.edit')): ?>
            <a class="btn btn-navy" href="<?= e(url('/senda/attentions/' . $record['id'] . '/edit')) ?>">Editar</a>
        <?php endif; ?>
        <a class="btn btn-outline-navy" href="<?= e($backUrl) ?>">Volver</a>
    </div>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<?php if (!empty($person)): ?>
    <div class="mb-3">
        <?= \Core\View::make('senda/people/card', [
            'person' => $person,
            'showUse' => false,
            'compact' => true,
        ], null) ?>
    </div>
<?php endif; ?>

<div class="page-card page-card--lg">
    <div class="senda-entry-inline mb-4">
        <span class="senda-entry-bar__kicker">Tipo de ingreso</span>
        <span class="senda-badge senda-badge--<?= e((string) ($entryType['tone'] ?? 'referral')) ?>">
            <i class="bi <?= $isReferral ? 'bi-signpost-split' : 'bi-person-walking' ?>"></i>
            <?= e((string) ($entryType['label'] ?? '')) ?>
        </span>
    </div>
    <dl class="senda-person-card__meta senda-person-card__meta--wide">
        <div>
            <dt>Fecha</dt>
            <dd><?= e(!empty($record['attention_date']) ? date('d-m-Y', strtotime((string) $record['attention_date'])) : '—') ?></dd>
        </div>
        <div>
            <dt>Hora</dt>
            <dd><?= e((string) ($record['attention_time_short'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Funcionario</dt>
            <dd><?= e((string) ($record['created_by_name'] ?? '—')) ?></dd>
        </div>
        <?php if ($isReferral): ?>
            <div>
                <dt>Tipo de institución</dt>
                <dd><?= e((string) ($record['referral_institution_type_label'] ?? '—')) ?></dd>
            </div>
            <div>
                <dt>Institución</dt>
                <dd><?= e((string) ($record['referral_institution_name'] ?? '—')) ?></dd>
            </div>
            <div>
                <dt>Persona que deriva</dt>
                <dd><?= e((string) ($record['referral_person'] ?? '—')) ?></dd>
            </div>
        <?php endif; ?>
    </dl>
    <h3 class="page-card__title mt-4">Observaciones</h3>
    <?php if (trim((string) ($record['summary'] ?? '')) !== ''): ?>
        <p class="mb-0 text-pre-wrap"><?= e((string) $record['summary']) ?></p>
    <?php else: ?>
        <p class="text-secondary mb-0">Sin observaciones.</p>
    <?php endif; ?>
</div>

<?php if (hasPermission('senda.followups.view') || hasPermission('senda.followups.create')): ?>
    <?= \Core\View::make('senda/followups/history', [
        'followups' => $followups ?? [],
        'attentionId' => (int) $record['id'],
        'returnTo' => 'attention',
    ], null) ?>
<?php endif; ?>
