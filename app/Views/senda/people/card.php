<?php
$person = $person ?? [];
$compact = !empty($compact);
$showUse = !empty($showUse);
$next = $next ?? '';
?>
<article class="senda-person-card">
    <div class="senda-person-card__body">
        <p class="senda-entry-bar__kicker mb-1">Persona</p>
        <h3 class="senda-person-card__name"><?= e((string) ($person['full_name'] ?? '')) ?></h3>
        <dl class="senda-person-card__meta">
            <div>
                <dt>RUT</dt>
                <dd><?= e((string) ($person['rut'] ?? '')) ?></dd>
            </div>
            <div>
                <dt>Fecha de nacimiento</dt>
                <dd><?= e(!empty($person['birth_date']) ? date('d-m-Y', strtotime((string) $person['birth_date'])) : '—') ?></dd>
            </div>
            <div>
                <dt>Edad</dt>
                <dd><?= $person['age'] !== null ? e((string) $person['age']) . ' años' : '—' ?></dd>
            </div>
            <?php if (!$compact): ?>
                <div>
                    <dt>Teléfono</dt>
                    <dd><?= e((string) ($person['phone'] ?? '—')) ?></dd>
                </div>
            <?php endif; ?>
        </dl>
        <?php if (!empty($person['is_deleted'])): ?>
            <p class="small text-secondary mb-0">Este registro estaba inactivo. Al utilizarlo se reactivará.</p>
        <?php endif; ?>
    </div>
    <?php if ($showUse && !empty($person['id'])): ?>
        <form method="post" action="<?= e(url('/senda/people/' . $person['id'] . '/use')) ?>" class="senda-person-card__actions">
            <?= csrf_field() ?>
            <?php if ($next !== ''): ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-navy">Utilizar esta persona</button>
        </form>
    <?php endif; ?>
</article>
