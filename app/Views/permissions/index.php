<section class="page-toolbar">
    <div>
        <h2 class="page-card__title mb-1">Catálogo de permisos</h2>
        <p class="text-secondary mb-0">Los permisos se asignan a roles. No se editan desde esta pantalla.</p>
    </div>
</section>

<?php foreach ($grouped as $module => $items): ?>
    <div class="page-card mb-3">
        <h3 class="page-card__title"><?= e(permission_module_label($module)) ?></h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Permiso</th>
                        <th>Identificador</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['name']) ?></td>
                            <td><code><?= e($item['slug']) ?></code></td>
                            <td><?= e((string) ($item['description'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>
