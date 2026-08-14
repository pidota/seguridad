<?php
$openShift = $openShift ?? [];
$closingSummary = $closingSummary ?? [];
$equipmentItems = $equipmentItems ?? [];
$statuses = $statuses ?? [];
$equipmentInput = is_array(old('equipment')) ? old('equipment') : [];
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Central de Cámaras</p>
        <h2 class="page-card__title mb-0">Finalizar Turno</h2>
    </div>
    <a class="btn btn-outline-navy" href="<?= e(url('/cctv#turno-activo')) ?>" data-cctv-leave-without-save>Volver al turno</a>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<div class="page-card page-card--lg">
    <div class="cctv-log-create-meta mb-4">
        <div>
            <p class="welcome-kicker mb-1">Turno operativo</p>
            <p class="mb-0">
                Turno activo iniciado el
                <strong><?= e((string) ($openShift['started_at_formatted'] ?? '—')) ?></strong>
            </p>
        </div>
        <div>
            <p class="welcome-kicker mb-1">Operador</p>
            <p class="mb-0"><strong><?= e((string) ($openShift['operator_label'] ?? '—')) ?></strong></p>
        </div>
    </div>

    <?php require __DIR__ . '/_closing-summary.php'; ?>

    <p class="cctv-reception-intro mb-4">
        Revise el estado de los equipos antes de entregar el puesto.
        Al finalizar el turno no podrá registrar nuevas entradas en la bitácora hasta abrir uno nuevo.
    </p>

    <form
        method="post"
        action="<?= e(url('/cctv/shifts/close')) ?>"
        novalidate
        data-cctv-shift-reception-form
        data-cctv-shift-close-form
        data-cctv-unsaved-guard
        data-closing-summary="<?= e(json_encode($closingSummary, JSON_UNESCAPED_UNICODE)) ?>"
    >
        <?= csrf_field() ?>

        <?php
        $generalNotesField = 'closing_notes';
        $generalNotesLabel = 'Observaciones de entrega del turno';
        $generalNotesPlaceholder = 'Ej.: Se entrega celular y equipos operativos.';
        $generalNotesHelp = 'Opcional. Use este campo para registrar una situación general de la entrega.';
        require __DIR__ . '/_equipment-checklist.php';
        ?>

        <div class="form-actions">
            <button class="btn btn-danger" type="submit">Finalizar turno</button>
            <a class="btn btn-outline-navy" href="<?= e(url('/cctv#turno-activo')) ?>" data-cctv-leave-without-save>Cancelar</a>
        </div>
    </form>
</div>
