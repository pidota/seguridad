<div class="page-card page-card--sm">
    <p class="welcome-kicker mb-1">Seguridad de la cuenta</p>
    <h2 class="page-card__title">Cambiar contraseña</h2>
    <p class="text-secondary">La nueva contraseña debe tener al menos 8 caracteres y ser distinta a la actual.</p>

    <form method="post" action="<?= e(url('/password/change')) ?>" class="mt-4" novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
            <label for="current_password" class="form-label">Contraseña actual</label>
            <input type="password" class="form-control <?= has_error('current_password') ? 'is-invalid' : '' ?>" id="current_password" name="current_password" autocomplete="current-password" required>
            <?php if (has_error('current_password')): ?>
                <div class="invalid-feedback"><?= e((string) error('current_password')) ?></div>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Nueva contraseña</label>
            <input type="password" class="form-control <?= has_error('password') ? 'is-invalid' : '' ?>" id="password" name="password" autocomplete="new-password" required>
            <?php if (has_error('password')): ?>
                <div class="invalid-feedback"><?= e((string) error('password')) ?></div>
            <?php endif; ?>
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirmar nueva contraseña</label>
            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn-navy">Guardar contraseña</button>
        <a href="<?= e(url('/profile')) ?>" class="btn btn-outline-navy">Volver al perfil</a>
    </form>
</div>
