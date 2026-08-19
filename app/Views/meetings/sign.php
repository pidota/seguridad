<?php

$meeting = is_array($meeting ?? null) ? $meeting : [];
$id = (int) ($meeting['id'] ?? 0);
$sourceModule = (string) ($sourceModule ?? ($meeting['source_module'] ?? 'admin'));
$hasSignature = ($activeSignature ?? null) !== null;

?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1"><?= $sourceModule === 'senda' ? 'SENDA' : 'Reuniones' ?></p>
        <h2 class="page-card__title mb-1">Revisar y firmar</h2>
        <p class="text-secondary mb-0"><?= e((string) ($meeting['meeting_number'] ?? 'Reunión')) ?></p>
    </div>
    <a class="btn btn-outline-navy" href="<?= e((string) ($showUrl ?? url('/meetings/' . $id))) ?>">Volver al detalle</a>
</section>

<div class="page-card mb-3">
    <h3 class="page-card__title">Resumen</h3>
    <dl class="meetings-summary">
        <div><dt>Fecha</dt><dd><?= e(!empty($meeting['meeting_date']) ? date('d-m-Y', strtotime((string) $meeting['meeting_date'])) : '—') ?></dd></div>
        <div><dt>Hora</dt><dd><?= e(!empty($meeting['meeting_time']) ? substr((string) $meeting['meeting_time'], 0, 5) : '—') ?></dd></div>
        <div><dt>Lugar</dt><dd><?= e((string) ($meeting['meeting_place'] ?? '—')) ?></dd></div>
    </dl>
</div>

<div class="page-card mb-3">
    <h3 class="page-card__title">Temas y acuerdos</h3>
    <p class="mb-2"><strong>Temas:</strong></p>
    <ol class="mb-3">
        <?php foreach ($meeting['topics'] ?? [] as $topic): ?>
            <li class="mb-1"><?= nl2br(e((string) ($topic['description'] ?? ''))) ?></li>
        <?php endforeach; ?>
    </ol>
    <p class="mb-2"><strong>Acuerdos:</strong></p>
    <ol class="mb-0">
        <?php foreach ($meeting['agreements'] ?? [] as $agreement): ?>
            <li class="mb-1"><?= nl2br(e((string) ($agreement['description'] ?? ''))) ?></li>
        <?php endforeach; ?>
    </ol>
</div>

<?php if (!$hasSignature): ?>
    <div class="alert alert-warning">
        Debe registrar su firma simple en
        <a href="<?= e(url('/meetings/profile/signature')) ?>">Mi Firma</a>
        antes de continuar.
    </div>
<?php else: ?>
    <div class="page-card mb-3">
        <h3 class="page-card__title">Firma a aplicar</h3>
        <div class="meetings-signature-preview">
            <img src="<?= e((string) ($signatureImageUrl ?? '')) ?>" alt="Mi firma simple">
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <form method="post" action="<?= e((string) ($signAction ?? url('/meetings/' . $id . '/sign'))) ?>"
              data-confirm="Confirma que revisó el contenido y desea registrar su firma simple en este acta."
              data-confirm-title="Registrar firma">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-navy">Firmar reunión</button>
        </form>
    </div>
<?php endif; ?>

<div class="page-card">
    <h3 class="page-card__title">Solicitar corrección</h3>
    <p class="text-secondary">Si detecta errores en el registro, puede devolverlo al responsable indicando el motivo.</p>
    <form method="post" action="<?= e((string) ($correctionAction ?? url('/meetings/' . $id . '/request-correction'))) ?>">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="reason">Motivo</label>
            <textarea class="form-control" id="reason" name="reason" rows="4" minlength="10" maxlength="1000" required placeholder="Describa qué debe corregirse…"></textarea>
        </div>
        <button type="submit" class="btn btn-outline-navy">Enviar solicitud de corrección</button>
    </form>
</div>
