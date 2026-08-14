<?php
$item = $item ?? [];
$id = (int) ($item['id'] ?? 0);
?>
<?php if (hasPermission('cctv.log.view')): ?>
    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/cctv/log/' . $id)) ?>">Ver</a>
<?php endif; ?>
<?php if (cctv_can_edit_log_entry($item ?? [])): ?>
    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/cctv/log/' . $id . '/edit')) ?>">Editar</a>
<?php endif; ?>
<?= \Core\View::make('camera/log/_cancel-form', [
    'id' => $id,
    'canCancelLogEntry' => cctv_can_cancel_log_entry($item ?? []),
], null) ?>
