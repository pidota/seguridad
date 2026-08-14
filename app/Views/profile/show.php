<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Cuenta institucional</p>
        <h2 class="page-card__title mb-0">Perfil</h2>
    </div>
</section>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="page-card profile-card text-center">
            <div class="profile-card__avatar"><?= e(mb_strtoupper(mb_substr((string) ($user['name'] ?? 'U'), 0, 1))) ?></div>
            <h3 class="mt-3 mb-1"><?= e((string) ($user['name'] ?? '')) ?></h3>
            <p class="text-secondary mb-3"><?= e((string) ($user['email'] ?? '')) ?></p>
            <span class="status-pill <?= !empty($user['is_active']) ? 'is-on' : 'is-off' ?>">
                <?= !empty($user['is_active']) ? 'Cuenta activa' : 'Cuenta inactiva' ?>
            </span>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="page-card">
            <h3 class="page-card__title">Datos de acceso</h3>
            <dl class="audit-dl">
                <div>
                    <dt>Nombre</dt>
                    <dd><?= e((string) ($user['name'] ?? '')) ?></dd>
                </div>
                <div>
                    <dt>Correo</dt>
                    <dd><?= e((string) ($user['email'] ?? '')) ?></dd>
                </div>
                <div>
                    <dt>Roles</dt>
                    <dd><?= e((string) ($user['role_names'] ?? '—')) ?></dd>
                </div>
                <div>
                    <dt>Último ingreso</dt>
                    <dd><?= e(!empty($user['last_login_at']) ? date('d-m-Y H:i', strtotime((string) $user['last_login_at'])) : '—') ?></dd>
                </div>
            </dl>
            <a href="<?= e(url('/password/change')) ?>" class="btn btn-navy mt-4">Cambiar contraseña</a>
        </div>
    </div>
</div>
