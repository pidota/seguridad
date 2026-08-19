<?php

$meeting = is_array($meeting ?? null) ? $meeting : null;
$isEdit = $meeting !== null;
$sourceModule = (string) ($sourceModule ?? 'admin');
$listUrl = (string) ($listUrl ?? url('/meetings'));
$createUrl = $sourceModule === 'senda' ? url('/senda/meetings/create') : url('/meetings/create');

$v = static function (string $key, mixed $fallback = '') use ($meeting): string {
    $value = old($key, $meeting[$key] ?? $fallback);

    return $value === null ? '' : (string) $value;
};

$participants = old('participants', $meeting['participants'] ?? []);
if (!is_array($participants) || $participants === []) {
    $participants = [['participant_type' => 'internal', 'signature_required' => 1]];
}

$topics = old('topics', $meeting['topics'] ?? []);
if (!is_array($topics) || $topics === []) {
    $topics = [['description' => '']];
}

$agreements = old('agreements', $meeting['agreements'] ?? []);
if (!is_array($agreements) || $agreements === []) {
    $agreements = [['description' => '']];
}

$nextRequired = $v('next_meeting_required', !empty($meeting['next_meeting_required']) ? 'yes' : 'no');
$includeCreator = old('include_creator', '1');

?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1"><?= $sourceModule === 'senda' ? 'SENDA' : 'Reuniones' ?></p>
        <h2 class="page-card__title mb-0"><?= $isEdit ? 'Editar Registro de Reunión' : 'Registro de Reunión' ?></h2>
    </div>
</section>

<?php if (!empty($sendaNav)): ?>
    <?= senda_nav($sendaNav) ?>
<?php endif; ?>

<form method="post" action="<?= e((string) ($formAction ?? url('/meetings'))) ?>" class="meetings-form" data-meetings-form novalidate autocomplete="off">
    <?= csrf_field() ?>

    <div class="page-card mb-3">
        <h3 class="page-card__title">Datos generales</h3>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label" for="meeting_date">Fecha</label>
                <input type="date" class="form-control <?= has_error('meeting_date') ? 'is-invalid' : '' ?>" id="meeting_date" name="meeting_date" value="<?= e($v('meeting_date', date('Y-m-d'))) ?>" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="meeting_time">Hora</label>
                <input type="time" class="form-control <?= has_error('meeting_time') ? 'is-invalid' : '' ?>" id="meeting_time" name="meeting_time" value="<?= e(substr($v('meeting_time', date('H:i')), 0, 5)) ?>" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="meeting_place">Lugar de la reunión</label>
                <input class="form-control <?= has_error('meeting_place') ? 'is-invalid' : '' ?>" id="meeting_place" name="meeting_place" value="<?= e($v('meeting_place')) ?>" required>
            </div>
        </div>
    </div>

    <div class="page-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="page-card__title mb-0">Participantes</h3>
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-outline-navy" data-meetings-add-participant="internal">+ Interno</button>
                <button type="button" class="btn btn-sm btn-outline-navy" data-meetings-add-participant="external">+ Externo</button>
            </div>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="include_creator" name="include_creator" <?= (string) $includeCreator === '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="include_creator">Incluirme como participante</label>
        </div>
        <div data-meetings-participants>
            <?php foreach (array_values($participants) as $index => $participant): ?>
                <?= \Core\View::make('meetings/_participant_row', ['index' => $index, 'row' => is_array($participant) ? $participant : []], null) ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="page-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="page-card__title mb-0">Temas abordados</h3>
            <button type="button" class="btn btn-sm btn-outline-navy" data-meetings-add-topic>+ Agregar Tema</button>
        </div>
        <div data-meetings-topics>
            <?php foreach (array_values($topics) as $index => $topic): ?>
                <?= \Core\View::make('meetings/_topic_row', ['index' => $index, 'row' => is_array($topic) ? $topic : []], null) ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="page-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="page-card__title mb-0">Acuerdos</h3>
            <button type="button" class="btn btn-sm btn-outline-navy" data-meetings-add-agreement>+ Agregar Acuerdo</button>
        </div>
        <div data-meetings-agreements>
            <?php foreach (array_values($agreements) as $index => $agreement): ?>
                <?= \Core\View::make('meetings/_agreement_row', ['index' => $index, 'row' => is_array($agreement) ? $agreement : []], null) ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="page-card mb-3">
        <h3 class="page-card__title">Observaciones y compromisos adicionales</h3>
        <textarea class="form-control" name="additional_notes" rows="4"><?= e($v('additional_notes')) ?></textarea>
    </div>

    <div class="page-card mb-3">
        <h3 class="page-card__title">Próxima reunión o seguimiento</h3>
        <div class="mb-3">
            <label class="form-label d-block">¿Requiere próxima reunión o seguimiento?</label>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="next_meeting_required" id="next_meeting_yes" value="yes" <?= $nextRequired === 'yes' ? 'checked' : '' ?> data-meetings-next-toggle>
                <label class="form-check-label" for="next_meeting_yes">Sí</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="next_meeting_required" id="next_meeting_no" value="no" <?= $nextRequired !== 'yes' ? 'checked' : '' ?> data-meetings-next-toggle>
                <label class="form-check-label" for="next_meeting_no">No</label>
            </div>
        </div>
        <div data-meetings-next-fields <?= $nextRequired === 'yes' ? '' : 'hidden' ?>>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="next_meeting_date">Fecha</label>
                    <input type="date" class="form-control" id="next_meeting_date" name="next_meeting_date" value="<?= e($v('next_meeting_date')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="next_meeting_time">Hora (opcional)</label>
                    <input type="time" class="form-control" id="next_meeting_time" name="next_meeting_time" value="<?= e(substr($v('next_meeting_time'), 0, 5)) ?>">
                </div>
            </div>
            <div class="mb-0">
                <label class="form-label" for="next_meeting_notes">Observaciones</label>
                <textarea class="form-control" id="next_meeting_notes" name="next_meeting_notes" rows="3"><?= e($v('next_meeting_notes')) ?></textarea>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-navy">Guardar Borrador</button>
        <a class="btn btn-outline-navy" href="<?= e((string) ($cancelUrl ?? $listUrl)) ?>">Cancelar</a>
    </div>
</form>

<template id="meetings-participant-internal-template">
    <?= \Core\View::make('meetings/_participant_row', ['index' => '__INDEX__', 'row' => ['participant_type' => 'internal', 'signature_required' => 1]], null) ?>
</template>
<template id="meetings-participant-external-template">
    <?= \Core\View::make('meetings/_participant_row', ['index' => '__INDEX__', 'row' => ['participant_type' => 'external']], null) ?>
</template>
<template id="meetings-topic-template">
    <?= \Core\View::make('meetings/_topic_row', ['index' => '__INDEX__', 'row' => []], null) ?>
</template>
<template id="meetings-agreement-template">
    <?= \Core\View::make('meetings/_agreement_row', ['index' => '__INDEX__', 'row' => []], null) ?>
</template>

<script>
window.MEETINGS_USER_SEARCH_URL = <?= json_encode(url('/meetings/users/search'), JSON_THROW_ON_ERROR) ?>;
</script>
