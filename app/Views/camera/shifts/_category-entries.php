<?php
$items = $items ?? [];
$title = (string) ($title ?? 'Registros');
$emptyMessage = (string) ($emptyMessage ?? 'Sin registros en este turno.');
$canViewLog = !empty($canViewLog);
?>
<section class="cctv-shift-detail__category">
    <div class="cctv-shift-detail__category-head">
        <h4 class="cctv-shift-detail__category-title mb-0"><?= e($title) ?></h4>
        <span class="cctv-shift-detail__category-count"><?= count($items) ?></span>
    </div>
    <?php if ($items === []): ?>
        <p class="cctv-shift-detail__empty mb-0"><?= e($emptyMessage) ?></p>
    <?php else: ?>
        <ul class="cctv-shift-detail__entry-list mb-0">
            <?php foreach ($items as $item): ?>
                <li class="cctv-shift-detail__entry-item">
                    <div class="cctv-shift-detail__entry-head">
                        <time class="cctv-shift-log__time"><?= e((string) ($item['time_label'] ?? '—')) ?></time>
                        <span class="camera-device-badge camera-device-badge--<?= e((string) ($item['type_tone'] ?? 'other')) ?>">
                            <?= e((string) ($item['type_label'] ?? 'REGISTRO')) ?>
                        </span>
                    </div>
                    <p class="cctv-shift-detail__entry-summary mb-0"><?= e((string) ($item['summary'] ?? '—')) ?></p>
                    <?php if ($canViewLog && !empty($item['can_view']) && !empty($item['id'])): ?>
                        <a class="cctv-shift-detail__entry-link" href="<?= e(url('/cctv/log/' . (int) $item['id'])) ?>">Ver detalle</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
