<?php $openShift = $openShift ?? []; ?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Turno activo</p>
        <h2 class="page-card__title mb-1">Finalizar turno</h2>
        <p class="text-secondary mb-0">Inicio: <?= e((string) ($openShift['started_at_formatted'] ?? '—')) ?></p>
    </div>
</section>

<?= guards_nav($guardsNav ?? []) ?>

<form method="post" action="<?= e(url('/guards/shifts/close')) ?>" class="page-card">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="closing_notes">Notas de cierre (opcional)</label>
        <textarea class="form-control" id="closing_notes" name="closing_notes" rows="4"><?= e((string) old('closing_notes', '')) ?></textarea>
    </div>
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-danger">Finalizar turno</button>
        <a href="<?= e(url('/guards')) ?>" class="btn btn-outline-navy">Cancelar</a>
    </div>
</form>
