<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-0">Identificar persona</h2>
        <p class="text-secondary mb-0">Ingrese el RUT. Si la persona ya fue atendida, se reutilizará su registro.</p>
    </div>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<div class="page-card page-card--search">
    <form method="post" action="<?= e(url('/senda/people/lookup')) ?>" novalidate>
        <?= csrf_field() ?>
        <?php if (($next ?? '') !== ''): ?>
            <input type="hidden" name="next" value="<?= e((string) $next) ?>">
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label" for="rut">Ingrese RUT</label>
            <input
                class="form-control <?= has_error('rut') ? 'is-invalid' : '' ?>"
                id="rut"
                name="rut"
                value="<?= e((string) ($rut ?? old('rut'))) ?>"
                placeholder="12.345.678-5 o 12345678-5"
                autocomplete="off"
                data-rut-input
                required
            >
            <?php if (has_error('rut')): ?>
                <div class="invalid-feedback"><?= e((string) error('rut')) ?></div>
            <?php else: ?>
                <div class="form-text">Acepta 12.345.678-5 o 12345678-5.</div>
            <?php endif; ?>
        </div>
        <button class="btn btn-navy" type="submit">Buscar</button>
        <a class="btn btn-outline-navy" href="<?= e(url('/senda/people')) ?>">Cancelar</a>
    </form>
</div>

<?php if ($exists === true && !empty($found)): ?>
    <div class="mt-3">
        <?= \Core\View::make('senda/people/card', [
            'person' => $found,
            'showUse' => true,
            'next' => $next ?? '',
        ], null) ?>
    </div>
<?php elseif ($exists === false): ?>
    <div class="page-card page-card--search mt-3">
        <p class="mb-3">No hay una persona registrada con el RUT <strong><?= e((string) $rut) ?></strong>.</p>
        <?php if (hasPermission('senda.people.create')): ?>
            <a class="btn btn-navy" href="<?= e(url('/senda/people/create/form') . '?' . http_build_query(array_filter([
                'rut' => $rut,
                'next' => $next ?? '',
            ]))) ?>">Registrar nueva persona</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
