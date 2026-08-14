<?php
$isEdit = !empty($record['id']);
$attention = $attention ?? [];
$person = $person ?? [];
$v = static function (string $key, mixed $fallback = '') use ($record): string {
    $value = $record[$key] ?? $fallback;
    if ($value === null) {
        $value = '';
    }

    return (string) old($key, $value);
};
$optionList = static function (array $options, string $selected): string {
    $html = '';
    foreach ($options as $option) {
        $value = (string) $option['value'];
        $isSelected = $selected === $value ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $isSelected . '>' . e((string) $option['label']) . '</option>';
    }

    return $html;
};
$contactType = $v('contact_type');
$result = $v('result');
$requires = $v('requires_follow_up');
$returnTo = in_array($returnTo ?? '', ['attention', 'history'], true) ? (string) $returnTo : '';
$attentionId = (int) ($attention['id'] ?? 0);
$personId = (int) ($person['id'] ?? 0);
$backUrl = match ($returnTo) {
    'history' => $personId > 0 ? url('/senda/follow-ups/person/' . $personId) : url('/senda/follow-ups'),
    'attention' => $attentionId > 0 ? url('/senda/attentions/' . $attentionId . '/edit') : url('/senda/follow-ups'),
    default => url('/senda/follow-ups') . ($attentionId > 0 ? '?attention=' . $attentionId : ''),
};
$backLabel = match ($returnTo) {
    'history' => 'Volver al seguimiento',
    'attention' => 'Volver a la atención',
    default => 'Volver al listado',
};
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-1"><?= $isEdit ? 'Editar seguimiento' : 'Nuevo seguimiento' ?></h2>
        <?php if (!empty($attention['attention_number'])): ?>
            <p class="mb-0">
                Atención
                <span class="senda-badge senda-badge--referral"><?= e((string) $attention['attention_number']) ?></span>
                <?php if (!empty($person['full_name'])): ?>
                    · <?= e((string) $person['full_name']) ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
    <a class="btn btn-outline-navy" href="<?= e($backUrl) ?>"><?= e($backLabel) ?></a>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<form
    method="post"
    action="<?= e($isEdit ? url('/senda/follow-ups/' . $record['id']) : url('/senda/follow-ups')) ?>"
    novalidate
    class="page-card page-card--xl"
    data-senda-followup-form
>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <?= method_field('PUT') ?>
    <?php endif; ?>
    <?php if ($returnTo !== ''): ?>
        <input type="hidden" name="return" value="<?= e($returnTo) ?>">
    <?php endif; ?>
    <input type="hidden" name="senda_attention_id" value="<?= e((string) ($attention['id'] ?? $v('senda_attention_id'))) ?>">

    <h3 class="page-card__title">Datos del seguimiento</h3>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label" for="follow_up_date">Fecha</label>
            <input type="date" class="form-control <?= has_error('follow_up_date') ? 'is-invalid' : '' ?>" id="follow_up_date" name="follow_up_date" value="<?= e($v('follow_up_date')) ?>">
            <?php if (has_error('follow_up_date')): ?><div class="invalid-feedback"><?= e((string) error('follow_up_date')) ?></div><?php endif; ?>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label" for="follow_up_time">Hora</label>
            <input type="time" class="form-control <?= has_error('follow_up_time') ? 'is-invalid' : '' ?>" id="follow_up_time" name="follow_up_time" value="<?= e($v('follow_up_time')) ?>">
            <?php if (has_error('follow_up_time')): ?><div class="invalid-feedback"><?= e((string) error('follow_up_time')) ?></div><?php endif; ?>
        </div>
    </div>

    <h3 class="page-card__title mt-2">Tipo de contacto</h3>
    <div class="mb-3">
        <label class="form-label" for="contact_type">Tipo de contacto</label>
        <select class="form-select <?= has_error('contact_type') ? 'is-invalid' : '' ?>" id="contact_type" name="contact_type" data-senda-other-toggle="contact">
            <option value="">Seleccione</option>
            <?= $optionList($contactTypes ?? [], $contactType) ?>
        </select>
        <?php if (has_error('contact_type')): ?><div class="invalid-feedback"><?= e((string) error('contact_type')) ?></div><?php endif; ?>
    </div>
    <div class="mb-3" data-senda-other-panel="contact" <?= $contactType === 'otro' ? '' : 'hidden' ?>>
        <label class="form-label" for="contact_type_other">Especifique</label>
        <input class="form-control <?= has_error('contact_type_other') ? 'is-invalid' : '' ?>" id="contact_type_other" name="contact_type_other" value="<?= e($v('contact_type_other')) ?>" maxlength="180">
        <?php if (has_error('contact_type_other')): ?><div class="invalid-feedback"><?= e((string) error('contact_type_other')) ?></div><?php endif; ?>
    </div>

    <h3 class="page-card__title mt-2">Resultado</h3>
    <div class="mb-3">
        <label class="form-label" for="result">Resultado</label>
        <select class="form-select <?= has_error('result') ? 'is-invalid' : '' ?>" id="result" name="result" data-senda-other-toggle="result">
            <option value="">Seleccione</option>
            <?= $optionList($results ?? [], $result) ?>
        </select>
        <?php if (has_error('result')): ?><div class="invalid-feedback"><?= e((string) error('result')) ?></div><?php endif; ?>
    </div>
    <div class="mb-3" data-senda-other-panel="result" <?= $result === 'otro' ? '' : 'hidden' ?>>
        <label class="form-label" for="result_other">Especifique</label>
        <input class="form-control <?= has_error('result_other') ? 'is-invalid' : '' ?>" id="result_other" name="result_other" value="<?= e($v('result_other')) ?>" maxlength="180">
        <?php if (has_error('result_other')): ?><div class="invalid-feedback"><?= e((string) error('result_other')) ?></div><?php endif; ?>
    </div>

    <h3 class="page-card__title mt-2">Observaciones</h3>
    <div class="mb-3">
        <label class="form-label" for="notes">Observaciones del seguimiento</label>
        <textarea class="form-control senda-observations <?= has_error('notes') ? 'is-invalid' : '' ?>" id="notes" name="notes" rows="8" maxlength="4000"><?= e($v('notes')) ?></textarea>
        <?php if (has_error('notes')): ?><div class="invalid-feedback"><?= e((string) error('notes')) ?></div><?php endif; ?>
    </div>

    <h3 class="page-card__title mt-2">Próximo seguimiento</h3>
    <div class="mb-3">
        <label class="form-label" for="requires_follow_up">¿Requiere nuevo seguimiento?</label>
        <select class="form-select <?= has_error('requires_follow_up') ? 'is-invalid' : '' ?>" id="requires_follow_up" name="requires_follow_up" data-senda-next-toggle>
            <option value="">Seleccione</option>
            <?= $optionList($yesNo ?? [], $requires) ?>
        </select>
        <?php if (has_error('requires_follow_up')): ?><div class="invalid-feedback"><?= e((string) error('requires_follow_up')) ?></div><?php endif; ?>
    </div>
    <div class="mb-4" data-senda-next-panel <?= $requires === 'si' ? '' : 'hidden' ?>>
        <label class="form-label" for="next_follow_up_date">Fecha del próximo seguimiento</label>
        <input type="date" class="form-control <?= has_error('next_follow_up_date') ? 'is-invalid' : '' ?>" id="next_follow_up_date" name="next_follow_up_date" value="<?= e($v('next_follow_up_date')) ?>">
        <?php if (has_error('next_follow_up_date')): ?><div class="invalid-feedback"><?= e((string) error('next_follow_up_date')) ?></div><?php endif; ?>
    </div>

    <button class="btn btn-navy" type="submit">Guardar seguimiento</button>
    <a class="btn btn-outline-navy" href="<?= e($backUrl) ?>">Cancelar</a>
</form>
