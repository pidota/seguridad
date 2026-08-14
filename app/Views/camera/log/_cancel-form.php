<?php

$id = (int) ($id ?? 0);
$buttonClass = trim((string) ($buttonClass ?? 'btn btn-outline-danger btn-sm'));
$formClass = trim((string) ($formClass ?? 'd-inline'));

if ($id < 1 || empty($canCancelLogEntry)) {
    return;
}

?>
<form
    method="post"
    action="<?= e(url('/cctv/log/' . $id)) ?>"
    class="<?= e($formClass) ?>"
    data-confirm="El registro dejará de aparecer en la bitácora operativa, pero la acción quedará registrada en auditoría."
    data-confirm-title="¿Anular este registro?"
    data-confirm-confirm-text="Sí, anular"
>
    <?= csrf_field() ?>
    <?= method_field('DELETE') ?>
    <button type="submit" class="<?= e($buttonClass) ?>">Anular registro</button>
</form>
