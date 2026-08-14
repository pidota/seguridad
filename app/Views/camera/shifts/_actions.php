<?php
$item = $item ?? [];
$id = (int) ($item['id'] ?? 0);
$logQuery = array_filter([
    'date_from' => (string) ($item['shift_date'] ?? ''),
    'date_to' => (string) ($item['shift_date'] ?? ''),
    'created_by' => (int) ($item['operator_id'] ?? 0) > 0 ? (string) $item['operator_id'] : null,
], static fn ($value): bool => $value !== null && $value !== '');
?>
<?php if (!empty($canViewLog)): ?>
    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/cctv/log?' . http_build_query($logQuery))) ?>">Ver registros</a>
<?php endif; ?>
<a class="btn btn-navy btn-sm" href="<?= e(url('/cctv/shifts/' . $id)) ?>">Ver turno</a>
<?php if (!empty($item['is_open'])): ?>
    <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/cctv#turno-activo')) ?>">Turno activo</a>
<?php endif; ?>
