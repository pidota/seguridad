<?php
$attention = $attention ?? [];
$person = $person ?? [];
$returnTo = in_array($returnTo ?? '', ['attention', 'history'], true) ? (string) $returnTo : '';
$attentionId = (int) ($attention['id'] ?? $record['senda_attention_id'] ?? 0);
$personId = (int) ($person['id'] ?? 0);
$backUrl = match ($returnTo) {
    'history' => $personId > 0 ? url('/senda/follow-ups/person/' . $personId) : url('/senda/follow-ups'),
    'attention' => $attentionId > 0 ? url('/senda/attentions/' . $attentionId . '/edit') : url('/senda/follow-ups'),
    default => url('/senda/follow-ups') . ($attentionId > 0 ? '?attention=' . $attentionId : ''),
};
$backLabel = match ($returnTo) {
    'history' => 'Volver al seguimiento',
    'attention' => 'Volver a la atención',
    default => 'Volver al listado',
};
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-1">Seguimiento</h2>
        <?php if (!empty($attention['attention_number'])): ?>
            <p class="mb-0">
                Atención
                <span class="senda-badge senda-badge--referral"><?= e((string) $attention['attention_number']) ?></span>
                <?php if (!empty($person['full_name'])): ?>
                    · <?= e((string) $person['full_name']) ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
    <a class="btn btn-outline-navy" href="<?= e($backUrl) ?>"><?= e($backLabel) ?></a>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<div class="page-card page-card--xl">
    <dl class="senda-person-card__meta senda-person-card__meta--wide">
        <div>
            <dt>Fecha</dt>
            <dd><?= e(!empty($record['follow_up_date']) ? date('d-m-Y', strtotime((string) $record['follow_up_date'])) : '—') ?></dd>
        </div>
        <div>
            <dt>Hora</dt>
            <dd><?= e((string) ($record['follow_up_time'] !== '' ? $record['follow_up_time'] : '—')) ?></dd>
        </div>
        <div>
            <dt>Tipo de contacto</dt>
            <dd><?= e((string) ($record['contact_type_label'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Resultado</dt>
            <dd><?= e((string) ($record['result_label'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Funcionario</dt>
            <dd><?= e((string) ($record['created_by_name'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>¿Requiere nuevo seguimiento?</dt>
            <dd><?= e((string) ($record['requires_follow_up_label'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Próximo seguimiento</dt>
            <dd>
                <?php if (($record['requires_follow_up'] ?? '') === 'si' && !empty($record['next_follow_up_date'])): ?>
                    <?= e(date('d-m-Y', strtotime((string) $record['next_follow_up_date']))) ?>
                <?php else: ?>
                    —
                <?php endif; ?>
            </dd>
        </div>
    </dl>

    <h3 class="page-card__title mt-4">Observaciones del seguimiento</h3>
    <?php if (trim((string) ($record['notes'] ?? '')) !== ''): ?>
        <p class="mb-4 text-pre-wrap"><?= e((string) $record['notes']) ?></p>
    <?php else: ?>
        <p class="text-secondary mb-4">Sin observaciones.</p>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2">
        <?php if (hasPermission('senda.followups.edit')): ?>
            <a class="btn btn-navy" href="<?= e(url('/senda/follow-ups/' . $record['id'] . '/edit') . ($returnTo !== '' ? '?return=' . rawurlencode($returnTo) : '')) ?>">Editar</a>
        <?php endif; ?>
        <?php if (hasPermission('senda.followups.delete')): ?>
            <form method="post" action="<?= e(url('/senda/follow-ups/' . $record['id'])) ?>" class="d-inline" data-confirm="Esta acción anulará el seguimiento." data-confirm-title="Eliminar seguimiento">
                <?= csrf_field() ?>
                <?= method_field('DELETE') ?>
                <?php if ($returnTo !== ''): ?>
                    <input type="hidden" name="return" value="<?= e($returnTo) ?>">
                <?php endif; ?>
                <button type="submit" class="btn btn-outline-navy">Eliminar</button>
            </form>
        <?php endif; ?>
        <a class="btn btn-outline-navy" href="<?= e($backUrl) ?>"><?= e($backLabel) ?></a>
    </div>
</div>
