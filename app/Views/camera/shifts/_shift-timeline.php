<?php
$shiftTimeline = $shiftTimeline ?? [];
$timelineItems = $shiftTimeline['items'] ?? [];
$logOrder = (string) ($shiftTimeline['order'] ?? 'desc');
$logOrderOptions = $logOrderOptions ?? [];
$currentLogOrderLabel = $logOrderOptions[$logOrder] ?? ($logOrder === 'asc' ? 'Cronológico' : 'Más reciente primero');
$title = (string) ($title ?? 'Bitácora del turno');
$hint = (string) ($hint ?? ((int) ($shiftTimeline['total'] ?? 0) . ' eventos en la jornada'));
$sectionId = (string) ($sectionId ?? 'bitacora-turno');
$showOrderToggle = !empty($showOrderToggle);
$formAction = (string) ($formAction ?? '');
$canViewLog = !empty($canViewLog);
?>
<section class="page-card cctv-shift-log mb-3" id="<?= e($sectionId) ?>">
    <div class="cctv-shift-log__header">
        <div>
            <p class="welcome-kicker mb-1">Monitoreo operativo</p>
            <h3 class="page-card__title mb-0"><?= e($title) ?></h3>
            <p class="cctv-shift-log__hint mb-0"><?= e($hint) ?></p>
        </div>
        <?php if ($showOrderToggle && $formAction !== ''): ?>
            <div class="cctv-shift-log__controls">
                <form method="get" action="<?= e($formAction) ?>" class="cctv-shift-log__sort">
                    <input type="hidden" name="log_order" value="<?= $logOrder === 'desc' ? 'asc' : 'desc' ?>">
                    <button type="submit" class="btn btn-outline-navy btn-sm">
                        <?= e($logOrder === 'desc' ? 'Ver cronológico' : 'Ver más reciente') ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <p class="cctv-shift-log__order-label">Orden: <strong><?= e($currentLogOrderLabel) ?></strong></p>

    <?php if ($timelineItems === []): ?>
        <p class="cctv-shift-log__empty mb-0">No hay eventos registrados en este turno.</p>
    <?php else: ?>
        <div class="cctv-shift-log__table-wrap d-none d-lg-block">
            <table class="table cctv-shift-log__table mb-0">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Tipo</th>
                        <th>Cámara</th>
                        <th>Sector</th>
                        <th>Resumen</th>
                        <th>Usuario</th>
                        <?php if ($canViewLog): ?>
                            <th>Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($timelineItems as $item): ?>
                        <tr class="cctv-shift-log__row cctv-shift-log__row--<?= e((string) ($item['kind'] ?? 'log_entry')) ?>">
                            <td class="cctv-shift-log__time"><?= e((string) ($item['time_label'] ?? '—')) ?></td>
                            <td>
                                <span class="camera-device-badge camera-device-badge--<?= e((string) ($item['type_tone'] ?? 'other')) ?>">
                                    <?= e((string) ($item['type_label'] ?? 'REGISTRO')) ?>
                                </span>
                            </td>
                            <td><?= e((string) ($item['camera_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['sector_label'] ?? '—')) ?></td>
                            <td class="cctv-shift-log__summary"><?= e((string) ($item['summary'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['user_label'] ?? '—')) ?></td>
                            <?php if ($canViewLog): ?>
                                <td>
                                    <?php if (!empty($item['can_view']) && !empty($item['id'])): ?>
                                        <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/cctv/log/' . (int) $item['id'])) ?>">Ver</a>
                                    <?php else: ?>
                                        <span class="text-secondary">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="cctv-shift-log__feed d-lg-none">
            <?php foreach ($timelineItems as $item): ?>
                <article class="cctv-shift-log__card cctv-shift-log__card--<?= e((string) ($item['kind'] ?? 'log_entry')) ?>">
                    <div class="cctv-shift-log__card-head">
                        <time class="cctv-shift-log__time"><?= e((string) ($item['time_label'] ?? '—')) ?></time>
                        <span class="camera-device-badge camera-device-badge--<?= e((string) ($item['type_tone'] ?? 'other')) ?>">
                            <?= e((string) ($item['type_label'] ?? 'REGISTRO')) ?>
                        </span>
                    </div>
                    <div class="cctv-shift-log__card-meta">
                        <?php if (($item['camera_label'] ?? '—') !== '—'): ?>
                            <span><?= e((string) $item['camera_label']) ?></span>
                        <?php endif; ?>
                        <?php if (($item['sector_label'] ?? '—') !== '—'): ?>
                            <span><?= e((string) $item['sector_label']) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="cctv-shift-log__summary mb-2"><?= e((string) ($item['summary'] ?? '—')) ?></p>
                    <div class="cctv-shift-log__card-foot">
                        <span><?= e((string) ($item['user_label'] ?? '—')) ?></span>
                        <?php if ($canViewLog && !empty($item['can_view']) && !empty($item['id'])): ?>
                            <a href="<?= e(url('/cctv/log/' . (int) $item['id'])) ?>">Ver detalle</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
