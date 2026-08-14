<div class="auth-form-wrap">
    <p class="auth-form-kicker">Acceso institucional</p>
    <h2>Iniciar sesión</h2>
    <p class="text-secondary mb-4">Ingrese sus credenciales institucionales para continuar.</p>

    <form method="post" action="<?= e(url('/login')) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico</label>
            <input
                type="email"
                class="form-control <?= has_error('email') ? 'is-invalid' : '' ?>"
                id="email"
                name="email"
                value="<?= e((string) old('email')) ?>"
                autocomplete="username"
                required
            >
            <?php if (has_error('email')): ?>
                <div class="invalid-feedback"><?= e((string) error('email')) ?></div>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Contraseña</label>
            <input
                type="password"
                class="form-control <?= has_error('password') ? 'is-invalid' : '' ?>"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >
            <?php if (has_error('password')): ?>
                <div class="invalid-feedback"><?= e((string) error('password')) ?></div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-navy w-100" <?= !empty($locked) ? 'disabled' : '' ?>>
            Ingresar al sistema
        </button>
    </form>

    <p class="mt-4 mb-0 text-center">
        <a href="<?= e(url('/password/forgot')) ?>" class="auth-link">¿Olvidó su contraseña?</a>
    </p>
</div>
