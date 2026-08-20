<?php

$meeting = is_array($meeting ?? null) ? $meeting : [];
$participant = is_array($participant ?? null) ? $participant : [];
$alreadyResponded = !empty($alreadyResponded);
$attendanceStatus = (string) ($attendanceStatus ?? 'pending');
?>
<div class="error-inner text-start">
    <span class="error-code"><i class="bi bi-envelope-check"></i></span>
    <h1>Confirmación de asistencia</h1>
    <p class="text-secondary mb-3">
        Reunión <strong><?= e((string) ($meeting['meeting_number'] ?? '')) ?></strong>
    </p>

    <?= component('flash') ?>

    <div class="page-card mb-3 text-start">
        <p class="mb-2">Estimado/a <strong><?= e((string) ($participant['external_name'] ?? '')) ?></strong>,</p>
        <dl class="meetings-summary mb-0">
            <div><dt>Fecha</dt><dd><?= e(!empty($meeting['meeting_date']) ? date('d-m-Y', strtotime((string) $meeting['meeting_date'])) : '—') ?></dd></div>
            <div><dt>Hora</dt><dd><?= e(!empty($meeting['meeting_time']) ? substr((string) $meeting['meeting_time'], 0, 5) : '—') ?></dd></div>
            <div><dt>Lugar</dt><dd><?= e((string) ($meeting['meeting_place'] ?? '—')) ?></dd></div>
        </dl>
    </div>

    <?php if ($alreadyResponded): ?>
        <div class="alert alert-success">
            <?php if ($attendanceStatus === 'confirmed'): ?>
                Su asistencia ya fue confirmada. Gracias.
            <?php else: ?>
                Registramos que no asistirá a esta reunión.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="mb-3">Indique si asistirá a la reunión:</p>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <form method="post" action="<?= e(url('/meetings/attendance/' . ($token ?? ''))) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="confirm">
                <button type="submit" class="btn btn-navy">Confirmar asistencia</button>
            </form>
            <form method="post" action="<?= e(url('/meetings/attendance/' . ($token ?? ''))) ?>"
                  data-confirm="¿Confirma que no asistirá a esta reunión?"
                  data-confirm-title="Declinar asistencia">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="decline">
                <button type="submit" class="btn btn-outline-danger">No asistiré</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
