<section class="page-toolbar">
    <div>
        <h2 class="page-card__title mb-1">Búsqueda por RUT</h2>
        <p class="text-secondary mb-0">Consulte solicitudes históricas de grabación en el módulo CCTV.</p>
    </div>
</section>

<?= cameras_nav($camerasNav ?? []) ?>

<div class="page-card mb-3">
    <form method="get" action="<?= e(url('/cctv/visits/search-rut')) ?>" class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="rut">RUT</label>
            <input type="text" class="form-control" id="rut" name="rut" value="<?= e((string) ($rut ?? '')) ?>" placeholder="12.345.678-5" required>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-navy">Buscar</button>
        </div>
    </form>
</div>

<?php if (($rut ?? '') !== ''): ?>
    <div class="page-card">
        <?php if (($results ?? []) === []): ?>
            <p class="text-secondary mb-0">No se encontraron solicitudes para el RUT indicado.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>N.º solicitud</th>
                            <th>Fecha solicitud</th>
                            <th>Solicitante</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $item): ?>
                            <tr>
                                <td><code><?= e((string) ($item['request_number'] ?? '—')) ?></code></td>
                                <td><?= e(date('d/m/Y', strtotime((string) ($item['visit_date'] ?? '')))) ?></td>
                                <td><?= e((string) ($item['requester_name'] ?? '—')) ?></td>
                                <td><?= e($statusCatalog->label((string) ($item['status'] ?? ''))) ?></td>
                                <td class="text-end"><a class="btn btn-outline-navy btn-sm" href="<?= e(url('/cctv/recording-requests/' . ($item['id'] ?? ''))) ?>">Ver</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
