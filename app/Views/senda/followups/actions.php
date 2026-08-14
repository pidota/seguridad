<?php
$item = $item ?? [];
$returnTo = in_array($returnTo ?? '', ['attention', 'history'], true) ? (string) $returnTo : '';
$returnQuery = $returnTo !== '' ? '?return=' . rawurlencode($returnTo) : '';
$id = (int) ($item['id'] ?? 0);
?>
<?php if (hasPermission('senda.followups.view')): ?>
    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/senda/follow-ups/' . $id) . $returnQuery) ?>">Ver</a>
<?php endif; ?>
<?php if (hasPermission('senda.followups.edit')): ?>
    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/senda/follow-ups/' . $id . '/edit') . $returnQuery) ?>">Editar</a>
<?php endif; ?>
<?php if (hasPermission('senda.followups.delete')): ?>
    <form method="post" action="<?= e(url('/senda/follow-ups/' . $id)) ?>" class="d-inline" data-confirm="Esta acción anulará el seguimiento." data-confirm-title="Eliminar seguimiento">
        <?= csrf_field() ?>
        <?= method_field('DELETE') ?>
        <?php if ($returnTo !== ''): ?>
            <input type="hidden" name="return" value="<?= e($returnTo) ?>">
        <?php endif; ?>
        <button type="submit" class="btn btn-outline-navy btn-sm">Eliminar</button>
    </form>
<?php endif; ?>
