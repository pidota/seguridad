<?php

declare(strict_types=1);

namespace App\Controllers\Cctv;

use App\Controllers\Camera\CameraController as CctvLayoutController;
use App\Repositories\Cctv\RecordingRequestRepository;
use App\Services\Cctv\CameraService;
use App\Services\Cctv\ComplaintDocumentService;
use App\Services\Cctv\ComplaintInstitutionCatalog;
use App\Services\Cctv\DeliveryMediumCatalog;
use App\Services\Cctv\NotFoundReasonCatalog;
use App\Services\Cctv\OfficeVisitService;
use App\Services\Cctv\ReceiverRelationshipCatalog;
use App\Services\Cctv\RecordingRequestService;
use App\Services\Cctv\RecordingRequestStatusCatalog;
use App\Services\Cctv\RejectionReasonCatalog;
use App\Services\Cctv\ShiftService;
use App\Services\Cctv\VisitorTypeCatalog;
use App\Services\Cctv\VisitDashboardService;
use App\Services\Cctv\VisitReasonCatalog;
use App\Validators\Cctv\OfficeVisitStoreValidator;
use App\Validators\Cctv\RecordingComplaintValidator;
use App\Validators\Cctv\RecordingDeliveryValidator;
use Core\Auth;
use Core\Request;
use Core\Session;

final class OfficeVisitController extends CctvLayoutController
{
    public function __construct(
        private readonly OfficeVisitService $visits = new OfficeVisitService(),
        private readonly RecordingRequestService $recordings = new RecordingRequestService(),
        private readonly RecordingRequestRepository $recordingRepo = new RecordingRequestRepository(),
        private readonly ShiftService $shifts = new ShiftService(),
        private readonly CameraService $cameras = new CameraService(),
        private readonly VisitDashboardService $dashboard = new VisitDashboardService(),
        private readonly ComplaintDocumentService $documents = new ComplaintDocumentService()
    ) {
    }

    public function index(Request $request): void
    {
        $tab = (string) $request->query('tab', 'visits');
        $page = max(1, (int) $request->query('page', 1));
        $filters = [
            'tab' => $tab === 'recordings' ? 'recordings' : 'visits',
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'visitor_type' => trim((string) $request->query('visitor_type', '')),
            'requester_rut' => trim((string) $request->query('requester_rut', '')),
            'requester_name' => trim((string) $request->query('requester_name', '')),
            'created_by' => trim((string) $request->query('created_by', '')),
            'status' => trim((string) $request->query('status', '')),
            'sector_id' => trim((string) $request->query('sector_id', '')),
            'q' => trim((string) $request->query('q', '')),
            'complaint_number' => trim((string) $request->query('complaint_number', '')),
            'request_number' => trim((string) $request->query('request_number', '')),
        ];

        $listing = $tab === 'recordings'
            ? $this->recordingRepo->paginate($filters, $page)
            : $this->visits->paginate($filters, $page);

        $pages = max(1, (int) ceil($listing['total'] / 20));
        $statusCatalog = new RecordingRequestStatusCatalog();
        $items = $listing['data'];

        if ($tab === 'recordings') {
            $items = array_map(function (array $row) use ($statusCatalog): array {
                $row['status_label'] = $statusCatalog->label((string) ($row['status'] ?? ''));
                $row['status_tone'] = $statusCatalog->tone((string) ($row['status'] ?? ''));

                return $row;
            }, $items);
        }

        $this->cameraView('visits/index', [
            'title' => 'Visitas y Solicitudes',
            'tab' => $tab,
            'filters' => $filters,
            'items' => $items,
            'total' => $listing['total'],
            'page' => min($page, $pages),
            'pages' => $pages,
            'visitorTypes' => VisitorTypeCatalog::options(),
            'statusOptions' => $statusCatalog->options(),
            'sectors' => $this->cameras->sectorOptions(),
            'moduleScripts' => $this->cctvScripts('visits.js'),
        ]);
    }

    public function create(): void
    {
        $openShift = $this->requireOpenShiftOrRedirect();
        $defaults = [
            'visitor_type' => VisitorTypeCatalog::GENERAL,
            'visit_date' => date('Y-m-d'),
            'arrival_time' => date('H:i'),
            'incident_date' => date('Y-m-d'),
            'time_from' => date('H:i'),
            'time_to' => date('H:i'),
            'has_complaint' => '0',
        ];

        $this->cameraView('visits/create', [
            'title' => 'Registrar Visita / Solicitud',
            'record' => array_merge($defaults, (array) old()),
            'openShift' => $openShift,
            'visitorTypes' => VisitorTypeCatalog::options(),
            'complaintInstitutions' => ComplaintInstitutionCatalog::options(),
            'visitReasons' => VisitReasonCatalog::options(),
            'sectors' => $this->cameras->sectorOptions(),
            'cameras' => $this->cameras->activeOptions(),
            'moduleScripts' => $this->cctvScripts('visits.js', 'unsaved.js'),
        ]);
    }

    public function store(Request $request): void
    {
        $openShift = $this->requireOpenShiftOrRedirect();
        $payload = $request->all();
        if (!isset($_FILES['complaint_document'])) {
            $payload['complaint_document'] = null;
        } else {
            $payload['complaint_document'] = $_FILES['complaint_document'];
        }

        $errors = (new OfficeVisitStoreValidator())->validate($payload);
        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/cctv/visits/create'));
        }

        try {
            $shiftId = (int) ($openShift['id'] ?? 0);
            $operatorId = Auth::id() ?? 0;

            if (($payload['visitor_type'] ?? '') === VisitorTypeCatalog::RECORDING) {
                $result = $this->recordings->createWithVisit($payload, $shiftId, $operatorId);
                $message = match ($result['status'] ?? '') {
                    RecordingRequestStatusCatalog::PENDING_COMPLAINT => 'La solicitud quedó pendiente de denuncia. No se encuentra habilitada para entrega.',
                    RecordingRequestStatusCatalog::INCOMPLETE_DOCUMENTATION => 'La solicitud quedó con denuncia informada. Debe verificarse antes de revisión.',
                    default => 'La solicitud fue registrada y quedó pendiente de revisión.',
                };
                Session::flashAlert(
                    'success',
                    'Solicitud registrada',
                    $result['request_number'] . '. ' . $message
                );
                $this->redirect(url('/cctv/recording-requests/' . $result['request_id']));
            }

            $visitId = $this->visits->createGeneralVisit($payload, $shiftId, $operatorId);
            Session::flashAlert('success', 'Visita registrada', 'La visita quedó registrada en la bitácora del turno.');
            $this->redirect(url('/cctv/visits/' . $visitId));
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndBack($e);
        }
    }

    public function show(Request $request, string $id): void
    {
        try {
            $record = $this->visits->detail((int) $id);
        } catch (\Throwable $e) {
            Session::flashAlert('error', 'No encontrado', 'La visita no existe.');
            $this->redirect(url('/cctv/visits'));
        }

        $recording = $this->recordingRepo->findByVisitId((int) $id);

        $this->cameraView('visits/show', [
            'title' => 'Detalle de visita',
            'record' => $record,
            'recording' => $recording,
        ]);
    }

    public function searchByRut(Request $request): void
    {
        $rut = trim((string) $request->query('rut', ''));
        $results = $rut !== '' ? $this->recordingRepo->findByRut($rut, 20) : [];

        $this->cameraView('visits/search-rut', [
            'title' => 'Búsqueda por RUT',
            'rut' => $rut,
            'results' => $results,
            'statusCatalog' => new RecordingRequestStatusCatalog(),
        ]);
    }

    public function showRecording(Request $request, string $id): void
    {
        try {
            $record = $this->recordings->detail((int) $id);
        } catch (\Throwable $e) {
            Session::flashAlert('error', 'No encontrado', 'La solicitud no existe.');
            $this->redirect(url('/cctv/visits?tab=recordings'));
        }

        $this->cameraView('recording-requests/show', [
            'title' => 'Detalle de Solicitud',
            'record' => $record,
            'statusOptions' => (new RecordingRequestStatusCatalog())->options(),
            'deliveryMedia' => DeliveryMediumCatalog::options(),
            'receiverRelationships' => ReceiverRelationshipCatalog::options(),
            'rejectionReasons' => RejectionReasonCatalog::options(),
            'notFoundReasons' => NotFoundReasonCatalog::options(),
            'complaintInstitutions' => ComplaintInstitutionCatalog::options(),
            'moduleScripts' => $this->cctvScripts('visits.js'),
        ]);
    }

    public function printRecording(Request $request, string $id): void
    {
        try {
            $record = $this->recordings->detail((int) $id);
        } catch (\Throwable) {
            Session::flashAlert('error', 'No encontrado', 'La solicitud no existe.');
            $this->redirect(url('/cctv/visits?tab=recordings'));
        }

        $this->view('camera/recording-requests/print', [
            'title' => 'Ficha administrativa',
            'record' => $record,
        ], 'print');
    }

    public function receiptRecording(Request $request, string $id): void
    {
        try {
            $receipt = $this->recordings->buildDeliveryReceipt((int) $id);
        } catch (\Throwable $e) {
            Session::flashAlert('error', 'Constancia no disponible', $e instanceof \Core\Exceptions\HttpException ? $e->getMessage() : 'No fue posible generar la constancia.');
            $this->redirect(url('/cctv/recording-requests/' . $id));
        }

        $this->view('camera/recording-requests/receipt', [
            'title' => 'Constancia de entrega',
            'receipt' => $receipt,
        ], 'print');
    }

    public function verifyComplaint(Request $request, string $id): void
    {
        try {
            $this->recordings->verifyComplaint((int) $id, Auth::id() ?? 0, trim((string) $request->input('notes', '')) ?: null);
            Session::flashAlert('success', 'Denuncia verificada', 'La solicitud avanzó a pendiente de revisión.');
        } catch (\Throwable $e) {
            $this->failAndBack($e);
        }

        $this->redirect(url('/cctv/recording-requests/' . $id));
    }

    public function preserveRecording(Request $request, string $id): void
    {
        try {
            $this->recordings->preserveRecording((int) $id, Auth::id() ?? 0, trim((string) $request->input('notes', '')) ?: null);
            Session::flashAlert('success', 'Grabación preservada', 'Se registró la reserva administrativa del material.');
        } catch (\Throwable $e) {
            $this->failAndBack($e);
        }

        $this->redirect(url('/cctv/recording-requests/' . $id));
    }

    public function assignRecording(Request $request, string $id): void
    {
        $assignee = trim((string) $request->input('assigned_to', ''));

        try {
            $this->recordings->assignTo(
                (int) $id,
                $assignee !== '' ? (int) $assignee : null,
                Auth::id() ?? 0,
                trim((string) $request->input('notes', '')) ?: null
            );
            Session::flashAlert('success', 'Responsable actualizado', 'La asignación quedó registrada.');
        } catch (\Throwable $e) {
            $this->failAndBack($e);
        }

        $this->redirect(url('/cctv/recording-requests/' . $id));
    }

    public function cancelRecording(Request $request, string $id): void
    {
        $reason = trim((string) $request->input('cancellation_reason', ''));

        try {
            $this->recordings->cancel((int) $id, $reason, Auth::id() ?? 0);
            Session::flashAlert('success', 'Solicitud anulada', 'La solicitud fue marcada como anulada.');
        } catch (\Throwable $e) {
            $this->failAndBack($e);
        }

        $this->redirect(url('/cctv/recording-requests/' . $id));
    }

    public function registerDeparture(Request $request, string $id): void
    {
        try {
            $this->visits->registerDeparture((int) $id, trim((string) $request->input('departure_time', '')) ?: null);
            Session::flashAlert('success', 'Salida registrada', 'Se registró la hora de salida de la visita.');
        } catch (\Throwable $e) {
            $this->failAndBack($e);
        }

        $this->redirect(url('/cctv/visits/' . $id));
    }

    public function registerComplaint(Request $request, string $id): void
    {
        $payload = $request->all();
        if (isset($_FILES['complaint_document'])) {
            $payload['complaint_document'] = $_FILES['complaint_document'];
        }

        $errors = (new RecordingComplaintValidator())->validate($payload);
        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Complete los antecedentes de la denuncia.');
            $this->redirect(url('/cctv/recording-requests/' . $id));
        }

        try {
            $this->recordings->registerComplaint((int) $id, $payload, Auth::id() ?? 0);
            Session::flashAlert('success', 'Denuncia registrada', 'La denuncia quedó informada y pendiente de verificación.');
        } catch (\Throwable $e) {
            $this->failAndBack($e);
        }

        $this->redirect(url('/cctv/recording-requests/' . $id));
    }

    public function updateStatus(Request $request, string $id): void
    {
        $status = trim((string) $request->input('status', ''));

        try {
            $this->recordings->transitionStatus((int) $id, $status, Auth::id() ?? 0, $request->all());
            Session::flashAlert('success', 'Estado actualizado', 'El cambio quedó registrado en el historial.');
        } catch (\Throwable $e) {
            $this->failAndBack($e);
        }

        $this->redirect(url('/cctv/recording-requests/' . $id));
    }

    public function deliver(Request $request, string $id): void
    {
        $payload = $request->all();
        $errors = (new RecordingDeliveryValidator())->validate($payload);
        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Complete los datos de entrega.');
            $this->redirect(url('/cctv/recording-requests/' . $id));
        }

        try {
            $this->recordings->deliver((int) $id, $payload, Auth::id() ?? 0);
            Session::flashAlert('success', 'Entrega registrada', 'La grabación fue marcada como entregada.');
        } catch (\Throwable $e) {
            if ($e instanceof \Core\Exceptions\HttpException && $e->getStatusCode() === 422) {
                Session::flashAlert('error', 'No es posible entregar la grabación', $e->getMessage());
                $this->redirect(url('/cctv/recording-requests/' . $id));
            }
            $this->failAndBack($e);
        }

        $this->redirect(url('/cctv/recording-requests/' . $id));
    }

    public function complaintDocument(Request $request, string $id): void
    {
        if (!hasPermission('cctv.recordings.view_complaint_document')) {
            Session::flashAlert('warning', 'Acceso denegado', 'No puede descargar documentos de denuncia.');
            $this->redirect(url('/cctv/recording-requests/' . $id));
        }

        try {
            $record = $this->recordingRepo->findById((int) $id);
            if ($record === null || empty($record['complaint_document_path'])) {
                throw new \RuntimeException('Documento no encontrado.');
            }

            $absolute = $this->documents->resolveAbsolutePath((string) $record['complaint_document_path']);
            $mime = (string) ($record['complaint_document_mime'] ?? 'application/octet-stream');
            $name = (string) ($record['complaint_document_original_name'] ?? 'documento-denuncia');

            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
            header('Content-Length: ' . (string) filesize($absolute));
            readfile($absolute);
            exit;
        } catch (\Throwable $e) {
            Session::flashAlert('error', 'Documento no disponible', 'No fue posible descargar el respaldo.');
            $this->redirect(url('/cctv/recording-requests/' . $id));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function requireOpenShiftOrRedirect(): array
    {
        $openShift = $this->shifts->findOpenForOperator(Auth::id() ?? 0);
        if ($openShift === null) {
            Session::flashAlert('warning', 'Turno requerido', 'Debe iniciar un turno antes de registrar visitas.');
            $this->redirect(url('/cctv/shifts/create'));
        }

        return $openShift;
    }
}
