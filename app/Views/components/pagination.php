<?php
$pages = max(1, (int) ($pages ?? 1));
$page = min(max(1, (int) ($page ?? 1)), $pages);
$base = rtrim((string) ($base ?? ''), '?');
$query = $query ?? [];

if ($pages <= 1) {
    return;
}

$link = static function (int $target) use ($base, $query): string {
    $query['page'] = $target;
    return $base . '?' . http_build_query($query);
};
?>
<nav class="pagination-bar" aria-label="Paginación">
    <?php if ($page > 1): ?>
        <a href="<?= e($link($page - 1)) ?>">Anterior</a>
    <?php endif; ?>
    <span>Página <?= (int) $page ?> de <?= (int) $pages ?></span>
    <?php if ($page < $pages): ?>
        <a href="<?= e($link($page + 1)) ?>">Siguiente</a>
    <?php endif; ?>
</nav>
