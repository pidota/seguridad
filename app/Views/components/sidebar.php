<?php
$user = $user ?? user();
$modules = [
    ['label' => 'Dashboard', 'icon' => 'bi-grid-1x2', 'path' => '/dashboard', 'permission' => 'dashboard.access'],
    ['label' => 'Oficina de la Mujer', 'icon' => 'bi-person-hearts', 'path' => '/women', 'permission' => 'women.access'],
    ['label' => 'Guardias', 'icon' => 'bi-person-badge', 'path' => '/guards', 'permission' => 'guards.access'],
];
$cctv = [
    ['label' => 'Inicio', 'icon' => 'bi-speedometer2', 'path' => '/cctv', 'permission' => 'cctv.dashboard.view', 'exact' => true],
    ['label' => 'Bitácora', 'icon' => 'bi-journal-text', 'path' => '/cctv/log', 'permission' => 'cctv.log.view'],
    ['label' => 'Visitas y Solicitudes', 'icon' => 'bi-person-lines-fill', 'path' => '/cctv/visits', 'permission' => 'cctv.visits.view'],
    ['label' => 'Cámaras', 'icon' => 'bi-camera-reels', 'path' => '/cctv/cameras', 'permission' => 'cctv.cameras.view'],
];
$visibleCctv = array_values(array_filter($cctv, static fn (array $item): bool => hasPermission($item['permission'])));
$cctvOpen = hasPermission('cctv.access') && is_active_path('/cctv');
$senda = [
    ['label' => 'Tipo de ingreso', 'icon' => 'bi-door-open', 'path' => '/senda', 'permission' => 'senda.dashboard.view', 'exact' => true],
    ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'path' => '/senda/dashboard', 'permission' => 'senda.dashboard.view', 'exact' => true],
    ['label' => 'Registro de Atención', 'icon' => 'bi-clipboard2-pulse', 'path' => '/senda/attentions', 'permission' => 'senda.attentions.view'],
    ['label' => 'Ficha de Referencia', 'icon' => 'bi-file-earmark-medical', 'path' => '/senda/referrals', 'permission' => 'senda.referrals.view'],
    ['label' => 'Seguimiento', 'icon' => 'bi-arrow-repeat', 'path' => '/senda/follow-ups', 'permission' => 'senda.followups.view'],
    ['label' => 'Personas', 'icon' => 'bi-people', 'path' => '/senda/people', 'permission' => 'senda.people.view'],
    ['label' => 'Estadísticas', 'icon' => 'bi-graph-up', 'path' => '/senda/statistics', 'permission' => 'senda.statistics.view'],
];
$visibleSenda = array_values(array_filter($senda, static fn (array $item): bool => hasPermission($item['permission'])));
$sendaOpen = hasPermission('senda.access') && is_active_path('/senda');
$admin = [
    ['label' => 'Usuarios', 'icon' => 'bi-people', 'path' => '/users', 'permission' => 'users.access'],
    ['label' => 'Roles', 'icon' => 'bi-shield-lock', 'path' => '/roles', 'permission' => 'roles.access'],
    ['label' => 'Sectores', 'icon' => 'bi-geo-alt', 'path' => '/sectors', 'permission' => 'sectors.access'],
    ['label' => 'Auditoría', 'icon' => 'bi-journal-text', 'path' => '/audit', 'permission' => 'audit.access'],
    ['label' => 'Configuración', 'icon' => 'bi-gear', 'path' => '/settings', 'permission' => 'settings.access'],
];
$visibleAdmin = array_values(array_filter($admin, static fn (array $item): bool => hasPermission($item['permission'])));
$adminOpen = is_active_group(array_column($visibleAdmin, 'path'));
?>
<aside class="app-sidebar" id="app-sidebar" aria-label="Navegación principal">
    <div class="sidebar-brand">
        <img src="<?= e(asset('images/logo.svg')) ?>" alt="Escudo municipal" width="40" height="40">
        <div>
            <strong>SIGSM</strong>
            <small>Seguridad Municipal</small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <p class="sidebar-label">Operación</p>
        <?php foreach ($modules as $item): ?>
            <?php if (!hasPermission($item['permission'])) continue; ?>
            <a class="sidebar-link <?= is_active_path($item['path']) ? 'is-active' : '' ?>" href="<?= e(url($item['path'])) ?>">
                <i class="bi <?= e($item['icon']) ?>"></i>
                <span><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>

        <?php if (hasPermission('cctv.access') && $visibleCctv !== []): ?>
            <div class="sidebar-group <?= $cctvOpen ? 'is-open' : '' ?>" data-nav-group>
                <button type="button" class="sidebar-link sidebar-group__toggle <?= $cctvOpen ? 'is-active' : '' ?>" data-nav-toggle aria-expanded="<?= $cctvOpen ? 'true' : 'false' ?>">
                    <i class="bi bi-camera-video"></i>
                    <span>CCTV</span>
                    <i class="bi bi-chevron-down sidebar-group__caret"></i>
                </button>
                <div class="sidebar-group__items">
                    <?php foreach ($visibleCctv as $item): ?>
                        <?php
                            $active = !empty($item['exact'])
                                ? is_current_path($item['path'])
                                : is_active_path($item['path']);
                        ?>
                        <a class="sidebar-link sidebar-link--sub <?= $active ? 'is-active' : '' ?>" href="<?= e(url($item['path'])) ?>">
                            <i class="bi <?= e($item['icon']) ?>"></i>
                            <span><?= e($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (hasPermission('senda.access') && $visibleSenda !== []): ?>
            <div class="sidebar-group <?= $sendaOpen ? 'is-open' : '' ?>" data-nav-group>
                <button type="button" class="sidebar-link sidebar-group__toggle <?= $sendaOpen ? 'is-active' : '' ?>" data-nav-toggle aria-expanded="<?= $sendaOpen ? 'true' : 'false' ?>">
                    <i class="bi bi-heart-pulse"></i>
                    <span>SENDA</span>
                    <i class="bi bi-chevron-down sidebar-group__caret"></i>
                </button>
                <div class="sidebar-group__items">
                    <?php foreach ($visibleSenda as $item): ?>
                        <?php
                            $active = !empty($item['exact'])
                                ? is_current_path($item['path'])
                                : is_active_path($item['path']);
                        ?>
                        <a class="sidebar-link sidebar-link--sub <?= $active ? 'is-active' : '' ?>" href="<?= e(url($item['path'])) ?>">
                            <i class="bi <?= e($item['icon']) ?>"></i>
                            <span><?= e($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($visibleAdmin !== []): ?>
            <p class="sidebar-label">Administración</p>
            <div class="sidebar-group <?= $adminOpen ? 'is-open' : '' ?>" data-nav-group>
                <button type="button" class="sidebar-link sidebar-group__toggle" data-nav-toggle aria-expanded="<?= $adminOpen ? 'true' : 'false' ?>">
                    <i class="bi bi-sliders"></i>
                    <span>Administración</span>
                    <i class="bi bi-chevron-down sidebar-group__caret"></i>
                </button>
                <div class="sidebar-group__items">
                    <?php foreach ($visibleAdmin as $item): ?>
                        <a class="sidebar-link sidebar-link--sub <?= is_active_path($item['path']) ? 'is-active' : '' ?>" href="<?= e(url($item['path'])) ?>">
                            <i class="bi <?= e($item['icon']) ?>"></i>
                            <span><?= e($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </nav>
</aside>
<div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>
