<div class="senda-entry-bar">
    <div class="senda-entry-bar__info">
        <span class="senda-entry-bar__kicker">Tipo de ingreso actual</span>
        <span class="senda-badge senda-badge--<?= e($sendaEntry['tone']) ?>"><?= e($sendaEntry['label']) ?></span>
    </div>
    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/senda')) ?>">Cambiar tipo de ingreso</a>
</div>
