<?php
$filters = $filters ?? [];
$matches = $matches ?? [];
$notFound = !empty($notFound);
$agenda = !empty($agenda);
$canCreateAttention = hasPermission('senda.attentions.create') || hasPermission('senda.people.create');
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-1">Seguimiento SENDA</h2>
        <p class="text-secondary mb-0">Consulte la ficha histórica de la persona. El RUT es el identificador principal.</p>
    </div>
    <?php if (hasPermission('senda.followups.create') && $agenda): ?>
        <a href="<?= e(url('/senda/follow-ups/create')) ?>" class="btn btn-outline-navy">Nuevo seguimiento</a>
    <?php endif; ?>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<div class="senda-search-hero">
    <p class="senda-search-hero__kicker">Consulta integral</p>
    <h3 class="senda-search-hero__title">Buscar persona por RUT</h3>
    <p class="senda-search-hero__text">Ingrese el RUT para ver atenciones, fichas, tamizaje ASSIST y seguimientos. La búsqueda no crea un registro nuevo.</p>
    <form method="post" action="<?= e(url('/senda/follow-ups/search')) ?>" novalidate class="senda-search-hero__form">
        <?= csrf_field() ?>
        <div class="senda-search-hero__row">
            <div class="senda-search-hero__field">
                <label class="form-label" for="rut">RUT de la persona</label>
                <input
                    class="form-control form-control-lg <?= has_error('rut') ? 'is-invalid' : '' ?>"
                    id="rut"
                    name="rut"
                    value="<?= e((string) ($rut ?? old('rut'))) ?>"
                    placeholder="12.345.678-5"
                    autocomplete="off"
                    data-rut-input
                >
                <?php if (has_error('rut')): ?>
                    <div class="invalid-feedback"><?= e((string) error('rut')) ?></div>
                <?php endif; ?>
            </div>
            <button class="btn btn-gold btn-lg senda-search-hero__submit" type="submit">Buscar</button>
        </div>
        <details class="senda-search-hero__extra" <?= trim((string) ($name ?? '')) !== '' ? 'open' : '' ?>>
            <summary>Búsqueda secundaria por nombre o apellido</summary>
            <div class="mt-3">
                <label class="form-label" for="name">Nombre o apellido</label>
                <input class="form-control <?= has_error('name') ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e((string) ($name ?? old('name'))) ?>" maxlength="120">
            </div>
        </details>
    </form>
</div>

<?php if ($notFound): ?>
    <div class="page-card mt-3">
        <h3 class="page-card__title">Persona no encontrada</h3>
        <p class="mb-3">No existen registros SENDA asociados a este RUT.</p>
        <?php if ($canCreateAttention): ?>
            <a class="btn btn-navy" href="<?= e(url('/senda/people/create') . '?next=attention' . (!empty($rut) ? '&rut=' . rawurlencode((string) $rut) : '')) ?>">Registrar Nueva Atención</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($matches !== []): ?>
    <div class="page-card mt-3">
        <h3 class="page-card__title">Varias personas coinciden</h3>
        <p class="text-secondary">Seleccione la persona correcta. El RUT identifica de manera inequívoca el historial.</p>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>RUT</th>
                        <th>Atenciones</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matches as $item): ?>
                        <tr>
                            <td><?= e((string) ($item['full_name'] ?? '')) ?></td>
                            <td><?= e((string) ($item['rut'] ?? '')) ?></td>
                            <td><?= (int) ($item['attentions_count'] ?? 0) ?></td>
                            <td class="text-end">
                                <a class="btn btn-navy btn-sm" href="<?= e(url('/senda/follow-ups/person/' . $item['id'])) ?>">Ver seguimiento</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($agenda): ?>
    <div class="mt-4">
        <?= \Core\View::make('senda/followups/agenda', [
            'followups' => $followups ?? [],
            'total' => $total ?? 0,
            'page' => $page ?? 1,
            'pages' => $pages ?? 1,
            'filters' => $filters,
            'contactTypes' => $contactTypes ?? [],
            'results' => $results ?? [],
            'staff' => $staff ?? [],
        ], null) ?>
    </div>
<?php else: ?>
    <p class="text-secondary mt-3 mb-0">
        <a href="<?= e(url('/senda/follow-ups') . '?status=' . \App\Services\Senda\FollowUpStatus::PENDING) ?>">Ver agenda de seguimientos pendientes</a>
    </p>
<?php endif; ?>
