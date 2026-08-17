<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-0"><?= e((string) $record['full_name']) ?></h2>
    </div>
    <div class="page-toolbar__actions">
        <?php if (hasPermission('senda.followups.view')): ?>
            <a href="<?= e(url('/senda/follow-ups/person/' . $record['id'])) ?>" class="btn btn-outline-navy">Seguimiento SENDA</a>
        <?php endif; ?>
        <?php if (hasPermission('senda.people.edit')): ?>
            <a href="<?= e(url('/senda/people/' . $record['id'] . '/edit')) ?>" class="btn btn-outline-navy">Editar</a>
        <?php endif; ?>
        <?php if (hasPermission('senda.attentions.create')): ?>
            <form method="post" action="<?= e(url('/senda/people/' . $record['id'] . '/use')) ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="attention">
                <button type="submit" class="btn btn-navy">Utilizar esta persona</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<?= \Core\View::make('senda/people/card', ['person' => $record, 'showUse' => false, 'compact' => false], null) ?>

<div class="page-card mt-3">
    <h3 class="page-card__title">Datos de contacto</h3>
    <dl class="senda-person-card__meta">
        <div>
            <dt>Dirección</dt>
            <dd><?= e((string) ($record['address'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Correo</dt>
            <dd><?= e((string) ($record['email'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Escolaridad</dt>
            <dd><?= e((string) ($record['education'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Ocupación</dt>
            <dd><?= e((string) ($record['occupation'] ?? '—')) ?></dd>
        </div>
    </dl>
</div>

<?php if (trim((string) ($record['motivo'] ?? '')) !== '' || trim((string) ($record['orientaciones'] ?? '')) !== '' || trim((string) ($record['gestion'] ?? '')) !== ''): ?>
    <div class="page-card mt-3">
        <h3 class="page-card__title">Motivo, orientaciones y gestión</h3>
        <?php if (trim((string) ($record['motivo'] ?? '')) !== ''): ?>
            <div class="mb-3">
                <p class="welcome-kicker mb-1">Motivo</p>
                <p class="mb-0"><?= nl2br(e((string) $record['motivo'])) ?></p>
            </div>
        <?php endif; ?>
        <?php if (trim((string) ($record['orientaciones'] ?? '')) !== ''): ?>
            <div class="mb-3">
                <p class="welcome-kicker mb-1">Orientaciones</p>
                <p class="mb-0"><?= nl2br(e((string) $record['orientaciones'])) ?></p>
            </div>
        <?php endif; ?>
        <?php if (trim((string) ($record['gestion'] ?? '')) !== ''): ?>
            <div class="mb-0">
                <p class="welcome-kicker mb-1">Gestión</p>
                <p class="mb-0"><?= nl2br(e((string) $record['gestion'])) ?></p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?= \Core\View::make('senda/people/timeline', [
    'record' => $record,
    'history' => $history ?? [],
], null) ?>
