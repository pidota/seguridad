<?php
$person = $person ?? [];
$showUse = !empty($showUse);
?>
<article class="women-person-card">
    <div class="women-person-card__body">
        <p class="welcome-kicker mb-1">Persona encontrada</p>
        <h3 class="women-person-card__name"><?= e((string) ($person['full_name'] ?? '')) ?></h3>
        <dl class="women-person-card__meta">
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
        </dl>
        <?php if (!empty($person['is_deleted'])): ?>
            <p class="small text-secondary mb-0">Este registro estaba inactivo. Al utilizarlo se reactivará.</p>
        <?php endif; ?>
    </div>
    <?php if ($showUse && !empty($person['id'])): ?>
        <form method="post" action="<?= e(url('/women/people/' . $person['id'] . '/use')) ?>" class="women-person-card__actions">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-navy">Utilizar esta persona</button>
        </form>
    <?php endif; ?>
</article>
