<?php

if (empty($canViewCamerasMap)) {
    return;
}

?>
<section class="camera-dashboard-grid mb-3">
    <a class="camera-stat-card camera-stat-card--link" href="<?= e(url('/cctv/cameras/map')) ?>">
        <p class="camera-stat-card__label">Mapa de cámaras</p>
        <p class="camera-stat-card__hint">
            Visualice en mapa las <?= (int) ($camerasMapCount ?? 0) ?> cámaras con ubicación geográfica registrada.
        </p>
        <span class="camera-stat-card__action">Abrir mapa <i class="bi bi-arrow-right"></i></span>
    </a>
</section>
