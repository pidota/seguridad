<section class="senda-hero">
    <div>
        <p class="welcome-kicker">Módulo operativo</p>
        <h2>Atención</h2>
        <p class="mb-0">Ingrese el RUT de la persona. Si ya fue atendida, se reutilizará su registro antes de elegir el tipo de atención.</p>
    </div>
</section>

<div class="page-card page-card--search senda-entry-rut">
    <form method="post" action="<?= e(url('/senda/lookup')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="rut">RUT de la persona</label>
            <input
                class="form-control <?= has_error('rut') ? 'is-invalid' : '' ?>"
                id="rut"
                name="rut"
                value="<?= e((string) ($rut ?? old('rut'))) ?>"
                placeholder="12.345.678-5 o 12345678-5"
                autocomplete="off"
                data-rut-input
                required
                autofocus
            >
            <?php if (has_error('rut')): ?>
                <div class="invalid-feedback"><?= e((string) error('rut')) ?></div>
            <?php else: ?>
                <div class="form-text">Acepta 12.345.678-5 o 12345678-5.</div>
            <?php endif; ?>
        </div>
        <button class="btn btn-navy" type="submit">Continuar</button>
    </form>
</div>

<?php if (($exists ?? null) === false): ?>
    <div class="page-card page-card--search mt-3">
        <p class="mb-2">No hay una persona registrada con el RUT <strong><?= e((string) $rut) ?></strong>.</p>
        <p class="text-secondary mb-3">Para continuar con la atención, debe registrar a la persona con ese RUT.</p>
        <?php if (hasPermission('senda.people.create')): ?>
            <a
                class="btn btn-navy"
                href="<?= e(url('/senda/people/create/form') . '?' . http_build_query([
                    'rut' => $rut,
                    'next' => 'attention',
                ])) ?>"
            >Registrar nueva persona</a>
        <?php endif; ?>
        <a class="btn btn-outline-navy" href="<?= e(url('/senda')) ?>">Buscar otro RUT</a>
    </div>
<?php endif; ?>
