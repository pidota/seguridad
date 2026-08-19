<?php

declare(strict_types=1);

namespace App\Controllers\WomenOffice;

use App\Repositories\WomenOffice\CatalogRepository;
use App\Repositories\SectorRepository;
use App\Services\WomenOffice\PersonContext;
use App\Services\WomenOffice\PersonService;
use App\Services\WomenOffice\WomenAuditService;
use App\Services\WomenOffice\WomenCaseDocumentService;
use App\Services\WomenOffice\WomenCaseService;
use App\Services\WomenOffice\WomenHistoryService;
use App\Validators\WomenOffice\CaseCancelValidator;
use App\Validators\WomenOffice\CaseCloseValidator;
use App\Validators\WomenOffice\CaseFollowUpsValidator;
use App\Validators\WomenOffice\CaseReferralsValidator;
use App\Validators\WomenOffice\CaseActionsValidator;
use App\Validators\WomenOffice\CaseSupportValidator;
use App\Validators\WomenOffice\CaseRiskPriorityValidator;
use App\Validators\WomenOffice\CaseBackgroundValidator;
use App\Validators\WomenOffice\CaseAggressorValidator;
use App\Validators\WomenOffice\CaseFactsValidator;
use App\Validators\WomenOffice\CaseRegistrationValidator;
use Core\Auth;
use Core\Request;
use Core\Session;

final class CaseController extends WomenOfficeController
{
    public function __construct(
        private readonly WomenCaseService $cases = new WomenCaseService(),
        private readonly PersonService $people = new PersonService(),
        private readonly CatalogRepository $catalogs = new CatalogRepository(),
        private readonly WomenHistoryService $history = new WomenHistoryService(),
        private readonly WomenAuditService $womenAudit = new WomenAuditService(),
        private readonly WomenCaseDocumentService $documents = new WomenCaseDocumentService()
    ) {
    }

    public function index(Request $request): void
    {
        $filters = $this->listFilters($request);
        $page = max(1, (int) $request->query('page', 1));
        $result = $this->cases->search($filters, $page);

        $this->womenView('cases/index', [
            'title' => 'Casos registrados — Oficina de la Mujer',
            'cases' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'filters' => $filters,
            'violenceTypes' => $this->catalogs->violenceTypes(),
            'caseStatuses' => $this->catalogs->caseStatuses(),
            'referralInstitutions' => $this->catalogs->referralInstitutions(),
            'sectors' => (new SectorRepository())->options(),
            'staff' => $this->cases->staffOptions(),
            'ageRanges' => WomenCaseService::ageRangeOptions(),
            'priorities' => WomenCaseService::priorityOptions(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listFilters(Request $request): array
    {
        return array_filter([
            'case_number' => trim((string) $request->query('case_number', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'violence_type_id' => (int) $request->query('violence_type_id', 0) ?: null,
            'sector_id' => (int) $request->query('sector_id', 0) ?: null,
            'age_range' => trim((string) $request->query('age_range', '')),
            'case_status_id' => (int) $request->query('case_status_id', 0) ?: null,
            'priority' => trim((string) $request->query('priority', '')),
            'created_by' => (int) $request->query('created_by', 0) ?: null,
            'pending_follow_up' => trim((string) $request->query('pending_follow_up', '')),
            'formal_report' => trim((string) $request->query('formal_report', '')),
            'referral_institution_id' => (int) $request->query('referral_institution_id', 0) ?: null,
            'referral_pending' => trim((string) $request->query('referral_pending', '')),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function create(Request $request): void
    {
        $this->redirect(url('/women/cases/create/person'));
    }

    public function register(Request $request): void
    {
        $personId = PersonContext::id();

        if ($personId === null) {
            Session::flashAlert('info', 'Persona requerida', 'Busque o registre primero a la persona afectada.');
            $this->redirect(url('/women/cases/create/person'));
        }

        try {
            $person = $this->people->find($personId);
        } catch (\Throwable $e) {
            PersonContext::forget();
            $this->failAndRedirect($e, url('/women/cases/create/person'));
        }

        $user = Auth::user();

        $this->womenView('cases/register', [
            'title' => 'Nueva denuncia — Datos del registro',
            'person' => $person,
            'reportChannels' => $this->catalogs->reportChannels(),
            'defaults' => [
                'reported_date' => date('Y-m-d'),
                'reported_time' => date('H:i'),
                'receiving_officer' => trim((string) ($user['name'] ?? '')),
            ],
        ]);
    }

    public function store(Request $request): void
    {
        $personId = PersonContext::id();

        if ($personId === null) {
            Session::flashAlert('info', 'Persona requerida', 'Busque o registre primero a la persona afectada.');
            $this->redirect(url('/women/cases/create/person'));
        }

        $payload = $request->all();
        $payload['affected_person_id'] = $personId;

        $errors = (new CaseRegistrationValidator())->validate($payload, $personId);
        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/women/cases/create/register'));
        }

        try {
            $id = $this->cases->createRegistration($personId, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/women/cases/create/register'));
        }

        Session::flashAlert('success', 'Caso registrado', 'Continúe con los antecedentes del hecho.');
        $this->redirect(url('/women/cases/' . $id . '/facts'));
    }

    public function facts(Request $request, string $id): void
    {
        $case = $this->caseForView($id, 'facts');

        $this->womenView('cases/facts', [
            'title' => 'Hechos del caso — ' . ($case['case_number'] ?? ''),
            'case' => $case,
            'currentStep' => 2,
            'violenceTypes' => $this->catalogs->violenceTypes(),
            'sectors' => (new \App\Repositories\SectorRepository())->options(),
            'canEdit' => $this->casesCanEdit($case),
        ]);
    }

    public function updateFacts(Request $request, string $id): void
    {
        $caseId = (int) $id;

        try {
            $case = $this->cases->findDetailed($caseId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases'));
        }

        try {
            $this->cases->assertCanEdit($case);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases/' . $caseId));
        }

        $payload = $request->all();
        $errors = (new CaseFactsValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/women/cases/' . $caseId . '/facts'));
        }

        try {
            $this->cases->updateFacts($caseId, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/women/cases/' . $caseId . '/facts'));
        }

        Session::flashAlert('success', 'Hechos registrados', 'Continúe con los antecedentes de la persona denunciada.');
        $this->redirect(url('/women/cases/' . $caseId . '/aggressor'));
    }

    public function aggressor(Request $request, string $id): void
    {
        $case = $this->caseForView($id, 'aggressor');

        $aggressor = $case['aggressor'] ?? [];

        $this->womenView('cases/aggressor', [
            'title' => 'Persona denunciada — ' . ($case['case_number'] ?? ''),
            'case' => $case,
            'aggressor' => is_array($aggressor) ? $aggressor : [],
            'currentStep' => 3,
            'relationshipTypes' => $this->catalogs->relationshipTypes(),
            'canEdit' => $this->casesCanEdit($case),
        ]);
    }

    public function updateAggressor(Request $request, string $id): void
    {
        $caseId = (int) $id;

        try {
            $case = $this->cases->findDetailed($caseId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases'));
        }

        try {
            $this->cases->assertCanEdit($case);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases/' . $caseId));
        }

        $payload = $request->all();
        $errors = (new CaseAggressorValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/women/cases/' . $caseId . '/aggressor'));
        }

        try {
            $this->cases->updateAggressor($caseId, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/women/cases/' . $caseId . '/aggressor'));
        }

        Session::flashAlert('success', 'Persona denunciada registrada', 'Continúe con los antecedentes del caso.');
        $this->redirect(url('/women/cases/' . $caseId . '/background'));
    }

    public function background(Request $request, string $id): void
    {
        $case = $this->caseForView($id, 'background');

        $previousReports = old('previous_reports');
        if (!is_array($previousReports)) {
            $previousReports = $case['previous_reports'] ?? [];
        }

        $this->womenView('cases/background', [
            'title' => 'Antecedentes — ' . ($case['case_number'] ?? ''),
            'case' => $case,
            'previousReports' => $previousReports,
            'formalReport' => $case['formal_report'] ?? [],
            'currentStep' => 4,
            'formalInstitutions' => $this->catalogs->formalReportInstitutions(),
            'canEdit' => $this->casesCanEdit($case),
        ]);
    }

    public function updateBackground(Request $request, string $id): void
    {
        $caseId = (int) $id;

        try {
            $case = $this->cases->findDetailed($caseId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases'));
        }

        try {
            $this->cases->assertCanEdit($case);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases/' . $caseId));
        }

        $payload = $request->all();
        $errors = (new CaseBackgroundValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/women/cases/' . $caseId . '/background'));
        }

        try {
            $this->cases->updateBackground($caseId, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/women/cases/' . $caseId . '/background'));
        }

        Session::flashAlert('success', 'Antecedentes registrados', 'Continúe con factores de riesgo y prioridad.');
        $this->redirect(url('/women/cases/' . $caseId . '/risk-priority'));
    }

    public function riskPriority(Request $request, string $id): void
    {
        $case = $this->caseForView($id, 'risk_priority');

        $this->womenView('cases/risk-priority', [
            'title' => 'Riesgo y prioridad — ' . ($case['case_number'] ?? ''),
            'case' => $case,
            'currentStep' => 5,
            'riskFactors' => $this->catalogs->riskFactors(),
            'canEdit' => $this->casesCanEdit($case),
        ]);
    }

    public function updateRiskPriority(Request $request, string $id): void
    {
        $caseId = (int) $id;

        try {
            $case = $this->cases->findDetailed($caseId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases'));
        }

        try {
            $this->cases->assertCanEdit($case);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases/' . $caseId));
        }

        $payload = $request->all();
        $errors = (new CaseRiskPriorityValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/women/cases/' . $caseId . '/risk-priority'));
        }

        try {
            $this->cases->updateRiskPriority($caseId, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/women/cases/' . $caseId . '/risk-priority'));
        }

        Session::flashAlert('success', 'Evaluación registrada', 'Continúe con medidas, necesidades y contexto familiar.');
        $this->redirect(url('/women/cases/' . $caseId . '/support'));
    }

    public function support(Request $request, string $id): void
    {
        $case = $this->caseForView($id, 'support');

        $protectiveMeasures = old('protective_measures');
        if (!is_array($protectiveMeasures)) {
            $protectiveMeasures = $case['protective_measures'] ?? [];
        }

        $linkedMinors = old('linked_minors');
        if (!is_array($linkedMinors)) {
            $linkedMinors = $case['linked_minors'] ?? [];
        }

        $this->womenView('cases/support', [
            'title' => 'Medidas y necesidades — ' . ($case['case_number'] ?? ''),
            'case' => $case,
            'currentStep' => 6,
            'protectiveMeasures' => $protectiveMeasures,
            'linkedMinors' => $linkedMinors,
            'measureTypes' => $this->catalogs->protectiveMeasureTypes(),
            'needs' => $this->catalogs->needs(),
            'ageRanges' => $this->catalogs->minorAgeRanges(),
            'canEdit' => $this->casesCanEdit($case),
        ]);
    }

    public function updateSupport(Request $request, string $id): void
    {
        $caseId = (int) $id;

        try {
            $case = $this->cases->findDetailed($caseId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases'));
        }

        try {
            $this->cases->assertCanEdit($case);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases/' . $caseId));
        }

        $payload = $request->all();
        $errors = (new CaseSupportValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/women/cases/' . $caseId . '/support'));
        }

        try {
            $this->cases->updateSupport($caseId, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/women/cases/' . $caseId . '/support'));
        }

        Session::flashAlert('success', 'Información registrada', 'Continúe con las acciones realizadas.');
        $this->redirect(url('/women/cases/' . $caseId . '/actions'));
    }

    public function actions(Request $request, string $id): void
    {
        $case = $this->caseForView($id, 'actions');

        $actionRows = old('actions');
        if (!is_array($actionRows)) {
            $actionRows = $case['actions'] ?? [];
        }

        if ($actionRows === []) {
            $actionRows = [[
                'action_date' => date('Y-m-d'),
                'action_time' => date('H:i'),
                'action_type_id' => '',
                'description' => '',
                'institution' => '',
            ]];
        }

        $this->womenView('cases/actions', [
            'title' => 'Acciones realizadas — ' . ($case['case_number'] ?? ''),
            'case' => $case,
            'currentStep' => 7,
            'actionRows' => $actionRows,
            'actionTypes' => $this->catalogs->actionTypes(),
            'canEdit' => $this->casesCanEdit($case),
        ]);
    }

    public function updateActions(Request $request, string $id): void
    {
        $caseId = (int) $id;

        try {
            $case = $this->cases->findDetailed($caseId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases'));
        }

        try {
            $this->cases->assertCanEdit($case);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases/' . $caseId));
        }

        $payload = $request->all();
        $errors = (new CaseActionsValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/women/cases/' . $caseId . '/actions'));
        }

        try {
            $this->cases->updateActions($caseId, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/women/cases/' . $caseId . '/actions'));
        }

        Session::flashAlert('success', 'Acciones registradas', 'Continúe con las derivaciones institucionales.');
        $this->redirect(url('/women/cases/' . $caseId . '/referrals'));
    }

    public function referrals(Request $request, string $id): void
    {
        $case = $this->caseForView($id, 'referrals');

        $referralRows = old('referrals');
        if (!is_array($referralRows)) {
            $referralRows = $case['referrals'] ?? [];
        }

        $pendingStatusId = null;
        foreach ($this->catalogs->referralStatuses() as $status) {
            if (($status['slug'] ?? '') === 'pending') {
                $pendingStatusId = (int) $status['id'];
                break;
            }
        }

        if ($referralRows === []) {
            $referralRows = [[
                'referral_date' => date('Y-m-d'),
                'institution_id' => '',
                'program_area' => '',
                'reason' => '',
                'contact_person' => '',
                'referral_status_id' => $pendingStatusId ?? '',
                'notes' => '',
            ]];
        }

        $this->womenView('cases/referrals', [
            'title' => 'Derivaciones — ' . ($case['case_number'] ?? ''),
            'case' => $case,
            'currentStep' => 8,
            'referralRows' => $referralRows,
            'referralInstitutions' => $this->catalogs->referralInstitutions(),
            'referralStatuses' => $this->catalogs->referralStatuses(),
            'canEdit' => $this->casesCanEdit($case),
        ]);
    }

    public function updateReferrals(Request $request, string $id): void
    {
        $caseId = (int) $id;

        try {
            $case = $this->cases->findDetailed($caseId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases'));
        }

        try {
            $this->cases->assertCanEdit($case);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases/' . $caseId));
        }

        $payload = $request->all();
        $errors = (new CaseReferralsValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/women/cases/' . $caseId . '/referrals'));
        }

        try {
            $this->cases->updateReferrals($caseId, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/women/cases/' . $caseId . '/referrals'));
        }

        Session::flashAlert('success', 'Derivaciones registradas', 'Continúe con los seguimientos del caso.');
        $this->redirect(url('/women/cases/' . $caseId . '/follow-ups'));
    }

    public function followUps(Request $request, string $id): void
    {
        $case = $this->caseForView($id, 'follow_ups');

        $followUpRows = old('followups');
        if (!is_array($followUpRows)) {
            $followUpRows = $case['followups'] ?? [];
        }

        if ($followUpRows === []) {
            $followUpRows = [[
                'follow_up_date' => date('Y-m-d'),
                'follow_up_time' => date('H:i'),
                'contact_type_id' => '',
                'contact_type_other' => '',
                'result_id' => '',
                'result_other' => '',
                'notes' => '',
                'requires_follow_up' => 'no',
                'next_follow_up_date' => '',
            ]];
        }

        $this->womenView('cases/follow-ups', [
            'title' => 'Seguimientos — ' . ($case['case_number'] ?? ''),
            'case' => $case,
            'currentStep' => 9,
            'followUpRows' => $followUpRows,
            'contactTypes' => $this->catalogs->followUpContactTypes(),
            'followUpResults' => $this->catalogs->followUpResults(),
            'canEdit' => $this->casesCanEdit($case),
        ]);
    }

    public function updateFollowUps(Request $request, string $id): void
    {
        $caseId = (int) $id;

        try {
            $case = $this->cases->findDetailed($caseId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases'));
        }

        try {
            $this->cases->assertCanEdit($case);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases/' . $caseId));
        }

        $payload = $request->all();
        $errors = (new CaseFollowUpsValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/women/cases/' . $caseId . '/follow-ups'));
        }

        try {
            $this->cases->updateFollowUps($caseId, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/women/cases/' . $caseId . '/follow-ups'));
        }

        Session::flashAlert('success', 'Seguimientos registrados', 'Los seguimientos quedaron guardados en el caso.');
        $this->redirect(url('/women/cases/' . $caseId));
    }

    public function show(Request $request, string $id): void
    {
        $case = $this->caseForView($id, 'detail');

        $validTabs = [
            'resumen', 'persona', 'hechos', 'denunciada', 'antecedentes',
            'acciones', 'derivaciones', 'seguimientos', 'documentos', 'historial',
        ];
        $tab = (string) $request->query('tab', 'resumen');
        if (!in_array($tab, $validTabs, true)) {
            $tab = 'resumen';
        }

        $order = $request->query('order') === 'asc' ? 'asc' : 'desc';
        $metrics = $this->history->metrics($case);
        $timeline = $this->history->timeline($case, $order);
        $auditHistory = hasPermission('women.audit.view')
            ? $this->history->auditEntries((int) $id, $order)
            : [];

        try {
            $person = $this->people->find((int) ($case['affected_person_id'] ?? 0));
        } catch (\Throwable) {
            $person = [];
        }

        $personId = (int) ($case['affected_person_id'] ?? 0);
        $documents = [];
        try {
            $documents = $this->documents->forCase((int) $id);
        } catch (\Throwable) {
            $documents = [];
        }

        $this->womenView('cases/show', [
            'title' => 'Caso ' . ($case['case_number'] ?? '') . ' — Oficina de la Mujer',
            'case' => $case,
            'person' => $person,
            'canEdit' => $this->casesCanEdit($case),
            'canClose' => $this->casesCanClose($case),
            'canCancel' => $this->casesCanCancel($case),
            'canEditPerson' => $this->peopleCanEdit($personId),
            'canUploadDocuments' => $this->casesCanEdit($case) && hasPermission('women.documents.upload'),
            'documents' => $documents,
            'tab' => $tab,
            'order' => $order,
            'metrics' => $metrics,
            'timeline' => $timeline,
            'auditHistory' => $auditHistory,
        ]);
    }

    public function close(Request $request, string $id): void
    {
        $caseId = (int) $id;
        $payload = $request->all();
        $errors = (new CaseCloseValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/women/cases/' . $caseId));
        }

        try {
            $this->cases->closeCase($caseId, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/women/cases/' . $caseId));
        }

        Session::flashAlert('success', 'Caso finalizado', 'El caso quedó cerrado correctamente.');
        $this->redirect(url('/women/cases/' . $caseId));
    }

    public function cancel(Request $request, string $id): void
    {
        $caseId = (int) $id;
        $payload = $request->all();
        $errors = (new CaseCancelValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/women/cases/' . $caseId));
        }

        try {
            $this->cases->cancelCase($caseId, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/women/cases/' . $caseId));
        }

        Session::flashAlert('success', 'Caso anulado', 'El caso quedó anulado correctamente.');
        $this->redirect(url('/women/cases/' . $caseId));
    }

    public function uploadDocument(Request $request, string $id): void
    {
        $caseId = (int) $id;

        try {
            $this->documents->upload($caseId, $_FILES['document'] ?? []);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases/' . $caseId . '?tab=documentos'));
        }

        Session::flashAlert('success', 'Documento adjuntado', 'El archivo quedó registrado en el caso.');
        $this->redirect(url('/women/cases/' . $caseId . '?tab=documentos'));
    }

    public function downloadDocument(Request $request, string $id, string $documentId): void
    {
        $caseId = (int) $id;

        try {
            $result = $this->documents->download($caseId, (int) $documentId);
            $document = $result['document'];
            $absolute = $result['absolute_path'];
            $mime = (string) ($document['mime_type'] ?? 'application/octet-stream');
            $name = (string) ($document['original_filename'] ?? 'documento');

            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
            header('Content-Length: ' . (string) filesize($absolute));
            readfile($absolute);
            exit;
        } catch (\Throwable $e) {
            Session::flashAlert('error', 'Documento no disponible', 'No fue posible descargar el archivo.');
            $this->redirect(url('/women/cases/' . $caseId . '?tab=documentos'));
        }
    }

    public function deleteDocument(Request $request, string $id, string $documentId): void
    {
        $caseId = (int) $id;

        try {
            $this->documents->delete($caseId, (int) $documentId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases/' . $caseId . '?tab=documentos'));
        }

        Session::flashAlert('success', 'Documento eliminado', 'El archivo adjunto fue retirado del caso.');
        $this->redirect(url('/women/cases/' . $caseId . '?tab=documentos'));
    }

    /**
     * @return array<string, mixed>
     */
    private function caseForView(string $id, string $section): array
    {
        try {
            $case = $this->cases->findDetailed((int) $id);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases'));
        }

        $this->cases->assertCanView($case);
        $this->womenAudit->viewedCase(
            (int) $id,
            (string) ($case['case_number'] ?? ''),
            $section
        );

        return $case;
    }

    /**
     * @param array<string, mixed> $case
     */
    private function casesCanEdit(array $case): bool
    {
        try {
            $this->cases->assertCanEdit($case);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $case
     */
    private function casesCanClose(array $case): bool
    {
        try {
            $this->cases->assertCanClose($case);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $case
     */
    private function casesCanCancel(array $case): bool
    {
        try {
            $this->cases->assertCanCancel($case);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function peopleCanEdit(int $personId): bool
    {
        if ($personId < 1) {
            return false;
        }

        try {
            $this->people->assertCanEdit($personId);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
