<?php

$case = $case ?? [];
$person = is_array($person ?? null) ? $person : [];
$metrics = is_array($metrics ?? null) ? $metrics : [];
$tab = in_array($tab ?? '', [
    'resumen', 'persona', 'hechos', 'denunciada', 'antecedentes',
    'acciones', 'derivaciones', 'seguimientos', 'documentos', 'historial',
], true) ? (string) $tab : 'resumen';
$order = ($order ?? 'desc') === 'asc' ? 'asc' : 'desc';
$caseId = (int) ($case['id'] ?? 0);
$baseUrl = url('/women/cases/' . $caseId);
$tabUrl = static function (string $name) use ($baseUrl, $order): string {
    $query = ['tab' => $name];
    if ($name === 'historial' && $order !== 'desc') {
        $query['order'] = $order;
    }

    return $baseUrl . '?' . http_build_query($query);
};
$lastAction = $metrics['last_action'] ?? null;
$nextFollowUp = $metrics['next_follow_up'] ?? null;

?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Oficina de la Mujer</p>
        <h2 class="page-card__title mb-1"><?= e((string) ($case['case_number'] ?? 'Caso')) ?></h2>
        <p class="text-secondary mb-0">
            Estado: <?= e((string) ($case['case_status_name'] ?? '—')) ?>
            · Registrado: <?= e(!empty($case['reported_at']) ? date('d-m-Y H:i', strtotime((string) $case['reported_at'])) : '—') ?>
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (!empty($canEdit) && empty($case['has_facts'])): ?>
            <a class="btn btn-navy" href="<?= e(url('/women/cases/' . $caseId . '/facts')) ?>">Completar hechos</a>
        <?php elseif (!empty($canEdit) && empty($case['has_aggressor'])): ?>
            <a class="btn btn-navy" href="<?= e(url('/women/cases/' . $caseId . '/aggressor')) ?>">Completar persona denunciada</a>
        <?php elseif (!empty($canEdit) && empty($case['has_background'])): ?>
            <a class="btn btn-navy" href="<?= e(url('/women/cases/' . $caseId . '/background')) ?>">Completar antecedentes</a>
        <?php elseif (!empty($canEdit) && empty($case['has_risk_assessment'])): ?>
            <a class="btn btn-navy" href="<?= e(url('/women/cases/' . $caseId . '/risk-priority')) ?>">Completar riesgo y prioridad</a>
        <?php elseif (!empty($canEdit) && empty($case['has_support_context'])): ?>
            <a class="btn btn-navy" href="<?= e(url('/women/cases/' . $caseId . '/support')) ?>">Completar medidas y necesidades</a>
        <?php elseif (!empty($canEdit)): ?>
            <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . $caseId . '/facts')) ?>">Editar hechos</a>
            <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . $caseId . '/aggressor')) ?>">Editar persona denunciada</a>
            <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . $caseId . '/background')) ?>">Editar antecedentes</a>
            <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . $caseId . '/risk-priority')) ?>">Editar riesgo</a>
            <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . $caseId . '/support')) ?>">Editar medidas</a>
            <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . $caseId . '/actions')) ?>">Editar acciones</a>
            <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . $caseId . '/referrals')) ?>">Editar derivaciones</a>
            <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/' . $caseId . '/follow-ups')) ?>">Editar seguimientos</a>
        <?php endif; ?>
        <?php if (!empty($canClose)): ?>
            <button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#womenCaseCloseModal">Finalizar caso</button>
        <?php endif; ?>
        <?php if (!empty($canCancel)): ?>
            <button class="btn btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#womenCaseCancelModal">Anular caso</button>
        <?php endif; ?>
        <a class="btn btn-outline-navy" href="<?= e(url('/women/cases')) ?>">Volver al listado</a>
    </div>
</section>

<?= women_nav($womenNav ?? []) ?>

<?= \Core\View::make('women-office/cases/_show_summary_header', [
    'case' => $case,
    'metrics' => $metrics,
    'lastAction' => $lastAction,
    'nextFollowUp' => $nextFollowUp,
    'tabUrl' => $tabUrl,
], null) ?>

<ul class="nav nav-tabs women-case-tabs mb-3" role="tablist">
    <?php
    $tabs = [
        'resumen' => 'Resumen',
        'persona' => 'Persona afectada',
        'hechos' => 'Hechos',
        'denunciada' => 'Persona denunciada',
        'antecedentes' => 'Antecedentes',
        'acciones' => 'Acciones',
        'derivaciones' => 'Derivaciones',
        'seguimientos' => 'Seguimientos',
        'documentos' => 'Documentos',
        'historial' => 'Historial',
    ];
    foreach ($tabs as $slug => $label):
    ?>
        <li class="nav-item">
            <a class="nav-link <?= $tab === $slug ? 'active' : '' ?>" href="<?= e($tabUrl($slug)) ?>"><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?= \Core\View::make('women-office/cases/_show_tab_' . $tab, [
    'case' => $case,
    'person' => $person,
    'metrics' => $metrics,
    'timeline' => $timeline ?? [],
    'auditHistory' => $auditHistory ?? [],
    'order' => $order,
    'baseUrl' => $baseUrl,
    'canEdit' => !empty($canEdit),
    'canEditPerson' => !empty($canEditPerson),
    'canUploadDocuments' => !empty($canUploadDocuments),
    'documents' => $documents ?? [],
], null) ?>

<?php if (!empty($canClose)): ?>
    <div class="modal fade" id="womenCaseCloseModal" tabindex="-1" aria-labelledby="womenCaseCloseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="<?= e(url('/women/cases/' . $caseId . '/close')) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="womenCaseCloseModalLabel">Finalizar caso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary">El caso quedará cerrado y no podrá editarse salvo que tenga permiso para modificar casos cerrados.</p>
                    <div class="mb-0">
                        <label class="form-label" for="closure_notes">Observaciones de cierre</label>
                        <textarea class="form-control" id="closure_notes" name="closure_notes" rows="4"><?= e((string) old('closure_notes', '')) ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-navy" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Confirmar cierre</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($canCancel)): ?>
    <div class="modal fade" id="womenCaseCancelModal" tabindex="-1" aria-labelledby="womenCaseCancelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="<?= e(url('/women/cases/' . $caseId . '/cancel')) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="womenCaseCancelModalLabel">Anular caso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary">Esta acción deja el caso anulado. Indique el motivo de la anulación.</p>
                    <div class="mb-0">
                        <label class="form-label" for="cancellation_reason">Motivo de anulación</label>
                        <textarea class="form-control <?= has_error('cancellation_reason') ? 'is-invalid' : '' ?>" id="cancellation_reason" name="cancellation_reason" rows="4" required minlength="10"><?= e((string) old('cancellation_reason', '')) ?></textarea>
                        <?php if (has_error('cancellation_reason')): ?><div class="invalid-feedback"><?= e((string) error('cancellation_reason')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-navy" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-danger">Confirmar anulación</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
