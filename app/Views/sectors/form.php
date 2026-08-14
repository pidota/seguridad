<?php
$isEdit = $record !== null;
$action = $isEdit ? url('/sectors/' . $record['id']) : url('/sectors');
$isActive = (string) old('is_active', $isEdit ? (string) ($record['is_active'] ?? '1') : '1') === '1';
?>
<div class="page-card">
    <h2 class="page-card__title"><?= $isEdit ? 'Editar sector' : 'Nuevo sector' ?></h2>
    <p class="text-secondary">Los sectores activos aparecen en los formularios de CCTV. El identificador no se modifica después de crear el registro.</p>

    <form method="post" action="<?= e($action) ?>" class="mt-4" novalidate>
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <?= method_field('PUT') ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="name">Nombre</label>
                <input type="text" class="form-control <?= has_error('name') ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e((string) old('name', $record['name'] ?? '')) ?>" required>
                <?php if (has_error('name')): ?><div class="invalid-feedback"><?= e((string) error('name')) ?></div><?php endif; ?>
            </div>
            <?php if (!$isEdit): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="slug">Identificador (opcional)</label>
                    <input type="text" class="form-control <?= has_error('slug') ? 'is-invalid' : '' ?>" id="slug" name="slug" value="<?= e((string) old('slug')) ?>" placeholder="se genera desde el nombre">
                    <?php if (has_error('slug')): ?><div class="invalid-feedback"><?= e((string) error('slug')) ?></div><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Identificador</label>
                    <input type="text" class="form-control" value="<?= e((string) ($record['slug'] ?? '')) ?>" disabled>
                </div>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="description">Descripción</label>
            <input type="text" class="form-control <?= has_error('description') ? 'is-invalid' : '' ?>" id="description" name="description" value="<?= e((string) old('description', $record['description'] ?? '')) ?>">
            <?php if (has_error('description')): ?><div class="invalid-feedback"><?= e((string) error('description')) ?></div><?php endif; ?>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label" for="sort_order">Orden</label>
                <input type="number" min="0" class="form-control <?= has_error('sort_order') ? 'is-invalid' : '' ?>" id="sort_order" name="sort_order" value="<?= e((string) old('sort_order', (string) ($record['sort_order'] ?? '0'))) ?>">
                <?php if (has_error('sort_order')): ?><div class="invalid-feedback"><?= e((string) error('sort_order')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-8 mb-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Sector activo</label>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-navy">Guardar</button>
        <a href="<?= e(url('/sectors')) ?>" class="btn btn-outline-navy">Cancelar</a>
    </form>
</div>
