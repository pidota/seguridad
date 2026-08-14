<?php
$features = $features ?? [];
?>
<section class="module-hero">
    <div class="module-hero__icon">
        <i class="bi <?= e($icon ?? 'bi-grid') ?>"></i>
    </div>
    <div>
        <p class="welcome-kicker"><?= e($kicker ?? 'Módulo') ?></p>
        <h2><?= e($title ?? '') ?></h2>
        <p class="mb-0"><?= e($lead ?? '') ?></p>
    </div>
    <span class="badge-soon">En preparación</span>
</section>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="page-card h-100">
            <h3 class="page-card__title">Estado del módulo</h3>
            <p class="text-secondary"><?= e($message ?? '') ?></p>
            <p class="mb-0 text-secondary">El ingreso a esta sección ya está protegido por permisos. Las funciones operativas se habilitarán sin cambiar la navegación.</p>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="page-card h-100">
            <h3 class="page-card__title">Alcance previsto</h3>
            <ul class="module-feature-list">
                <?php foreach ($features as $feature): ?>
                    <li><i class="bi bi-check2"></i> <?= e($feature) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
