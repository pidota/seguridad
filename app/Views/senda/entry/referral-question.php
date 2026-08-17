<?php
$person = $person ?? [];
$entryType = $entryType ?? null;
?>
<section class="senda-hero">
    <div>
        <p class="welcome-kicker">Módulo operativo</p>
        <h2>Atención</h2>
        <p class="mb-0">Después de elegir el tipo de atención, indique si la persona requiere completar la ficha de referencia.</p>
    </div>
</section>

<?php if ($person !== []): ?>
    <div class="mb-4">
        <?= \Core\View::make('senda/people/card', [
            'person' => $person,
            'showUse' => false,
            'compact' => true,
        ], null) ?>
        <p class="mt-2 mb-0">
            <a href="<?= e(url('/senda')) ?>">Cambiar persona</a>
            ·
            <a href="<?= e(\App\Services\Senda\EntryFlowContext::attentionTypesUrl()) ?>">Cambiar tipo de atención</a>
        </p>
    </div>
<?php endif; ?>

<?php if (is_array($entryType) && !empty($entryType['label'])): ?>
    <div class="alert alert-light border mb-4" role="status">
        Tipo de atención seleccionado: <strong><?= e((string) $entryType['label']) ?></strong>
    </div>
<?php endif; ?>

<div class="page-card page-card--lg">
    <h3 class="page-card__title h5 mb-3">¿Requiere ficha de referencia?</h3>
    <p class="text-secondary mb-4">
        La ficha de referencia asistida a tratamiento se utiliza cuando la persona debe ser derivada o evaluada formalmente.
        Si no aplica, continúe directamente con el registro de la atención.
    </p>

    <form method="post" action="<?= e(url('/senda/referral-decision')) ?>" class="d-flex flex-wrap gap-2">
        <?= csrf_field() ?>
        <button class="btn btn-navy" type="submit" name="requires_referral" value="1">
            Sí, completar ficha
        </button>
        <button class="btn btn-outline-navy" type="submit" name="requires_referral" value="0">
            No, continuar
        </button>
    </form>
</div>
