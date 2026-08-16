<?php

$cameras = $cameras ?? [];
$mapConfig = $mapConfig ?? [];

?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Central de Cámaras</p>
        <h2 class="page-card__title mb-1">Mapa de cámaras</h2>
        <p class="text-secondary mb-0">
            <?= count($cameras) ?> cámara<?= count($cameras) === 1 ? '' : 's' ?> con ubicación geográfica registrada
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-navy" href="<?= e(url('/cctv/cameras')) ?>">Inventario</a>
        <?php if (!empty($canManage)): ?>
            <a class="btn btn-navy" href="<?= e(url('/cctv/cameras/create')) ?>">Registrar cámara</a>
        <?php endif; ?>
    </div>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<div class="page-card mb-3">
    <?php if ($cameras === []): ?>
        <p class="text-secondary mb-0">
            Aún no hay cámaras con coordenadas en el mapa.
            <?php if (!empty($canManage)): ?>
                Al registrar o editar una cámara, seleccione su ubicación en el mapa.
            <?php endif; ?>
        </p>
    <?php else: ?>
        <div
            id="cctv-cameras-map"
            class="cctv-map-overview"
            data-cameras-map
            data-map-config="<?= e(json_encode($mapConfig, JSON_UNESCAPED_UNICODE)) ?>"
            data-cameras="<?= e(json_encode(array_map(static function (array $camera): array {
                return [
                    'id' => (int) ($camera['id'] ?? 0),
                    'code' => (string) ($camera['code'] ?? ''),
                    'name' => (string) ($camera['name'] ?? ''),
                    'location' => (string) ($camera['location'] ?? ''),
                    'sector' => (string) ($camera['sector_label'] ?? ''),
                    'status' => (string) ($camera['status_label'] ?? ''),
                    'statusTone' => (string) ($camera['status_tone'] ?? 'info'),
                    'lat' => (float) ($camera['latitude'] ?? 0),
                    'lng' => (float) ($camera['longitude'] ?? 0),
                    'editUrl' => url('/cctv/cameras/' . ($camera['id'] ?? '') . '/edit'),
                ];
            }, $cameras), JSON_UNESCAPED_UNICODE)) ?>"
        ></div>
    <?php endif; ?>
</div>
