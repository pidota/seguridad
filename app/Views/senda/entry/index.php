<section class="senda-hero">
    <div>
        <p class="welcome-kicker">Módulo operativo</p>
        <h2>Atención</h2>
        <p class="mb-0">Seleccione el tipo de atención que realizará para la persona identificada.</p>
    </div>
</section>

<?php if (!empty($person)): ?>
    <div class="mb-4">
        <?= \Core\View::make('senda/people/card', [
            'person' => $person,
            'showUse' => false,
            'compact' => true,
        ], null) ?>
        <p class="mt-2 mb-0">
            <a href="<?= e(url('/senda')) ?>">Cambiar persona</a>
        </p>
    </div>
<?php endif; ?>

<section class="senda-choice-grid" aria-label="Opciones de atención">
    <?php foreach ($options as $option): ?>
        <?php
            $selected = ($currentEntryType ?? null) === $option['value'];
            $isFollowUp = ($option['menu_action'] ?? '') === 'followup';
        ?>
        <form
            method="post"
            action="<?= e(url('/senda/ingreso')) ?>"
            class="senda-choice-form"
            data-menu-action="<?= e((string) ($option['menu_action'] ?? 'entry')) ?>"
        >
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
                <?php if ($selected && !$isFollowUp): ?>
                    <span class="senda-choice-card__hint">Seleccionado</span>
                <?php endif; ?>
            </button>
        </form>
    <?php endforeach; ?>
</section>
