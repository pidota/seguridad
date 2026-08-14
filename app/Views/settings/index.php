<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Administración</p>
        <h2 class="page-card__title mb-0">Configuración</h2>
    </div>
</section>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="page-card h-100">
            <h3 class="page-card__title">Identidad institucional</h3>
            <dl class="audit-dl">
                <div>
                    <dt>Sistema</dt>
                    <dd><?= e($appName) ?></dd>
                </div>
                <div>
                    <dt>Entorno</dt>
                    <dd><?= e($environment) ?></dd>
                </div>
                <div>
                    <dt>Zona horaria</dt>
                    <dd><?= e($timezone) ?></dd>
                </div>
            </dl>
            <p class="text-secondary mb-0 mt-3">Los parámetros de entorno se administran en el servidor. Esta pantalla concentra el acceso a la gestión institucional.</p>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="page-card h-100">
            <h3 class="page-card__title">Gestión del sistema</h3>
            <?php if ($links === []): ?>
                <p class="text-secondary mb-0">No tiene permisos adicionales de administración.</p>
            <?php else: ?>
                <div class="settings-links">
                    <?php foreach ($links as $link): ?>
                        <a class="settings-link" href="<?= e(url($link['route'])) ?>">
                            <i class="bi <?= e($link['icon']) ?>"></i>
                            <span>
                                <strong><?= e($link['label']) ?></strong>
                                <small><?= e($link['description']) ?></small>
                            </span>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
