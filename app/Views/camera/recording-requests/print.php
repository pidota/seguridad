<?php

$record = $record ?? [];

?>
<article class="cctv-print-sheet">
    <header class="text-center mb-4">
        <h1 class="h4 mb-1">Ficha Administrativa de Solicitud CCTV</h1>
        <p class="mb-0"><code><?= e((string) ($record['request_number'] ?? '—')) ?></code></p>
    </header>

    <section class="mb-3">
        <h2 class="h6">Solicitante</h2>
        <p class="mb-0"><?= e((string) ($record['requester_name'] ?? '—')) ?> — <?= e((string) ($record['requester_rut'] ?? '—')) ?></p>
    </section>

    <section class="mb-3">
        <h2 class="h6">Hecho</h2>
        <p class="mb-1"><?= nl2br(e((string) ($record['incident_description'] ?? ''))) ?></p>
        <small>Fecha: <?= e(date('d/m/Y', strtotime((string) ($record['incident_date'] ?? 'now')))) ?> · <?= e(substr((string) ($record['time_from'] ?? ''), 0, 5)) ?> - <?= e(substr((string) ($record['time_to'] ?? ''), 0, 5)) ?></small>
    </section>

    <section class="mb-3">
        <h2 class="h6">Denuncia</h2>
        <p class="mb-0">
            Informada: <?= !empty($record['has_complaint']) ? 'Sí' : 'No' ?> ·
            Verificada: <?= !empty($record['complaint_verified']) ? 'Sí' : 'No' ?>
            <?php if (!empty($record['complaint_number'])): ?>
                · N.º <?= e((string) $record['complaint_number']) ?>
            <?php endif; ?>
        </p>
    </section>

    <section class="mb-3">
        <h2 class="h6">Estado actual</h2>
        <p class="mb-0"><?= e((string) ($record['status_label'] ?? '—')) ?></p>
    </section>

    <section>
        <h2 class="h6">Historial principal</h2>
        <ul class="mb-0">
            <?php foreach ($record['history'] ?? [] as $item): ?>
                <li>
                    <?= e(date('d/m/Y H:i', strtotime((string) ($item['created_at'] ?? 'now')))) ?> —
                    <?= e((string) ($item['event_label'] ?? $item['new_status_label'] ?? '—')) ?>
                    <?php if (!empty($item['notes'])): ?>(<?= e((string) $item['notes']) ?>)<?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</article>
