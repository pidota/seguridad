<section class="page-toolbar">    <div>
        <p class="welcome-kicker mb-1">Guardias Municipales</p>
        <h2 class="page-card__title mb-1">Iniciar turno</h2>
        <p class="text-secondary mb-0">Registre el inicio de su servicio en terreno.</p>
    </div>
</section>

<?= guards_nav($guardsNav ?? []) ?>

<form method="post" action="<?= e(url('/guards/shifts')) ?>" class="page-card">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="shift_date">Fecha del turno</label>
            <input
                type="date"
                class="form-control <?= has_error('shift_date') ? 'is-invalid' : '' ?>"
                id="shift_date"
                name="shift_date"
                value="<?= e((string) old('shift_date', date('Y-m-d'))) ?>"
                required
            >
            <?php if (has_error('shift_date')): ?>
                <div class="invalid-feedback"><?= e(error('shift_date')) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-12">
            <label class="form-label" for="opening_notes">Notas de apertura (opcional)</label>
            <textarea class="form-control" id="opening_notes" name="opening_notes" rows="3"><?= e((string) old('opening_notes', '')) ?></textarea>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-navy">Iniciar turno</button>
        <a href="<?= e(url('/guards')) ?>" class="btn btn-outline-navy">Cancelar</a>
    </div>
</form>
