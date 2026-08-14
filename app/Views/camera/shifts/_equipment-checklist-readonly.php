<?php
$checks = $checks ?? [];
$emptyMessage = (string) ($emptyMessage ?? 'No hay equipos registrados para esta fase.');
?>
<?php if ($checks === []): ?>
    <p class="cctv-shift-detail__empty mb-0"><?= e($emptyMessage) ?></p>
<?php else: ?>
    <ul class="cctv-shift-check-list mb-0">
        <?php foreach ($checks as $check): ?>
            <?php
            $observations = trim((string) ($check['observations'] ?? ''));
            ?>
            <li class="cctv-shift-check-list__item">
                <div class="cctv-shift-check-list__main">
                    <span class="cctv-shift-check-list__name"><?= e((string) ($check['equipment_name'] ?? 'Equipo')) ?></span>
                    <span class="camera-device-badge camera-device-badge--<?= e((string) ($check['status_tone'] ?? 'other')) ?>">
                        <?= e((string) ($check['status_label'] ?? '—')) ?>
                    </span>
                </div>
                <?php if ($observations !== ''): ?>
                    <p class="cctv-shift-check-list__notes mb-0"><?= e($observations) ?></p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
