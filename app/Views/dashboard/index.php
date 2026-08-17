<?php
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Buenos días' : ($hour < 20 ? 'Buenas tardes' : 'Buenas noches');
?>
<section class="welcome-band">
    <div>
        <p class="welcome-kicker">Sistema Integral de Gestión de Seguridad Municipal</p>
        <h2><?= e($greeting) ?>, <?= e((string) ($user['name'] ?? '')) ?></h2>
        <p class="mb-0">Seleccione un módulo operativo. El acceso visible corresponde a los permisos de su perfil.</p>
    </div>
    <div class="welcome-meta">
        <span class="role-chip"><?= e((string) ($user['role_names'] ?? '')) ?></span>
        <span class="welcome-date"><?= e(format_fecha_institucional()) ?></span>
    </div>
</section>

<?php if (($followUpAlertPanel ?? null) !== null): ?>
    <?php require dirname(__DIR__) . '/senda/dashboard/followup-alerts.php'; ?>
<?php endif; ?>

<?php if ($modules === []): ?>
    <div class="page-card">
        <h2 class="page-card__title">Sin módulos asignados</h2>
        <p class="text-secondary mb-0">Su cuenta no tiene permisos sobre los módulos operativos. Consulte con la administración del sistema.</p>
    </div>
<?php else: ?>
    <section class="module-grid">
        <?php foreach ($modules as $module): ?>
            <a class="module-card module-card--<?= e($module['tone']) ?>" href="<?= e(url($module['route'])) ?>">
                <div class="module-card__icon">
                    <i class="bi <?= e($module['icon']) ?>"></i>
                </div>
                <h3><?= e($module['name']) ?></h3>
                <p><?= e($module['description']) ?></p>
                <span class="module-card__cta">Ingresar <i class="bi bi-arrow-right"></i></span>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
