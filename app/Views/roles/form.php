<?php
$isEdit = $record !== null;
$action = $isEdit ? url('/roles/' . $record['id']) : url('/roles');
$lockedPermissions = $isEdit && ($record['slug'] ?? '') === 'superadministrador';
?>
<div class="page-card">
    <h2 class="page-card__title"><?= $isEdit ? 'Editar rol' : 'Nuevo rol' ?></h2>
    <p class="text-secondary">
        <?= $lockedPermissions
            ? 'El superadministrador tiene acceso total. Los permisos de este rol no se editan desde la interfaz.'
            : 'Marque los permisos que tendrá este rol. Las rutas validan estos permisos aunque el botón no se muestre.' ?>
    </p>

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
            <?php endif; ?>
        </div>

        <div class="mb-4">
            <label class="form-label" for="description">Descripción</label>
            <input type="text" class="form-control" id="description" name="description" value="<?= e((string) old('description', $record['description'] ?? '')) ?>">
        </div>

        <?php if (!$lockedPermissions): ?>
            <fieldset class="mb-4">
                <legend class="form-label">Permisos</legend>
                <?php foreach ($grouped as $module => $items): ?>
                    <div class="perm-group">
                        <h3><?= e(permission_module_label($module)) ?></h3>
                        <div class="check-grid">
                            <?php foreach ($items as $permission): ?>
                                <label class="check-card">
                                    <input type="checkbox" name="permission_ids[]" value="<?= (int) $permission['id'] ?>" <?= in_array((int) $permission['id'], $selectedPermissionIds, true) ? 'checked' : '' ?>>
                                    <strong><?= e($permission['name']) ?></strong>
                                    <small><code><?= e($permission['slug']) ?></code></small>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </fieldset>
        <?php endif; ?>

        <button type="submit" class="btn btn-navy">Guardar</button>
        <a href="<?= e(url('/roles')) ?>" class="btn btn-outline-navy">Cancelar</a>
    </form>
</div>
