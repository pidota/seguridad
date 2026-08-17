<?php $isEdit = $record !== null; ?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-0"><?= $isEdit ? 'Editar persona' : 'Registrar nueva persona' ?></h2>
    </div>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<div class="page-card page-card--md">
    <form method="post" action="<?= e($isEdit ? url('/senda/people/' . $record['id']) : url('/senda/people')) ?>" novalidate>
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <?= method_field('PUT') ?>
        <?php endif; ?>
        <?php if (!$isEdit && ($next ?? '') !== ''): ?>
            <input type="hidden" name="next" value="<?= e((string) $next) ?>">
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label" for="rut">RUT</label>
            <input
                class="form-control <?= has_error('rut') ? 'is-invalid' : '' ?>"
                id="rut"
                name="rut"
                value="<?= e((string) old('rut', $isEdit ? (string) $record['rut'] : (string) ($rut ?? ''))) ?>"
                <?= $isEdit ? '' : 'readonly' ?>
                data-rut-input
                required
            >
            <?php if (has_error('rut')): ?><div class="invalid-feedback"><?= e((string) error('rut')) ?></div><?php endif; ?>
        </div>
        <div class="mb-3">
            <label class="form-label" for="first_names">Nombres</label>
            <input class="form-control <?= has_error('first_names') ? 'is-invalid' : '' ?>" id="first_names" name="first_names" value="<?= e((string) old('first_names', $isEdit ? (string) $record['first_names'] : '')) ?>" required>
            <?php if (has_error('first_names')): ?><div class="invalid-feedback"><?= e((string) error('first_names')) ?></div><?php endif; ?>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="paternal_surname">Apellido paterno</label>
                <input class="form-control <?= has_error('paternal_surname') ? 'is-invalid' : '' ?>" id="paternal_surname" name="paternal_surname" value="<?= e((string) old('paternal_surname', $isEdit ? (string) $record['paternal_surname'] : '')) ?>" required>
                <?php if (has_error('paternal_surname')): ?><div class="invalid-feedback"><?= e((string) error('paternal_surname')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="maternal_surname">Apellido materno</label>
                <input class="form-control <?= has_error('maternal_surname') ? 'is-invalid' : '' ?>" id="maternal_surname" name="maternal_surname" value="<?= e((string) old('maternal_surname', $isEdit ? (string) ($record['maternal_surname'] ?? '') : '')) ?>">
                <?php if (has_error('maternal_surname')): ?><div class="invalid-feedback"><?= e((string) error('maternal_surname')) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="birth_date">Fecha de nacimiento</label>
            <input type="date" class="form-control <?= has_error('birth_date') ? 'is-invalid' : '' ?>" id="birth_date" name="birth_date" value="<?= e((string) old('birth_date', $isEdit ? (string) $record['birth_date'] : '')) ?>" required>
            <?php if (has_error('birth_date')): ?><div class="invalid-feedback"><?= e((string) error('birth_date')) ?></div><?php endif; ?>
            <?php if ($isEdit && $record['age'] !== null): ?>
                <div class="form-text">Edad actual: <?= e((string) $record['age']) ?> años (calculada desde la fecha de nacimiento).</div>
            <?php else: ?>
                <div class="form-text">La edad se calcula desde la fecha de nacimiento; no se almacena.</div>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <label class="form-label" for="address">Dirección</label>
            <input class="form-control <?= has_error('address') ? 'is-invalid' : '' ?>" id="address" name="address" value="<?= e((string) old('address', $isEdit ? (string) ($record['address'] ?? '') : '')) ?>">
            <?php if (has_error('address')): ?><div class="invalid-feedback"><?= e((string) error('address')) ?></div><?php endif; ?>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="phone">Teléfono</label>
                <input class="form-control <?= has_error('phone') ? 'is-invalid' : '' ?>" id="phone" name="phone" value="<?= e((string) old('phone', $isEdit ? (string) ($record['phone'] ?? '') : '')) ?>">
                <?php if (has_error('phone')): ?><div class="invalid-feedback"><?= e((string) error('phone')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="email">Correo electrónico</label>
                <input type="email" class="form-control <?= has_error('email') ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= e((string) old('email', $isEdit ? (string) ($record['email'] ?? '') : '')) ?>">
                <?php if (has_error('email')): ?><div class="invalid-feedback"><?= e((string) error('email')) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="education">Escolaridad</label>
                <input class="form-control <?= has_error('education') ? 'is-invalid' : '' ?>" id="education" name="education" value="<?= e((string) old('education', $isEdit ? (string) ($record['education'] ?? '') : '')) ?>">
                <?php if (has_error('education')): ?><div class="invalid-feedback"><?= e((string) error('education')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6 mb-4">
                <label class="form-label" for="occupation">Ocupación</label>
                <input class="form-control <?= has_error('occupation') ? 'is-invalid' : '' ?>" id="occupation" name="occupation" value="<?= e((string) old('occupation', $isEdit ? (string) ($record['occupation'] ?? '') : '')) ?>">
                <?php if (has_error('occupation')): ?><div class="invalid-feedback"><?= e((string) error('occupation')) ?></div><?php endif; ?>
            </div>
        </div>

        <?php if (!$isEdit): ?>
            <div class="mb-3">
                <label class="form-label" for="motivo">Motivo</label>
                <textarea
                    class="form-control <?= has_error('motivo') ? 'is-invalid' : '' ?>"
                    id="motivo"
                    name="motivo"
                    rows="3"
                    maxlength="5000"
                ><?= e((string) old('motivo')) ?></textarea>
                <?php if (has_error('motivo')): ?><div class="invalid-feedback"><?= e((string) error('motivo')) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="orientaciones">Orientaciones</label>
                <textarea
                    class="form-control <?= has_error('orientaciones') ? 'is-invalid' : '' ?>"
                    id="orientaciones"
                    name="orientaciones"
                    rows="3"
                    maxlength="5000"
                ><?= e((string) old('orientaciones')) ?></textarea>
                <?php if (has_error('orientaciones')): ?><div class="invalid-feedback"><?= e((string) error('orientaciones')) ?></div><?php endif; ?>
            </div>
            <div class="mb-4">
                <label class="form-label" for="gestion">Gestión</label>
                <textarea
                    class="form-control <?= has_error('gestion') ? 'is-invalid' : '' ?>"
                    id="gestion"
                    name="gestion"
                    rows="3"
                    maxlength="5000"
                ><?= e((string) old('gestion')) ?></textarea>
                <?php if (has_error('gestion')): ?><div class="invalid-feedback"><?= e((string) error('gestion')) ?></div><?php endif; ?>
            </div>
        <?php endif; ?>
        <button class="btn btn-navy" type="submit">Guardar</button>
        <a class="btn btn-outline-navy" href="<?= e($isEdit ? url('/senda/people/' . $record['id']) : (($next ?? '') === 'attention' ? url('/senda') : url('/senda/people/create'))) ?>">Cancelar</a>
    </form>
</div>
