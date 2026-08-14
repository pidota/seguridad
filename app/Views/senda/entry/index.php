<section class="senda-hero">
    <div>
        <p class="welcome-kicker">Módulo operativo</p>
        <h2>Tipo de ingreso</h2>
        <p class="mb-0">Seleccione cómo llega la persona a SENDA. El valor quedará registrado de forma permanente al crear la atención.</p>
    </div>
</section>

<section class="senda-choice-grid" aria-label="Tipo de ingreso">
    <?php foreach ($options as $option): ?>
        <?php $selected = ($currentEntryType ?? null) === $option['value']; ?>
        <form method="post" action="<?= e(url('/senda/ingreso')) ?>" class="senda-choice-form">
            <?= csrf_field() ?>
            <input type="hidden" name="tipo_ingreso" value="<?= e($option['value']) ?>">
            <?php if (($next ?? '') === 'attention'): ?>
                <input type="hidden" name="next" value="attention">
            <?php endif; ?>
            <button type="submit" class="senda-choice-card senda-choice-card--<?= e($option['tone']) ?> <?= $selected ? 'is-current' : '' ?>">
                <span class="senda-choice-card__icon">
                    <i class="bi <?= e($option['icon']) ?>"></i>
                </span>
                <span class="senda-choice-card__body">
                    <span class="senda-choice-card__label"><?= e($option['label']) ?></span>
                    <span class="senda-choice-card__text"><?= e($option['description']) ?></span>
                </span>
                <?php if ($selected): ?>
                    <span class="senda-choice-card__hint">Seleccionado</span>
                <?php endif; ?>
            </button>
        </form>
    <?php endforeach; ?>
</section>
