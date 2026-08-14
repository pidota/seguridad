<?php
$closingSummary = $closingSummary ?? [];
?>
<section class="cctv-shift-close-summary" aria-label="Resumen del turno">
    <div class="cctv-shift-close-summary__header">
        <h3 class="cctv-shift-close-summary__title mb-0">Resumen de cierre</h3>
        <p class="cctv-shift-close-summary__hint mb-0">Revise los datos del turno antes de confirmar la entrega.</p>
    </div>
    <dl class="cctv-shift-close-summary__grid">
        <div>
            <dt>Inicio</dt>
            <dd><?= e((string) ($closingSummary['started_time'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Término</dt>
            <dd><span data-cctv-closing-ending-time><?= e((string) ($closingSummary['ending_time'] ?? '—')) ?></span></dd>
        </div>
        <div>
            <dt>Total registros</dt>
            <dd><?= (int) ($closingSummary['total_entries'] ?? 0) ?></dd>
        </div>
        <div>
            <dt>Incidentes</dt>
            <dd><?= (int) ($closingSummary['incidents'] ?? 0) ?></dd>
        </div>
        <div>
            <dt>Novedades</dt>
            <dd><?= (int) ($closingSummary['general_entries'] ?? 0) ?></dd>
        </div>
        <div>
            <dt>Novedades técnicas</dt>
            <dd><?= (int) ($closingSummary['technical_issues'] ?? 0) ?></dd>
        </div>
        <div>
            <dt>Coordinaciones</dt>
            <dd><?= (int) ($closingSummary['coordinations'] ?? 0) ?></dd>
        </div>
    </dl>
</section>
