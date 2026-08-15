<?php

$receipt = $receipt ?? [];

?>
<article class="cctv-print-sheet">
    <header class="text-center mb-4">
        <h1 class="h4 mb-1"><?= e((string) ($receipt['title'] ?? 'Constancia de Entrega')) ?></h1>
        <p class="mb-0">N.º <?= e((string) ($receipt['request_number'] ?? '—')) ?></p>
    </header>

    <dl class="cctv-detail-grid">
        <div><dt>Fecha y hora de solicitud</dt><dd><?= e((string) ($receipt['request_datetime'] ?? '—')) ?></dd></div>
        <div><dt>Solicitante</dt><dd><?= e((string) ($receipt['requester_name'] ?? '—')) ?> (<?= e((string) ($receipt['requester_rut'] ?? '—')) ?>)</dd></div>
        <div><dt>Identificación del hecho</dt><dd><?= e((string) ($receipt['incident_description'] ?? '—')) ?></dd></div>
        <div><dt>Fecha del hecho</dt><dd><?= e((string) ($receipt['incident_date'] ?? '—')) ?></dd></div>
        <div><dt>Rango horario solicitado</dt><dd><?= e((string) ($receipt['time_from'] ?? '—')) ?> - <?= e((string) ($receipt['time_to'] ?? '—')) ?></dd></div>
        <div><dt>Sector</dt><dd><?= e((string) ($receipt['sector_name'] ?? '—')) ?></dd></div>
        <div><dt>Cámara</dt><dd><?= e((string) ($receipt['camera_name'] ?? '—')) ?></dd></div>
        <div><dt>Institución de denuncia</dt><dd><?= e((string) ($receipt['complaint_institution'] ?? '—')) ?></dd></div>
        <div><dt>N.º denuncia / parte</dt><dd><?= e((string) ($receipt['complaint_number'] ?? '—')) ?></dd></div>
        <div><dt>Persona que recibe</dt><dd><?= e((string) ($receipt['receiver_name'] ?? '—')) ?> (<?= e((string) ($receipt['receiver_rut'] ?? '—')) ?>)</dd></div>
        <div><dt>Medio de entrega</dt><dd><?= e((string) ($receipt['delivery_medium'] ?? '—')) ?></dd></div>
        <div><dt>Fecha y hora de entrega</dt><dd><?= e((string) ($receipt['delivered_at'] ?? '—')) ?></dd></div>
        <div><dt>Funcionario que entrega</dt><dd><?= e((string) ($receipt['delivered_by'] ?? '—')) ?></dd></div>
    </dl>

    <?php if (!empty($receipt['public_notes'])): ?>
        <section class="mt-3">
            <h2 class="h6">Observaciones</h2>
            <p class="mb-0"><?= nl2br(e((string) $receipt['public_notes'])) ?></p>
        </section>
    <?php endif; ?>

    <p class="text-secondary small mt-4 mb-0">
        Documento preparado para impresión o exportación PDF. La firma manuscrita o electrónica se incorporará en una etapa posterior.
    </p>
</article>
