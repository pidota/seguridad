<?php
$panel = $followUpAlertPanel ?? null;

if ($panel === null) {
    return;
}

$items = $panel['items'] ?? [];
$total = (int) ($panel['total'] ?? 0);
$remaining = max(0, $total - count($items));
?>
<section class="page-card senda-followup-alerts mb-3" aria-label="Alertas de seguimientos pendientes">
    <div class="senda-followup-alerts__header">
        <div>
            <p class="welcome-kicker mb-1">Alertas operativas</p>
            <h3 class="page-card__title h5 mb-1">Seguimientos pendientes</h3>
            <p class="text-secondary mb-0">
                Hay <strong><?= $total ?></strong> seguimiento(s) con próxima fecha programada.
                <?php if (($panel['overdue'] ?? 0) > 0): ?>
                    <span class="senda-followup-alerts__chip senda-followup-alerts__chip--overdue">
                        <?= (int) $panel['overdue'] ?> atrasado(s)
                    </span>
                <?php endif; ?>
                <?php if (($panel['due_today'] ?? 0) > 0): ?>
                    <span class="senda-followup-alerts__chip senda-followup-alerts__chip--due">
                        <?= (int) $panel['due_today'] ?> para hoy
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <a class="btn btn-outline-navy btn-sm" href="<?= e(url((string) ($panel['list_path'] ?? '/senda/follow-ups'))) ?>">
            Ver todos
        </a>
    </div>

    <?php if ($total === 0): ?>
        <p class="senda-followup-alerts__empty mb-0 mt-3" aria-label="0 seguimientos pendientes">0</p>
        <p class="text-secondary mb-0">No hay seguimientos pendientes en este momento.</p>
    <?php elseif ($items === []): ?>
        <p class="text-secondary mb-0 mt-3">No hay detalle disponible para mostrar.</p>
    <?php else: ?>
        <ul class="senda-followup-alerts__list">
            <?php foreach ($items as $item): ?>
                <li>
                    <a class="senda-followup-alerts__item senda-followup-alerts__item--<?= e((string) ($item['tone'] ?? 'due')) ?>" href="<?= e(url((string) ($item['path'] ?? '/senda/follow-ups'))) ?>">
                        <span class="senda-followup-alerts__person"><?= e((string) ($item['person_full_name'] ?? '—')) ?></span>
                        <?php if (!empty($item['person_rut'])): ?>
                            <span class="senda-followup-alerts__meta"><?= e((string) $item['person_rut']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['attention_number'])): ?>
                            <span class="senda-followup-alerts__meta">Atención <?= e((string) $item['attention_number']) ?></span>
                        <?php endif; ?>
                        <span class="senda-badge senda-badge--<?= e((string) ($item['tone'] ?? 'due')) ?>">
                            <?= e((string) ($item['status_label'] ?? 'Pendiente')) ?>
                        </span>
                        <span class="senda-followup-alerts__date">Próximo: <?= e((string) ($item['next_follow_up_date_label'] ?? '—')) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($remaining > 0): ?>
            <p class="senda-followup-alerts__more mb-0">
                <a href="<?= e(url((string) ($panel['list_path'] ?? '/senda/follow-ups'))) ?>">
                    Y <?= $remaining ?> seguimiento(s) más…
                </a>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</section>
