<?php

declare(strict_types=1);

namespace App\Controllers\Meetings;

use App\Services\Meetings\MeetingHistoryService;
use App\Services\Meetings\MeetingService;
use App\Services\Meetings\MeetingSignatureService;
use App\Services\Meetings\MeetingSourceContext;
use App\Services\Meetings\MeetingSourceModule;
use App\Services\Meetings\MeetingStatus;
use App\Validators\Meetings\MeetingStoreValidator;
use Core\Auth;
use Core\Request;
use Core\Session;

final class MeetingRecordController extends MeetingController
{
    public function __construct(
        private readonly MeetingService $meetings = new MeetingService(),
        private readonly MeetingSignatureService $signatures = new MeetingSignatureService(),
        private readonly MeetingHistoryService $history = new MeetingHistoryService()
    ) {
    }

    public function index(Request $request): void
    {
        $source = $this->resolveSourceModule($request);
        $filters = $this->listFilters($request, $source);
        $page = max(1, (int) $request->query('page', 1));
        $result = $this->meetings->search($filters, $page);

        $this->renderPage('index', [
            'title' => $this->pageTitle('Reuniones'),
            'meetings' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'filters' => $filters,
            'sourceModule' => $source,
            'statusOptions' => $this->statusOptions(),
            'canCreate' => $this->canCreate($source),
        ]);
    }

    public function create(Request $request): void
    {
        $source = $this->resolveSourceModule($request);
        $this->assertCanCreate($source);

        $this->renderPage('form', [
            'title' => $this->pageTitle('Nueva Reunión'),
            'meeting' => null,
            'sourceModule' => $source,
            'formAction' => $this->formAction($source),
            'cancelUrl' => $this->listUrl($source),
            'userOptions' => [],
        ]);
    }

    public function store(Request $request): void
    {
        $source = $this->resolveSourceModule($request);
        $this->assertCanCreate($source);

        $payload = $request->all();
        $errors = (new MeetingStoreValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect($this->createUrl($source));
        }

        try {
            $id = $this->meetings->createDraft($source, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, $this->createUrl($source));
        }

        Session::flashAlert('success', 'Borrador guardado', 'El registro de reunión quedó guardado como borrador.');
        $this->redirect($this->showUrl($source, $id));
    }

    public function show(Request $request, string $id): void
    {
        try {
            $meeting = $this->meetings->findDetailed((int) $id);
            $this->meetings->assertCanView($meeting);
            $this->meetings->auditView($meeting);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, $this->listUrl($this->resolveSourceModule($request)));
        }

        $source = (string) ($meeting['source_module'] ?? MeetingSourceModule::ADMIN);
        $signatureRows = $this->signatures->presentSignatures($meeting);
        $canFinalize = !empty($meeting['can_edit']) && ($meeting['status'] ?? '') === MeetingStatus::DRAFT;

        $this->renderPage('show', [
            'title' => $this->pageTitle((string) ($meeting['meeting_number'] ?? 'Reunión')),
            'meeting' => $meeting,
            'sourceModule' => $source,
            'canEdit' => !empty($meeting['can_edit']),
            'canCancel' => !empty($meeting['can_cancel']),
            'canReopen' => !empty($meeting['can_reopen']),
            'canDelete' => !empty($meeting['can_delete']),
            'canFinalize' => $canFinalize,
            'canSign' => $this->signatures->canUserSign((int) $id),
            'signatures' => $signatureRows,
            'timeline' => $this->history->timeline($meeting),
            'auditEntries' => hasPermission('audit.view') ? $this->history->auditEntries((int) $id) : [],
            'finalizeUrl' => $this->finalizeUrl($source, (int) $id),
            'cancelUrl' => $this->cancelUrl($source, (int) $id),
            'reopenUrl' => $this->reopenUrl($source, (int) $id),
            'deleteUrl' => $this->deleteUrl($source, (int) $id),
            'signUrl' => $this->signReviewUrl($source, (int) $id),
            'editUrl' => $this->editUrl($source, (int) $id),
            'listUrl' => $this->listUrl($source),
        ]);
    }

    public function edit(Request $request, string $id): void
    {
        try {
            $meeting = $this->meetings->findDetailed((int) $id);
            $this->meetings->assertCanEdit($meeting);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, $this->listUrl($this->resolveSourceModule($request)));
        }

        $source = (string) ($meeting['source_module'] ?? MeetingSourceModule::ADMIN);

        $this->renderPage('form', [
            'title' => $this->pageTitle('Editar Reunión'),
            'meeting' => $meeting,
            'sourceModule' => $source,
            'formAction' => $this->formAction($source, (int) $id),
            'cancelUrl' => $this->showUrl($source, (int) $id),
            'userOptions' => [],
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $meetingId = (int) $id;

        try {
            $meeting = $this->meetings->findDetailed($meetingId);
            $this->meetings->assertCanEdit($meeting);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/meetings'));
        }

        $source = (string) ($meeting['source_module'] ?? MeetingSourceModule::ADMIN);
        $payload = $request->all();
        $errors = (new MeetingStoreValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect($this->editUrl($source, $meetingId));
        }

        try {
            $this->meetings->updateDraft($meetingId, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, $this->editUrl($source, $meetingId));
        }

        Session::flashAlert('success', 'Borrador actualizado', 'Los cambios quedaron guardados.');
        $this->redirect($this->showUrl($source, $meetingId));
    }

    public function searchUsers(Request $request): void
    {
        if (!hasPermission('meetings.create') && !hasPermission('meetings.edit')) {
            http_response_code(403);
            echo json_encode(['data' => []]);
            exit;
        }

        $term = trim((string) $request->query('q', ''));
        $results = $this->meetings->searchActiveUsers($term);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['data' => $results], JSON_THROW_ON_ERROR);
        exit;
    }

    public function finalize(Request $request, string $id): void
    {
        $meetingId = (int) $id;

        try {
            $meeting = $this->meetings->findDetailed($meetingId);
            $source = (string) ($meeting['source_module'] ?? MeetingSourceModule::ADMIN);
            $emailStats = $this->signatures->finalize($meetingId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, $this->showUrl($source ?? MeetingSourceModule::ADMIN, $meetingId));
        }

        $message = 'Se bloqueó la edición y se enviaron las solicitudes de firma a los participantes internos requeridos.';
        if (($emailStats['sent'] ?? 0) > 0) {
            $message .= ' Se enviaron ' . (int) $emailStats['sent'] . ' correo(s) de confirmación de asistencia a participantes externos.';
        } elseif (($emailStats['failed'] ?? 0) > 0) {
            $message .= ' No fue posible enviar algunos correos de confirmación; revise la configuración SMTP.';
        }

        Session::flashAlert(
            'success',
            'Reunión finalizada',
            $message
        );
        $this->redirect($this->showUrl($source, $meetingId));
    }

    public function cancel(Request $request, string $id): void
    {
        $meetingId = (int) $id;
        $reason = trim((string) $request->input('cancellation_reason', ''));

        try {
            $meeting = $this->meetings->findDetailed($meetingId);
            $source = (string) ($meeting['source_module'] ?? MeetingSourceModule::ADMIN);
            $this->meetings->cancel($meetingId, $reason);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, $this->showUrl($source ?? MeetingSourceModule::ADMIN, $meetingId));
        }

        Session::flashAlert('success', 'Reunión anulada', 'El registro quedó anulado y se invalidaron las solicitudes de firma activas.');
        $this->redirect($this->showUrl($source, $meetingId));
    }

    public function reopen(Request $request, string $id): void
    {
        $meetingId = (int) $id;
        $reason = trim((string) $request->input('reopen_reason', ''));

        try {
            $meeting = $this->meetings->findDetailed($meetingId);
            $source = (string) ($meeting['source_module'] ?? MeetingSourceModule::ADMIN);
            $this->meetings->reopen($meetingId, $reason);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, $this->showUrl($source ?? MeetingSourceModule::ADMIN, $meetingId));
        }

        Session::flashAlert('success', 'Reunión reabierta', 'El registro volvió a borrador. Puede corregirlo y finalizarlo nuevamente.');
        $this->redirect($this->editUrl($source, $meetingId));
    }

    public function destroy(Request $request, string $id): void
    {
        $meetingId = (int) $id;

        try {
            $meeting = $this->meetings->findDetailed($meetingId);
            $source = (string) ($meeting['source_module'] ?? MeetingSourceModule::ADMIN);
            $this->meetings->delete($meetingId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, $this->showUrl($source ?? MeetingSourceModule::ADMIN, $meetingId));
        }

        Session::flashAlert('success', 'Reunión eliminada', 'El registro fue eliminado permanentemente del sistema.');
        $this->redirect($this->listUrl($source));
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function renderPage(string $view, array $data = []): void
    {
        $source = MeetingSourceContext::get();
        if ($source === MeetingSourceModule::SENDA) {
            $data['sendaNav'] = $this->sendaNavigation();
        }

        $this->meetingView($view, $data);
    }

    protected function resolveSourceModule(Request $request): string
    {
        $context = MeetingSourceContext::get();
        if ($context !== null) {
            return $context;
        }

        $fromQuery = trim((string) $request->query('source_module', ''));
        if ($fromQuery !== '' && in_array($fromQuery, MeetingSourceModule::all(), true)) {
            return $fromQuery;
        }

        return MeetingSourceModule::ADMIN;
    }

    /**
     * @return array<string, mixed>
     */
    private function listFilters(Request $request, string $source): array
    {
        $filters = array_filter([
            'source_module' => $source !== MeetingSourceModule::ADMIN ? $source : trim((string) $request->query('source_module', '')),
            'status' => trim((string) $request->query('status', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'pending_my_signature' => trim((string) $request->query('pending_my_signature', '')),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if (($filters['pending_my_signature'] ?? '') === 'yes') {
            unset($filters['pending_my_signature']);
            $userId = Auth::id();
            if ($userId !== null) {
                $filters['pending_signature_user_id'] = $userId;
            }
        }

        if (($filters['source_module'] ?? '') === '') {
            unset($filters['source_module']);
        }

        return $filters;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => MeetingStatus::DRAFT, 'label' => MeetingStatus::label(MeetingStatus::DRAFT)],
            ['value' => MeetingStatus::PENDING_SIGNATURES, 'label' => MeetingStatus::label(MeetingStatus::PENDING_SIGNATURES)],
            ['value' => MeetingStatus::PARTIALLY_SIGNED, 'label' => MeetingStatus::label(MeetingStatus::PARTIALLY_SIGNED)],
            ['value' => MeetingStatus::SIGNED, 'label' => MeetingStatus::label(MeetingStatus::SIGNED)],
            ['value' => MeetingStatus::CORRECTION_REQUESTED, 'label' => MeetingStatus::label(MeetingStatus::CORRECTION_REQUESTED)],
            ['value' => MeetingStatus::CANCELLED, 'label' => MeetingStatus::label(MeetingStatus::CANCELLED)],
        ];
    }

    private function canCreate(string $source): bool
    {
        if (!hasPermission('meetings.create')) {
            return false;
        }

        if ($source === MeetingSourceModule::SENDA) {
            return hasPermission('senda.meetings.create');
        }

        return true;
    }

    private function assertCanCreate(string $source): void
    {
        if (!$this->canCreate($source)) {
            Session::flashAlert('warning', 'Acceso denegado', 'No tiene permiso para crear reuniones.');
            $this->redirect($this->listUrl($source));
        }
    }

    private function pageTitle(string $suffix): string
    {
        $source = MeetingSourceContext::get();
        if ($source === MeetingSourceModule::SENDA) {
            return $suffix . ' — SENDA';
        }

        return $suffix . ' — Reuniones';
    }

    private function listUrl(string $source): string
    {
        return $source === MeetingSourceModule::SENDA
            ? url('/senda/meetings')
            : url('/meetings');
    }

    private function createUrl(string $source): string
    {
        return $source === MeetingSourceModule::SENDA
            ? url('/senda/meetings/create')
            : url('/meetings/create');
    }

    private function showUrl(string $source, int $id): string
    {
        return $source === MeetingSourceModule::SENDA
            ? url('/senda/meetings/' . $id)
            : url('/meetings/' . $id);
    }

    private function editUrl(string $source, int $id): string
    {
        return $source === MeetingSourceModule::SENDA
            ? url('/senda/meetings/' . $id . '/edit')
            : url('/meetings/' . $id . '/edit');
    }

    private function formAction(string $source, ?int $id = null): string
    {
        if ($id !== null) {
            return $source === MeetingSourceModule::SENDA
                ? url('/senda/meetings/' . $id)
                : url('/meetings/' . $id);
        }

        return $source === MeetingSourceModule::SENDA
            ? url('/senda/meetings')
            : url('/meetings');
    }

    private function finalizeUrl(string $source, int $id): string
    {
        return $source === MeetingSourceModule::SENDA
            ? url('/senda/meetings/' . $id . '/finalize')
            : url('/meetings/' . $id . '/finalize');
    }

    private function signReviewUrl(string $source, int $id): string
    {
        return $source === MeetingSourceModule::SENDA
            ? url('/senda/meetings/' . $id . '/sign')
            : url('/meetings/' . $id . '/sign');
    }

    private function cancelUrl(string $source, int $id): string
    {
        return $source === MeetingSourceModule::SENDA
            ? url('/senda/meetings/' . $id . '/cancel')
            : url('/meetings/' . $id . '/cancel');
    }

    private function reopenUrl(string $source, int $id): string
    {
        return $source === MeetingSourceModule::SENDA
            ? url('/senda/meetings/' . $id . '/reopen')
            : url('/meetings/' . $id . '/reopen');
    }

    private function deleteUrl(string $source, int $id): string
    {
        return $source === MeetingSourceModule::SENDA
            ? url('/senda/meetings/' . $id . '/delete')
            : url('/meetings/' . $id . '/delete');
    }

    /**
     * @return list<array{label: string, path: string, permission: string, icon: string, exact?: bool}>
     */
    private function sendaNavigation(): array
    {
        $items = [
            ['label' => 'Registro de Atención', 'path' => '/senda/attentions', 'permission' => 'senda.attentions.view', 'icon' => 'bi-clipboard2-pulse'],
            ['label' => 'Ficha de Referencia', 'path' => '/senda/referrals', 'permission' => 'senda.referrals.view', 'icon' => 'bi-file-earmark-medical'],
            ['label' => 'Personas', 'path' => '/senda/people', 'permission' => 'senda.people.view', 'icon' => 'bi-people'],
            ['label' => 'Reuniones', 'path' => '/senda/meetings', 'permission' => 'senda.meetings.view', 'icon' => 'bi-journal-text'],
            ['label' => 'Estadísticas', 'path' => '/senda/statistics', 'permission' => 'senda.statistics.view', 'icon' => 'bi-graph-up'],
        ];

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => hasPermission($item['permission'])
        ));
    }
}
