<?php

$filters = $filters ?? [];
$query = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');
$hasFilters = $query !== [];

?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Oficina de la Mujer</p>
        <h2 class="page-card__title mb-1">Casos registrados</h2>
        <p class="text-secondary mb-0"><?= (int) ($total ?? 0) ?> casos</p>
    </div>
    <?php if (hasPermission('women.cases.create')): ?>
        <a href="<?= e(url('/women/cases/create')) ?>" class="btn btn-navy">Nueva denuncia</a>
    <?php endif; ?>
</section>

<?= women_nav($womenNav ?? []) ?>

<form method="get" action="<?= e(url('/women/cases')) ?>" class="page-card women-filters mb-3">
    <div class="women-filters__grid">
        <div>
            <label class="form-label" for="filter_case_number">N.º caso</label>
            <input class="form-control" id="filter_case_number" name="case_number" value="<?= e((string) ($filters['case_number'] ?? '')) ?>" placeholder="MUJER-2026-000001">
        </div>
        <div>
            <label class="form-label" for="filter_date_from">Fecha desde</label>
            <input type="date" class="form-control" id="filter_date_from" name="date_from" value="<?= e((string) ($filters['date_from'] ?? '')) ?>">
        </div>
        <div>
            <label class="form-label" for="filter_date_to">Fecha hasta</label>
            <input type="date" class="form-control" id="filter_date_to" name="date_to" value="<?= e((string) ($filters['date_to'] ?? '')) ?>">
        </div>
        <div>
            <label class="form-label" for="filter_violence_type">Tipo de violencia</label>
            <select class="form-select" id="filter_violence_type" name="violence_type_id">
                <option value="">Todos</option>
                <?php foreach ($violenceTypes ?? [] as $type): ?>
                    <option value="<?= (int) $type['id'] ?>" <?= (string) ($filters['violence_type_id'] ?? '') === (string) $type['id'] ? 'selected' : '' ?>>
                        <?= e((string) $type['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="filter_sector">Sector</label>
            <select class="form-select" id="filter_sector" name="sector_id">
                <option value="">Todos</option>
                <?php foreach ($sectors ?? [] as $sector): ?>
                    <option value="<?= (int) $sector['id'] ?>" <?= (string) ($filters['sector_id'] ?? '') === (string) $sector['id'] ? 'selected' : '' ?>>
                        <?= e((string) $sector['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="filter_age_range">Rango etario</label>
            <select class="form-select" id="filter_age_range" name="age_range">
                <option value="">Todos</option>
                <?php foreach ($ageRanges ?? [] as $option): ?>
                    <option value="<?= e((string) $option['value']) ?>" <?= ($filters['age_range'] ?? '') === $option['value'] ? 'selected' : '' ?>>
                        <?= e((string) $option['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="filter_status">Estado</label>
            <select class="form-select" id="filter_status" name="case_status_id">
                <option value="">Todos</option>
                <?php foreach ($caseStatuses ?? [] as $status): ?>
                    <option value="<?= (int) $status['id'] ?>" <?= (string) ($filters['case_status_id'] ?? '') === (string) $status['id'] ? 'selected' : '' ?>>
                        <?= e((string) $status['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="filter_priority">Prioridad</label>
            <select class="form-select" id="filter_priority" name="priority">
                <option value="">Todas</option>
                <?php foreach ($priorities ?? [] as $option): ?>
                    <option value="<?= e((string) $option['value']) ?>" <?= ($filters['priority'] ?? '') === $option['value'] ? 'selected' : '' ?>>
                        <?= e((string) $option['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (hasPermission('women.cases.view_all')): ?>
            <div>
                <label class="form-label" for="filter_created_by">Funcionario</label>
                <select class="form-select" id="filter_created_by" name="created_by">
                    <option value="">Todos</option>
                    <?php foreach ($staff ?? [] as $user): ?>
                        <option value="<?= (int) $user['id'] ?>" <?= (string) ($filters['created_by'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>>
                            <?= e((string) $user['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div>
            <label class="form-label" for="filter_pending_follow_up">Seguimiento pendiente</label>
            <select class="form-select" id="filter_pending_follow_up" name="pending_follow_up">
                <option value="">Todos</option>
                <option value="yes" <?= ($filters['pending_follow_up'] ?? '') === 'yes' ? 'selected' : '' ?>>Con seguimiento pendiente</option>
            </select>
        </div>
        <div>
            <label class="form-label" for="filter_formal_report">Denuncia formal</label>
            <select class="form-select" id="filter_formal_report" name="formal_report">
                <option value="">Todas</option>
                <option value="yes" <?= ($filters['formal_report'] ?? '') === 'yes' ? 'selected' : '' ?>>Con denuncia formal</option>
                <option value="no" <?= ($filters['formal_report'] ?? '') === 'no' ? 'selected' : '' ?>>Sin denuncia formal</option>
                <option value="unknown" <?= ($filters['formal_report'] ?? '') === 'unknown' ? 'selected' : '' ?>>No informado</option>
            </select>
        </div>
        <div>
            <label class="form-label" for="filter_referral_institution">Institución derivación</label>
            <select class="form-select" id="filter_referral_institution" name="referral_institution_id">
                <option value="">Todas</option>
                <?php foreach ($referralInstitutions ?? [] as $institution): ?>
                    <option value="<?= (int) $institution['id'] ?>" <?= (string) ($filters['referral_institution_id'] ?? '') === (string) $institution['id'] ? 'selected' : '' ?>>
                        <?= e((string) $institution['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="women-filters__actions">
        <button class="btn btn-navy" type="submit">Filtrar</button>
        <?php if ($hasFilters): ?>
            <a class="btn btn-outline-navy" href="<?= e(url('/women/cases')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="page-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>N.º caso</th>
                    <th>Fecha</th>
                    <th>Persona</th>
                    <th>Rango etario</th>
                    <th>Sector</th>
                    <th>Violencia</th>
                    <th>Estado</th>
                    <th>Prioridad</th>
                    <th>Próximo seguimiento</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (($cases ?? []) === []): ?>
                    <tr>
                        <td colspan="10" class="text-secondary">No hay casos para mostrar.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($cases as $item): ?>
                        <tr>
                            <td><code><?= e((string) $item['case_number']) ?></code></td>
                            <td><?= e(date('d-m-Y', strtotime((string) $item['reported_at']))) ?></td>
                            <td><?= e((string) ($item['person_display_name'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['age_range_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['person_sector_name'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['violence_types_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($item['case_status_name'] ?? '—')) ?></td>
                            <td>
                                <?php if (!empty($item['priority'])): ?>
                                    <span class="women-priority-badge women-priority-badge--<?= e((string) $item['priority']) ?>">
                                        <?= e((string) ($item['priority_label'] ?? '—')) ?>
                                    </span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($item['next_follow_up_date'])): ?>
                                    <span class="women-followup-date women-followup-date--<?= e((string) ($item['next_follow_up_tone'] ?? 'scheduled')) ?>">
                                        <?= e((string) ($item['next_follow_up_label'] ?? '—')) ?>
                                    </span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-outline-navy btn-sm" href="<?= e(url('/women/cases/' . $item['id'])) ?>">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= component('pagination', [
        'page' => $page ?? 1,
        'pages' => $pages ?? 1,
        'base' => url('/women/cases'),
        'query' => $query,
    ]) ?>
</div>
