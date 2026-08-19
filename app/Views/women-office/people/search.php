<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Oficina de la Mujer</p>
        <h2 class="page-card__title mb-0">Persona afectada</h2>
        <p class="text-secondary mb-0">Ingrese el RUT. Si la persona ya fue atendida, se reutilizará su registro.</p>
    </div>
</section>

<?= women_nav($womenNav ?? []) ?>

<div class="page-card page-card--search">
    <form method="post" action="<?= e(url('/women/people/lookup')) ?>" novalidate autocomplete="off">
        <?= csrf_field() ?>
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
        <a class="btn btn-outline-navy" href="<?= e(url('/women')) ?>">Cancelar</a>
    </form>
</div>

<?php if ($exists === true && !empty($found)): ?>
    <div class="mt-3">
        <?= \Core\View::make('women-office/people/card', [
            'person' => $found,
            'showUse' => true,
        ], null) ?>
    </div>
<?php elseif ($exists === false): ?>
    <div class="page-card page-card--search mt-3">
        <p class="mb-3">No hay una persona registrada con el RUT <strong><?= e((string) $rut) ?></strong>.</p>
        <?php if (hasPermission('women.people.create')): ?>
            <a class="btn btn-navy" href="<?= e(url('/women/people/create/form') . '?' . http_build_query(['rut' => $rut])) ?>">Registrar nueva persona</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
