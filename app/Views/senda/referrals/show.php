<?php
$record = $record ?? [];
$person = $person ?? [];
$attention = $attention ?? [];
$personId = (int) ($person['id'] ?? $record['senda_person_id'] ?? 0);
$backUrl = $personId > 0 && hasPermission('senda.followups.view')
    ? url('/senda/follow-ups/person/' . $personId)
    : url('/senda/referrals');
$classifier = new \App\Services\Senda\AssistClassificationService();
$assistItems = [];
foreach ($assistSubstances ?? [] as $substance) {
    $row = is_array($record['assist'][$substance['key']] ?? null) ? $record['assist'][$substance['key']] : [];
    $assistItems[] = [
        'label' => $substance['label'],
        'score' => (string) ($row['score'] ?? ''),
        'risk_label' => $classifier->label((string) ($row['risk_level'] ?? '')),
    ];
}
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-1">Ver ficha de referencia</h2>
        <?php if (!empty($attention['attention_number'])): ?>
            <p class="mb-0">Atención <span class="senda-badge senda-badge--referral"><?= e((string) $attention['attention_number']) ?></span></p>
        <?php endif; ?>
    </div>
    <div class="page-toolbar__actions">
        <?php if (hasPermission('senda.referrals.edit')): ?>
            <a class="btn btn-navy" href="<?= e(url('/senda/referrals/' . $record['id'] . '/edit')) ?>">Editar</a>
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
            'compact' => false,
        ], null) ?>
    </div>
<?php endif; ?>

<div class="page-card page-card--xl">
    <dl class="senda-person-card__meta senda-person-card__meta--wide">
        <div>
            <dt>Fecha de ficha</dt>
            <dd><?= e(!empty($record['request_date']) ? date('d-m-Y', strtotime((string) $record['request_date'])) : '—') ?></dd>
        </div>
        <div>
            <dt>Estado</dt>
            <dd><?= e((string) ($record['status_label'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Origen de la demanda</dt>
            <dd><?= e((string) ($record['demand_origin_label'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Tamizaje aplicado</dt>
            <dd><?= ($record['screening_used'] ?? '') === 'si' ? 'Sí' : 'No' ?></dd>
        </div>
        <div>
            <dt>Funcionario</dt>
            <dd><?= e((string) ($record['created_by_name'] ?? $record['receiving_officer'] ?? '—')) ?></dd>
        </div>
    </dl>

    <?php if (($record['screening_used'] ?? '') === 'si'): ?>
        <div class="mt-4">
            <?= \Core\View::make('senda/followups/assist-table', ['items' => $assistItems], null) ?>
        </div>
    <?php endif; ?>
</div>
