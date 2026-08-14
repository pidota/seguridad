<?php
$isEdit = $record !== null;
$action = $isEdit ? url('/users/' . $record['id']) : url('/users');
?>
<div class="page-card page-card--md">
    <h2 class="page-card__title"><?= $isEdit ? 'Editar usuario' : 'Nuevo usuario' ?></h2>
    <p class="text-secondary">Un usuario puede tener uno o más roles. Los permisos se obtienen de la suma de esos roles.</p>

    <form method="post" action="<?= e($action) ?>" class="mt-4" novalidate>
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <?= method_field('PUT') ?>
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label" for="name">Nombre</label>
            <input type="text" class="form-control <?= has_error('name') ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e((string) old('name', $record['name'] ?? '')) ?>" required>
            <?php if (has_error('name')): ?><div class="invalid-feedback"><?= e((string) error('name')) ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="email">Correo electrónico</label>
            <input type="email" class="form-control <?= has_error('email') ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= e((string) old('email', $record['email'] ?? '')) ?>" required>
            <?php if (has_error('email')): ?><div class="invalid-feedback"><?= e((string) error('email')) ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="password"><?= $isEdit ? 'Nueva contraseña (opcional)' : 'Contraseña' ?></label>
            <input type="password" class="form-control <?= has_error('password') ? 'is-invalid' : '' ?>" id="password" name="password" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
            <?php if (has_error('password')): ?><div class="invalid-feedback"><?= e((string) error('password')) ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="password_confirmation">Confirmar contraseña</label>
            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
        </div>

        <div class="mb-3">
            <label class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= old('is_active', $record['is_active'] ?? 1) ? 'checked' : '' ?>>
                <span class="form-check-label">Cuenta activa</span>
            </label>
        </div>

        <fieldset class="mb-4">
            <legend class="form-label">Roles</legend>
            <?php if (has_error('role_ids')): ?>
                <div class="text-danger mb-2"><?= e((string) error('role_ids')) ?></div>
            <?php endif; ?>
            <div class="check-grid">
                <?php foreach ($roles as $role): ?>
                    <label class="check-card">
                        <input type="checkbox" name="role_ids[]" value="<?= (int) $role['id'] ?>" <?= in_array((int) $role['id'], $selectedRoleIds, true) ? 'checked' : '' ?>>
                        <strong><?= e($role['name']) ?></strong>
                        <small><?= e((string) ($role['description'] ?? '')) ?></small>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <button type="submit" class="btn btn-navy">Guardar</button>
        <a href="<?= e(url('/users')) ?>" class="btn btn-outline-navy">Cancelar</a>
    </form>
</div>
