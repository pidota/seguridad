<?php
$equipmentItems = $equipmentItems ?? [];
$statuses = $statuses ?? [];
$equipmentInput = is_array(old('equipment')) ? old('equipment') : [];
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Central de Cámaras</p>
        <h2 class="page-card__title mb-0">Recepción del puesto</h2>
    </div>
    <a class="btn btn-outline-navy" href="<?= e(url('/cctv')) ?>" data-cctv-leave-without-save>Volver al inicio</a>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<div class="page-card page-card--lg">
    <p class="cctv-reception-intro mb-4">
        Revise el estado de los equipos del puesto antes de iniciar su turno operativo.
        Puede registrar una situación general o detallar observaciones por equipo.
    </p>

    <form
        method="post"
        action="<?= e(url('/cctv/shifts')) ?>"
        novalidate
        data-cctv-shift-reception-form
    >
        <?= csrf_field() ?>

        <?php
        $generalNotesField = 'opening_notes';
        $generalNotesLabel = 'Observaciones generales de recepción';
        $generalNotesPlaceholder = 'Ej.: Se recibe celular y equipos en buen estado.';
        $generalNotesHelp = 'Opcional. Use este campo para registrar una situación general del puesto.';
        require __DIR__ . '/_equipment-checklist.php';
        ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-navy">Iniciar Turno</button>
            <a class="btn btn-outline-navy" href="<?= e(url('/cctv')) ?>" data-cctv-leave-without-save>Cancelar</a>
        </div>
    </form>
</div>
