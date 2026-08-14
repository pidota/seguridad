<div class="auth-form-wrap">
    <p class="auth-form-kicker">Recuperación</p>
    <h2>Restablecer acceso</h2>
    <p class="text-secondary mb-4">Esta función queda preparada para cuando se configure el envío de correo institucional. Puede registrar la solicitud ahora.</p>

    <form method="post" action="<?= e(url('/password/forgot')) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico</label>
            <input
                type="email"
                class="form-control <?= has_error('email') ? 'is-invalid' : '' ?>"
                id="email"
                name="email"
                value="<?= e((string) old('email')) ?>"
                required
            >
            <?php if (has_error('email')): ?>
                <div class="invalid-feedback"><?= e((string) error('email')) ?></div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-navy w-100">Registrar solicitud</button>
    </form>

    <p class="mt-4 mb-0 text-center">
        <a href="<?= e(url('/login')) ?>" class="auth-link">Volver al inicio de sesión</a>
    </p>
</div>
