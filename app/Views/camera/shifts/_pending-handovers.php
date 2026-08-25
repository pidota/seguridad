<?php
$pendingEntries = $closingSummary['pending_entries'] ?? [];
$pendingCount = count($pendingEntries);
$inProgress = (int) ($closingSummary['in_progress'] ?? $pendingCount);
?>
<?php if ($pendingCount > 0): ?>
<section class="cctv-shift-pending-handover mb-4" aria-label="Registros pendientes">
    <div class="cctv-shift-pending-handover__header">
        <h3 class="h5 mb-1">Registros pendientes</h3>
        <p class="mb-0 text-secondary">
            <?= $pendingCount ?> incidente(s)/novedad(es) continúan en desarrollo.
            Serán entregados al siguiente turno para revisión y continuidad.
        </p>
    </div>

    <ul class="cctv-shift-pending-handover__list">
        <?php foreach ($pendingEntries as $entry): ?>
            <li>
                <time><?= e((string) ($entry['event_time_formatted'] ?? '')) ?></time>
                <strong><?= e((string) ($entry['log_type_name'] ?? 'Registro')) ?></strong>
                <span><?= e((string) ($entry['incident_type_display'] ?? $entry['technical_issue_display'] ?? mb_substr((string) ($entry['observations'] ?? ''), 0, 80))) ?></span>
                <?php if (!empty($entry['sector_name'])): ?>
                    <span class="text-secondary">Sector <?= e((string) $entry['sector_name']) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="cctv-shift-pending-handover__notes">
        <p class="welcome-kicker mb-2">Observación para el siguiente turno</p>
        <?php foreach ($pendingEntries as $entry): ?>
            <?php $entryId = (int) ($entry['id'] ?? 0); ?>
            <div class="mb-3">
                <label class="form-label" for="handover_notes_<?= $entryId ?>">
                    <?= e((string) ($entry['log_type_name'] ?? 'Registro')) ?> · <?= e((string) ($entry['event_time_formatted'] ?? '')) ?>
                </label>
                <textarea
                    class="form-control"
                    id="handover_notes_<?= $entryId ?>"
                    name="handover_notes[<?= $entryId ?>]"
                    rows="2"
                    maxlength="1000"
                    placeholder="Ej.: Se informó a Carabineros. Patrulla aún no confirma llegada."
                ><?= e(old('handover_notes.' . $entryId)) ?></textarea>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
