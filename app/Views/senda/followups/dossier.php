<?php
$dossier = $dossier ?? [];
$person = $dossier['person'] ?? [];
$metrics = $dossier['metrics'] ?? [];
$permissions = $dossier['permissions'] ?? [];
$tab = in_array($tab ?? '', ['historial', 'atenciones', 'fichas', 'seguimientos'], true) ? $tab : 'historial';
$order = ($order ?? 'desc') === 'asc' ? 'asc' : 'desc';
$personId = (int) ($person['id'] ?? 0);
$baseUrl = url('/senda/follow-ups/person/' . $personId);
$tabUrl = static function (string $name) use ($baseUrl, $order): string {
    return $baseUrl . '?' . http_build_query(['tab' => $name, 'order' => $order]);
};
$lastAction = $metrics['last_action'] ?? null;
$next = $metrics['next_follow_up'] ?? null;
$lastAttention = $metrics['last_attention'] ?? null;
$lastFollowUp = $metrics['last_follow_up'] ?? null;
$firstAttention = $metrics['first_attention'] ?? null;
$dash = static fn (mixed $value): string => trim((string) $value) !== '' ? (string) $value : '—';
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">SENDA</p>
        <h2 class="page-card__title mb-1">Seguimiento de Persona</h2>
        <p class="text-secondary mb-0">Ficha histórica consolidada de todo lo realizado en SENDA.</p>
    </div>
    <div class="page-toolbar__actions">
        <a class="btn btn-outline-navy" href="<?= e(url('/senda/follow-ups')) ?>">Nueva búsqueda</a>
        <?php if (hasPermission('senda.followups.create') && $personId > 0): ?>
            <a class="btn btn-navy" href="<?= e(url('/senda/follow-ups/create') . '?person=' . $personId . '&return=history') ?>">Registrar Seguimiento</a>
        <?php endif; ?>
        <?php if (hasPermission('senda.attentions.create') && $personId > 0): ?>
            <form method="post" action="<?= e(url('/senda/people/' . $personId . '/use')) ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="attention">
                <button type="submit" class="btn btn-gold">Nueva Atención</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?= senda_nav($sendaNav ?? []) ?>

<article class="senda-dossier-card">
    <div class="senda-dossier-card__identity">
        <p class="senda-entry-bar__kicker mb-1">Persona</p>
        <h3 class="senda-dossier-card__name"><?= e((string) ($person['full_name'] ?? '')) ?></h3>
        <p class="senda-dossier-card__rut mb-0"><?= e((string) ($person['rut'] ?? '')) ?></p>
    </div>
    <dl class="senda-person-card__meta senda-person-card__meta--wide senda-dossier-card__meta">
        <div>
            <dt>Fecha de nacimiento</dt>
            <dd><?= e(!empty($person['birth_date']) ? date('d-m-Y', strtotime((string) $person['birth_date'])) : '—') ?></dd>
        </div>
        <div>
            <dt>Edad actual</dt>
            <dd><?= isset($person['age']) && $person['age'] !== null ? e((string) $person['age']) . ' años' : '—' ?></dd>
        </div>
        <div>
            <dt>Teléfono</dt>
            <dd><?= e($dash($person['phone'] ?? null)) ?></dd>
        </div>
        <div>
            <dt>Correo electrónico</dt>
            <dd><?= e($dash($person['email'] ?? null)) ?></dd>
        </div>
        <div>
            <dt>Domicilio</dt>
            <dd><?= e($dash($person['address'] ?? null)) ?></dd>
        </div>
        <div>
            <dt>Educación</dt>
            <dd><?= e($dash($person['education'] ?? null)) ?></dd>
        </div>
        <div>
            <dt>Ocupación</dt>
            <dd><?= e($dash($person['occupation'] ?? null)) ?></dd>
        </div>
        <div>
            <dt>Primera atención</dt>
            <dd><?= e(!empty($firstAttention['datetime_label']) ? (string) $firstAttention['datetime_label'] : '—') ?></dd>
        </div>
    </dl>
    <div class="senda-dossier-card__status">
        <div>
            <p class="senda-entry-bar__kicker mb-1">Última atención SENDA</p>
            <p class="mb-0">
                <?php if ($lastAttention): ?>
                    <?= e((string) $lastAttention['entry_label']) ?>
                    · <?= e((string) $lastAttention['datetime_label']) ?>
                    · <?= e((string) $lastAttention['attention_number']) ?>
                <?php else: ?>
                    Sin atenciones registradas
                <?php endif; ?>
            </p>
        </div>
        <div>
            <p class="senda-entry-bar__kicker mb-1">Último seguimiento</p>
            <p class="mb-0">
                <?php if ($lastFollowUp): ?>
                    <?= e((string) ($lastFollowUp['contact_type_label'] ?? 'Seguimiento')) ?>
                    · <?= e(date('d-m-Y', strtotime((string) $lastFollowUp['follow_up_date']))) ?>
                    <?= !empty($lastFollowUp['follow_up_time']) ? e((string) $lastFollowUp['follow_up_time']) : '' ?>
                    · <?= e((string) ($lastFollowUp['result_label'] ?? '')) ?>
                <?php else: ?>
                    Sin seguimientos
                <?php endif; ?>
            </p>
        </div>
        <div>
            <p class="senda-entry-bar__kicker mb-1">Última gestión SENDA</p>
            <p class="mb-0">
                <?php if ($lastAction): ?>
                    <?= e((string) ($lastAction['type_label'] ?? '')) ?>
                    <?php if (!empty($lastAction['badge']) && $lastAction['badge'] !== $lastAction['type_label']): ?>
                        · <?= e((string) $lastAction['badge']) ?>
                    <?php endif; ?>
                    · <?= e((string) ($lastAction['datetime_label'] ?? '')) ?>
                    <?php if (!empty($lastAction['officer'])): ?>
                        · Funcionario: <?= e((string) $lastAction['officer']) ?>
                    <?php endif; ?>
                <?php else: ?>
                    Sin gestiones registradas
                <?php endif; ?>
            </p>
        </div>
    </div>
</article>

<?php
$nextTone = (string) ($next['tone'] ?? '');
$nextClass = 'senda-next-banner';
if ($nextTone === 'overdue') {
    $nextClass .= ' is-overdue';
} elseif ($nextTone === 'due') {
    $nextClass .= ' is-due';
}
?>
<div class="<?= e($nextClass) ?>">
    <p class="senda-entry-bar__kicker mb-1">Próximo seguimiento</p>
    <?php if ($next): ?>
        <p class="senda-next-banner__date mb-1"><?= e((string) $next['date_label']) ?></p>
        <p class="mb-0"><strong><?= e((string) $next['status_label']) ?></strong></p>
    <?php else: ?>
        <p class="senda-next-banner__date mb-0">Sin seguimiento pendiente</p>
    <?php endif; ?>
</div>

<div class="senda-metric-grid">
    <a class="senda-metric-card" href="<?= e($tabUrl('atenciones')) ?>">
        <span class="senda-metric-card__value"><?= (int) ($metrics['attentions_count'] ?? 0) ?></span>
        <span class="senda-metric-card__label">Atenciones</span>
    </a>
    <a class="senda-metric-card" href="<?= e($tabUrl('fichas')) ?>">
        <span class="senda-metric-card__value"><?= (int) ($metrics['referrals_count'] ?? 0) ?></span>
        <span class="senda-metric-card__label">Fichas de referencia</span>
    </a>
    <a class="senda-metric-card" href="<?= e($tabUrl('seguimientos')) ?>">
        <span class="senda-metric-card__value"><?= (int) ($metrics['followups_count'] ?? 0) ?></span>
        <span class="senda-metric-card__label">Seguimientos realizados</span>
    </a>
    <div class="senda-metric-card senda-metric-card--static">
        <span class="senda-metric-card__value senda-metric-card__value--date">
            <?= e($next ? (string) $next['date_label'] : '—') ?>
        </span>
        <span class="senda-metric-card__label"><?= e($next ? (string) $next['status_label'] : 'Sin seguimiento pendiente') ?></span>
    </div>
</div>

<ul class="nav senda-dossier-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'historial' ? 'active' : '' ?>" href="<?= e($tabUrl('historial')) ?>">Historial</a>
    </li>
    <?php if (!empty($permissions['attentions'])): ?>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'atenciones' ? 'active' : '' ?>" href="<?= e($tabUrl('atenciones')) ?>">Atenciones</a>
        </li>
    <?php endif; ?>
    <?php if (!empty($permissions['referrals'])): ?>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'fichas' ? 'active' : '' ?>" href="<?= e($tabUrl('fichas')) ?>">Fichas</a>
        </li>
    <?php endif; ?>
    <?php if (!empty($permissions['followups'])): ?>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'seguimientos' ? 'active' : '' ?>" href="<?= e($tabUrl('seguimientos')) ?>">Seguimientos</a>
        </li>
    <?php endif; ?>
</ul>

<?php if ($tab === 'historial'): ?>
    <div class="page-card">
        <div class="page-toolbar mb-3">
            <h3 class="page-card__title mb-0">Historial SENDA</h3>
            <div class="btn-group">
                <a class="btn btn-sm <?= $order === 'desc' ? 'btn-navy' : 'btn-outline-navy' ?>" href="<?= e($baseUrl . '?' . http_build_query(['tab' => 'historial', 'order' => 'desc'])) ?>">Más reciente primero</a>
                <a class="btn btn-sm <?= $order === 'asc' ? 'btn-navy' : 'btn-outline-navy' ?>" href="<?= e($baseUrl . '?' . http_build_query(['tab' => 'historial', 'order' => 'asc'])) ?>">Más antiguo primero</a>
            </div>
        </div>
        <?php if (($dossier['timeline'] ?? []) === []): ?>
            <p class="text-secondary mb-0">Aún no hay actuaciones SENDA para esta persona.</p>
        <?php else: ?>
            <ol class="senda-timeline">
                <?php foreach ($dossier['timeline'] as $event): ?>
                    <li class="senda-timeline__item senda-timeline__item--<?= e((string) ($event['tone'] ?? $event['type'])) ?>">
                        <div class="senda-timeline__marker" aria-hidden="true">
                            <i class="bi <?= e((string) ($event['icon'] ?? 'bi-circle')) ?>"></i>
                        </div>
                        <div class="senda-timeline__body">
                            <p class="senda-timeline__when"><?= e((string) ($event['datetime_label'] ?? '')) ?></p>
                            <p class="senda-timeline__badges">
                                <span class="senda-badge senda-badge--<?= e((string) ($event['tone'] ?? 'attention')) ?>">
                                    <i class="bi <?= e((string) ($event['icon'] ?? 'bi-circle')) ?>"></i>
                                    <?= e((string) ($event['type_label'] ?? '')) ?>
                                </span>
                                <?php if (!empty($event['badge']) && $event['badge'] !== $event['type_label']): ?>
                                    <span class="senda-badge senda-badge--<?= e((string) ($event['tone'] ?? 'attention')) ?>"><?= e((string) $event['badge']) ?></span>
                                <?php endif; ?>
                            </p>
                            <?php foreach ($event['lines'] ?? [] as $line): ?>
                                <p class="senda-timeline__line mb-1"><?= e((string) $line) ?></p>
                            <?php endforeach; ?>
                            <?php if (!empty($event['url'])): ?>
                                <a class="small" href="<?= e((string) $event['url']) ?>">Ver detalle</a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
<?php elseif ($tab === 'atenciones'): ?>
    <div class="page-card">
        <h3 class="page-card__title">Atenciones</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>N.º atención</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Tipo</th>
                        <th>Funcionario</th>
                        <th>Ficha</th>
                        <th>Seguimientos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($dossier['attentions'] ?? []) === []): ?>
                        <tr><td colspan="8" class="text-secondary">No hay atenciones.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dossier['attentions'] as $item): ?>
                            <tr>
                                <td><code><?= e((string) $item['attention_number']) ?></code></td>
                                <td><?= e(!empty($item['attention_date']) ? date('d-m-Y', strtotime((string) $item['attention_date'])) : '—') ?></td>
                                <td><?= e($item['attention_time'] !== '' ? (string) $item['attention_time'] : '—') ?></td>
                                <td>
                                    <span class="senda-badge senda-badge--<?= e((string) $item['entry_tone']) ?>">
                                        <i class="bi <?= $item['entry_type'] === \App\Services\Senda\EntryType::DERIVACION ? 'bi-signpost-split' : 'bi-person-walking' ?>"></i>
                                        <?= e((string) $item['entry_label']) ?>
                                    </span>
                                </td>
                                <td><?= e((string) $item['officer']) ?></td>
                                <td><?= !empty($item['has_ficha']) ? 'Sí' : 'No' ?></td>
                                <td><?= (int) $item['followup_count'] ?></td>
                                <td class="text-end text-nowrap">
                                    <?php if (!empty($item['url'])): ?>
                                        <a class="btn btn-outline-navy btn-sm" href="<?= e((string) $item['url']) ?>">Ver Atención</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php elseif ($tab === 'fichas'): ?>
    <div class="page-card">
        <h3 class="page-card__title">Fichas de referencia</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>N.º atención</th>
                        <th>Fecha de ficha</th>
                        <th>Estado</th>
                        <th>Tamizaje aplicado</th>
                        <th>Fecha finalización</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($dossier['referrals'] ?? []) === []): ?>
                        <tr><td colspan="6" class="text-secondary">No hay fichas de referencia.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dossier['referrals'] as $item): ?>
                            <tr>
                                <td><code><?= e((string) $item['attention_number']) ?></code></td>
                                <td><?= e(!empty($item['request_date']) ? date('d-m-Y', strtotime((string) $item['request_date'])) : '—') ?></td>
                                <td><?= e((string) $item['status_label']) ?></td>
                                <td><?= e((string) $item['screening_label']) ?></td>
                                <td><?= e((string) $item['finished_label']) ?></td>
                                <td class="text-end">
                                    <?php if (!empty($item['url'])): ?>
                                        <a class="btn btn-outline-navy btn-sm" href="<?= e((string) $item['url']) ?>">Ver Ficha</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($item['screening_used']) && !empty($item['assist'])): ?>
                                <tr>
                                    <td colspan="6">
                                        <?= \Core\View::make('senda/followups/assist-table', ['items' => $item['assist']], null) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="page-card">
        <h3 class="page-card__title">Seguimientos</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Atención asociada</th>
                        <th>Tipo de contacto</th>
                        <th>Resultado</th>
                        <th>Funcionario</th>
                        <th>Próximo seguimiento</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($dossier['follow_ups'] ?? []) === []): ?>
                        <tr><td colspan="8" class="text-secondary">No hay seguimientos.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dossier['follow_ups'] as $item): ?>
                            <tr>
                                <td><?= e(date('d-m-Y', strtotime((string) $item['follow_up_date']))) ?></td>
                                <td><?= e((string) ($item['follow_up_time'] !== '' ? $item['follow_up_time'] : '—')) ?></td>
                                <td><code><?= e((string) ($item['attention_number'] ?? '')) ?></code></td>
                                <td><?= e((string) ($item['contact_type_label'] ?? '—')) ?></td>
                                <td><?= e((string) ($item['result_label'] ?? '—')) ?></td>
                                <td><?= e((string) ($item['created_by_name'] ?? '—')) ?></td>
                                <td>
                                    <?php if (!empty($item['is_pending']) && !empty($item['next_follow_up_date'])): ?>
                                        <?= e(date('d-m-Y', strtotime((string) $item['next_follow_up_date']))) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?= \Core\View::make('senda/followups/actions', [
                                        'item' => $item,
                                        'returnTo' => 'history',
                                    ], null) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
