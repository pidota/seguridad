<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Models\Cctv\LogType;
use App\Repositories\Cctv\OfficeVisitRepository;
use App\Repositories\Cctv\RecordingDeliveryRepository;
use App\Repositories\Cctv\RecordingRequestCameraRepository;
use App\Repositories\Cctv\RecordingRequestHistoryRepository;
use App\Repositories\Cctv\RecordingRequestRepository;
use App\Repositories\Cctv\RecordingRequestSequenceRepository;
use App\Repositories\SectorRepository;
use App\Support\ChileanRutValidator;
use Core\Database;
use Core\Exceptions\HttpException;

final class RecordingRequestService
{
    public function __construct(
        private readonly OfficeVisitRepository $visits = new OfficeVisitRepository(),
        private readonly RecordingRequestRepository $requests = new RecordingRequestRepository(),
        private readonly RecordingRequestHistoryRepository $history = new RecordingRequestHistoryRepository(),
        private readonly RecordingRequestCameraRepository $requestCameras = new RecordingRequestCameraRepository(),
        private readonly RecordingDeliveryRepository $deliveries = new RecordingDeliveryRepository(),
        private readonly RecordingRequestSequenceRepository $sequences = new RecordingRequestSequenceRepository(),
        private readonly RecordingRequestStatusCatalog $statuses = new RecordingRequestStatusCatalog(),
        private readonly SectorRepository $sectors = new SectorRepository(),
        private readonly CameraService $cameras = new CameraService(),
        private readonly LogEntryService $logEntries = new LogEntryService(),
        private readonly CctvAuditService $cctvAudit = new CctvAuditService(),
        private readonly ComplaintDocumentService $documents = new ComplaintDocumentService()
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{visit_id: int, request_id: int, request_number: string, status: string}
     */
    public function createWithVisit(array $data, int $shiftId, int $operatorId): array
    {
        $hasComplaint = $this->normalizeBool($data['has_complaint'] ?? null);
        $complaint = $hasComplaint ? $this->normalizeComplaintInform($data) : [
            'complaint_institution' => null,
            'complaint_number' => null,
            'complaint_date' => null,
            'complaint_observations' => null,
        ];

        if ($hasComplaint) {
            $status = RecordingRequestStatusCatalog::INCOMPLETE_DOCUMENTATION;
        }

        $sectorId = $this->nullableInt($data['sector_id'] ?? null);
        if ($sectorId !== null && $this->sectors->findById($sectorId) === null) {
            throw new HttpException(422, 'El sector seleccionado no es válido.');
        }

        $cameraId = $this->nullableInt($data['camera_id'] ?? null);
        if ($cameraId !== null) {
            try {
                $this->cameras->find($cameraId);
            } catch (\Throwable) {
                throw new HttpException(422, 'La cámara seleccionada no es válida.');
            }
        }

        return Database::transaction(function () use ($data, $shiftId, $operatorId, $hasComplaint, $status, $complaint, $sectorId, $cameraId): array {
            $visitId = $this->visits->create([
                'cctv_shift_id' => $shiftId,
                'visitor_type' => VisitorTypeCatalog::RECORDING,
                'visit_date' => $data['visit_date'],
                'arrival_time' => $this->normalizeTime($data['arrival_time'] ?? $data['request_time'] ?? date('H:i')),
                'departure_time' => $this->nullableTime($data['departure_time'] ?? null),
                'requester_name' => trim((string) $data['requester_name']),
                'requester_rut' => $this->nullableRut($data['requester_rut'] ?? null, true),
                'requester_phone' => $this->nullableString($data['requester_phone'] ?? null),
                'requester_email' => $this->nullableString($data['requester_email'] ?? null),
                'organization' => $this->nullableString($data['organization'] ?? null),
                'reason' => trim((string) $data['reason']),
                'recording_requested' => 1,
                'created_by' => $operatorId,
            ]);

            $year = (int) date('Y', strtotime((string) $data['visit_date']));
            $sequence = $this->sequences->next($year);
            $requestNumber = $this->sequences->formatNumber($year, $sequence);

            $document = null;
            if ($hasComplaint && !empty($data['complaint_document'])) {
                $document = $this->documents->store((int) $visitId, $data['complaint_document']);
            }

            $requestId = $this->requests->create([
                'office_visit_id' => $visitId,
                'request_number' => $requestNumber,
                'incident_date' => $data['incident_date'],
                'time_from' => $this->normalizeTime($data['time_from']),
                'time_to' => $this->normalizeTime($data['time_to']),
                'sector_id' => $sectorId,
                'cctv_camera_id' => $cameraId,
                'incident_description' => trim((string) $data['incident_description']),
                'has_complaint' => $hasComplaint ? 1 : 0,
                'complaint_institution' => $complaint['complaint_institution'],
                'complaint_number' => $complaint['complaint_number'],
                'complaint_date' => $complaint['complaint_date'],
                'complaint_observations' => $complaint['complaint_observations'],
                'complaint_document_path' => $document['path'] ?? null,
                'complaint_document_original_name' => $document['original_name'] ?? null,
                'complaint_document_mime' => $document['mime'] ?? null,
                'complaint_document_size' => $document['size'] ?? null,
                'status' => $status,
                'received_by' => $operatorId,
            ]);

            if ($cameraId !== null) {
                $this->requestCameras->syncForRequest($requestId, [$cameraId], $cameraId);
            }

            $this->logHistory($requestId, null, $status, $operatorId, $hasComplaint
                ? 'Solicitud registrada con denuncia informada.'
                : 'Solicitud registrada sin denuncia.');

            if ($hasComplaint) {
                $this->logHistory(
                    $requestId,
                    $status,
                    $status,
                    $operatorId,
                    'Denuncia informada. Pendiente de verificación.',
                    RecordingHistoryEventCatalog::COMPLAINT_REGISTERED
                );
            }

            $statusLabel = $this->statuses->label($status);
            $summary = sprintf('Solicitud %s registrada. Estado: %s.', $requestNumber, $statusLabel);
            $this->logEntries->createOfficeSummary(
                $shiftId,
                $operatorId,
                LogType::SLUG_RECORDING_REQUEST,
                $summary,
                (string) $data['visit_date'],
                $this->normalizeTime($data['arrival_time'] ?? $data['request_time'] ?? date('H:i')),
                'cctv_recording_request',
                $requestId
            );

            $created = $this->requests->findById($requestId);
            $this->cctvAudit->recordingRequestCreated($requestId, $this->snapshot($created));

            return [
                'visit_id' => $visitId,
                'request_id' => $requestId,
                'request_number' => $requestNumber,
                'status' => $status,
            ];
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function registerComplaint(int $requestId, array $data, int $userId): void
    {
        $current = $this->requireRequest($requestId);
        $this->assertNotImmutable($current);

        if ((string) ($current['status'] ?? '') === RecordingRequestStatusCatalog::CANCELLED) {
            throw new HttpException(422, 'La solicitud está anulada.');
        }

        $complaint = $this->normalizeComplaintInform($data);
        if (!$this->isComplaintInformComplete($complaint)) {
            throw new HttpException(422, 'Complete los antecedentes mínimos de la denuncia.');
        }

        $document = null;
        if (!empty($data['complaint_document'])) {
            $document = $this->documents->store(
                (int) ($current['office_visit_id'] ?? 0),
                $data['complaint_document'],
                $current['complaint_document_path'] ?? null
            );
        }

        Database::transaction(function () use ($requestId, $current, $complaint, $document, $userId): void {
            $old = $this->snapshot($current);
            $previous = (string) ($current['status'] ?? '');
            $newStatus = RecordingRequestStatusCatalog::INCOMPLETE_DOCUMENTATION;

            $this->requests->update($requestId, array_merge($complaint, [
                'has_complaint' => 1,
                'status' => $newStatus,
                'complaint_verified_by' => null,
                'complaint_verified_at' => null,
                'complaint_document_path' => $document['path'] ?? $current['complaint_document_path'] ?? null,
                'complaint_document_original_name' => $document['original_name'] ?? $current['complaint_document_original_name'] ?? null,
                'complaint_document_mime' => $document['mime'] ?? $current['complaint_document_mime'] ?? null,
                'complaint_document_size' => $document['size'] ?? $current['complaint_document_size'] ?? null,
            ]));

            $this->logHistory($requestId, $previous, $newStatus, $userId, 'Denuncia registrada.', RecordingHistoryEventCatalog::COMPLAINT_REGISTERED);

            $updated = $this->requests->findById($requestId);
            $this->cctvAudit->recordingRequestComplaintRegistered($requestId, $old, $this->snapshot($updated));
        });
    }

    public function verifyComplaint(int $requestId, int $userId, ?string $notes = null): void
    {
        if (!hasPermission('cctv.recordings.verify_complaint')) {
            throw new HttpException(403, 'No está autorizado para verificar denuncias.');
        }

        $current = $this->requireRequest($requestId);
        $this->assertNotImmutable($current);

        if ((int) ($current['has_complaint'] ?? 0) !== 1) {
            throw new HttpException(422, 'No existe denuncia registrada para verificar.');
        }

        if (!empty($current['complaint_verified_by'])) {
            throw new HttpException(422, 'La denuncia ya fue verificada.');
        }

        $this->assertComplaintDataComplete($current);

        Database::transaction(function () use ($requestId, $current, $userId, $notes): void {
            $old = $this->snapshot($current);
            $previous = (string) ($current['status'] ?? '');
            $newStatus = RecordingRequestStatusCatalog::PENDING_REVIEW;

            $this->requests->update($requestId, [
                'status' => $newStatus,
                'complaint_verified_by' => $userId,
                'complaint_verified_at' => date('Y-m-d H:i:s'),
            ]);

            $this->logHistory(
                $requestId,
                $previous,
                $newStatus,
                $userId,
                $notes ?? 'Denuncia verificada.',
                RecordingHistoryEventCatalog::COMPLAINT_VERIFIED
            );

            $updated = $this->requests->findById($requestId);
            $this->cctvAudit->recordingRequestStatusChanged($requestId, $old, $this->snapshot($updated), $previous, $newStatus);
        });
    }

    /**
     * @param array<string, mixed> $context
     */
    public function transitionStatus(int $requestId, string $newStatus, int $userId, array $context = []): void
    {
        if ($newStatus === RecordingRequestStatusCatalog::DELIVERED) {
            throw new HttpException(422, 'Use el flujo de entrega para marcar la solicitud como entregada.');
        }

        $current = $this->requireRequest($requestId);
        $this->assertNotImmutable($current);
        $previous = (string) ($current['status'] ?? '');

        if ((string) ($current['status'] ?? '') === RecordingRequestStatusCatalog::CANCELLED) {
            throw new HttpException(422, 'La solicitud está anulada.');
        }

        if ($newStatus === $previous) {
            return;
        }

        if (!$this->statuses->isValid($newStatus)) {
            throw new HttpException(422, 'El estado seleccionado no es válido.');
        }

        $allowed = $this->statuses->allowedTransitions(
            $previous,
            hasPermission('cctv.recordings.approve'),
            hasPermission('cctv.recordings.review'),
            hasPermission('cctv.recordings.deliver')
        );

        if (!in_array($newStatus, $allowed, true)) {
            throw new HttpException(403, 'No puede cambiar la solicitud a ese estado.');
        }

        if ($newStatus === RecordingRequestStatusCatalog::APPROVED && !hasPermission('cctv.recordings.approve')) {
            throw new HttpException(403, 'No está autorizado para aprobar entregas.');
        }

        if (in_array($newStatus, [
            RecordingRequestStatusCatalog::PENDING_REVIEW,
            RecordingRequestStatusCatalog::UNDER_REVIEW,
            RecordingRequestStatusCatalog::RECORDING_FOUND,
            RecordingRequestStatusCatalog::RECORDING_NOT_FOUND,
            RecordingRequestStatusCatalog::REJECTED,
            RecordingRequestStatusCatalog::INCOMPLETE_DOCUMENTATION,
        ], true) && !hasPermission('cctv.recordings.review')) {
            throw new HttpException(403, 'No está autorizado para revisar solicitudes.');
        }

        if ($newStatus !== RecordingRequestStatusCatalog::PENDING_COMPLAINT) {
            $this->assertComplaintVerified($current, $newStatus);
        }

        $notes = $this->nullableString($context['notes'] ?? null);
        $update = ['status' => $newStatus];

        if ($newStatus === RecordingRequestStatusCatalog::REJECTED) {
            $reason = trim((string) ($context['rejection_reason'] ?? ''));
            if (!RejectionReasonCatalog::isValid($reason)) {
                throw new HttpException(422, 'Indique el motivo de rechazo.');
            }
            $update['rejection_reason'] = $reason;
            $update['rejection_notes'] = $this->nullableString($context['rejection_notes'] ?? null);
        }

        if ($newStatus === RecordingRequestStatusCatalog::RECORDING_NOT_FOUND) {
            $reason = trim((string) ($context['not_found_reason'] ?? ''));
            if (!NotFoundReasonCatalog::isValid($reason)) {
                throw new HttpException(422, 'Indique el motivo de grabación no encontrada.');
            }
            $update['not_found_reason'] = $reason;
            $update['not_found_notes'] = $this->nullableString($context['not_found_notes'] ?? null);
            $update['not_found_cameras_reviewed'] = $this->nullableString($context['not_found_cameras_reviewed'] ?? null);
            $update['reviewed_by'] = $userId;
            $update['reviewed_at'] = date('Y-m-d H:i:s');
        }

        if (in_array($newStatus, [
            RecordingRequestStatusCatalog::UNDER_REVIEW,
            RecordingRequestStatusCatalog::RECORDING_FOUND,
        ], true)) {
            $update['reviewed_by'] = $userId;
            $update['reviewed_at'] = date('Y-m-d H:i:s');
        }

        if ($newStatus === RecordingRequestStatusCatalog::APPROVED) {
            $update['approved_by'] = $userId;
            $update['approved_at'] = date('Y-m-d H:i:s');
        }

        Database::transaction(function () use ($requestId, $current, $previous, $newStatus, $userId, $notes, $update): void {
            $old = $this->snapshot($current);
            $this->requests->update($requestId, $update);
            $this->logHistory($requestId, $previous, $newStatus, $userId, $notes);

            $updated = $this->requests->findById($requestId);
            $this->cctvAudit->recordingRequestStatusChanged($requestId, $old, $this->snapshot($updated), $previous, $newStatus);
        });
    }

    public function preserveRecording(int $requestId, int $userId, ?string $notes = null): void
    {
        if (!hasPermission('cctv.recordings.review')) {
            throw new HttpException(403, 'No está autorizado para preservar grabaciones.');
        }

        $current = $this->requireRequest($requestId);
        $this->assertNotImmutable($current);

        if (!$this->hasRecordingLocated($current)) {
            throw new HttpException(422, 'Solo puede preservar grabaciones previamente localizadas.');
        }

        if ((int) ($current['recording_preserved'] ?? 0) === 1) {
            return;
        }

        Database::transaction(function () use ($requestId, $current, $userId, $notes): void {
            $old = $this->snapshot($current);
            $this->requests->update($requestId, [
                'recording_preserved' => 1,
                'preserved_by' => $userId,
                'preserved_at' => date('Y-m-d H:i:s'),
            ]);

            $this->logHistory(
                $requestId,
                (string) ($current['status'] ?? ''),
                (string) ($current['status'] ?? ''),
                $userId,
                $notes ?? 'Grabación marcada como preservada.',
                RecordingHistoryEventCatalog::PRESERVED
            );

            $updated = $this->requests->findById($requestId);
            $this->cctvAudit->recordingRequestStatusChanged(
                $requestId,
                $old,
                $this->snapshot($updated),
                (string) ($current['status'] ?? ''),
                (string) ($current['status'] ?? '')
            );
        });
    }

    public function assignTo(int $requestId, ?int $assigneeId, int $userId, ?string $notes = null): void
    {
        if (!hasPermission('cctv.recordings.assign')) {
            throw new HttpException(403, 'No está autorizado para asignar solicitudes.');
        }

        $current = $this->requireRequest($requestId);
        $this->assertNotImmutable($current);

        Database::transaction(function () use ($requestId, $current, $assigneeId, $userId, $notes): void {
            $this->requests->update($requestId, ['assigned_to' => $assigneeId]);
            $this->logHistory(
                $requestId,
                (string) ($current['status'] ?? ''),
                (string) ($current['status'] ?? ''),
                $userId,
                $notes ?? ($assigneeId ? 'Responsable asignado.' : 'Responsable removido.'),
                RecordingHistoryEventCatalog::ASSIGNED
            );
        });
    }

    public function cancel(int $requestId, string $reason, int $userId): void
    {
        if (!hasPermission('cctv.recordings.cancel')) {
            throw new HttpException(403, 'No está autorizado para anular solicitudes.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new HttpException(422, 'Indique el motivo de anulación.');
        }

        $current = $this->requireRequest($requestId);
        if ((string) ($current['status'] ?? '') === RecordingRequestStatusCatalog::DELIVERED) {
            throw new HttpException(422, 'No puede anular una solicitud ya entregada.');
        }

        if ((string) ($current['status'] ?? '') === RecordingRequestStatusCatalog::CANCELLED) {
            return;
        }

        Database::transaction(function () use ($requestId, $current, $reason, $userId): void {
            $old = $this->snapshot($current);
            $previous = (string) ($current['status'] ?? '');

            $this->requests->update($requestId, [
                'status' => RecordingRequestStatusCatalog::CANCELLED,
                'cancelled_by' => $userId,
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancellation_reason' => $reason,
            ]);

            $this->logHistory(
                $requestId,
                $previous,
                RecordingRequestStatusCatalog::CANCELLED,
                $userId,
                $reason,
                RecordingHistoryEventCatalog::CANCELLED
            );

            $updated = $this->requests->findById($requestId);
            $this->cctvAudit->recordingRequestStatusChanged(
                $requestId,
                $old,
                $this->snapshot($updated),
                $previous,
                RecordingRequestStatusCatalog::CANCELLED
            );
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateCameraReview(int $requestId, int $cameraRowId, array $data, int $userId): void
    {
        if (!hasPermission('cctv.recordings.review')) {
            throw new HttpException(403, 'No está autorizado para revisar cámaras.');
        }

        $current = $this->requireRequest($requestId);
        $this->assertNotImmutable($current);
        $row = $this->requestCameras->findById($cameraRowId);

        if ($row === null || (int) ($row['recording_request_id'] ?? 0) !== $requestId) {
            throw new HttpException(404, 'La cámara indicada no pertenece a esta solicitud.');
        }

        $status = trim((string) ($data['review_status'] ?? ''));
        if (!CameraReviewStatusCatalog::isValid($status)) {
            throw new HttpException(422, 'Estado de revisión no válido.');
        }

        $this->requestCameras->update($cameraRowId, [
            'review_status' => $status,
            'reviewed_by' => $userId,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'notes' => $this->nullableString($data['notes'] ?? null),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function deliver(int $requestId, array $data, int $userId): void
    {
        if (!hasPermission('cctv.recordings.deliver')) {
            throw new HttpException(403, 'No está autorizado para entregar grabaciones.');
        }

        $current = $this->requireRequest($requestId);
        $this->assertCanDeliver($current);

        $receiverRut = $this->nullableRut($data['receiver_rut'] ?? null, true);
        $receiverName = trim((string) ($data['receiver_name'] ?? ''));
        if ($receiverName === '') {
            throw new HttpException(422, 'Indique el nombre de quien retira la grabación.');
        }

        $relationship = trim((string) ($data['receiver_relationship'] ?? 'solicitante'));
        if (!ReceiverRelationshipCatalog::isValid($relationship)) {
            throw new HttpException(422, 'Seleccione la relación del receptor con el solicitante.');
        }

        $medium = trim((string) ($data['delivery_medium'] ?? ''));
        if (!DeliveryMediumCatalog::isValid($medium)) {
            throw new HttpException(422, 'Seleccione un medio de entrega válido.');
        }

        Database::transaction(function () use ($requestId, $current, $data, $userId, $receiverRut, $receiverName, $relationship, $medium): void {
            $old = $this->snapshot($current);
            $deliveredAt = date('Y-m-d H:i:s');

            $this->deliveries->create([
                'recording_request_id' => $requestId,
                'delivered_at' => $deliveredAt,
                'delivered_by' => $userId,
                'receiver_name' => $receiverName,
                'receiver_rut' => $receiverRut,
                'receiver_relationship' => $relationship,
                'authorization_document' => $this->nullableString($data['authorization_document'] ?? null),
                'delivery_medium' => $medium,
                'notes' => $this->nullableString($data['delivery_notes'] ?? null),
                'public_notes' => $this->nullableString($data['public_notes'] ?? null),
                'internal_notes' => $this->nullableString($data['internal_notes'] ?? null),
                'file_internal_name' => $this->nullableString($data['file_internal_name'] ?? null),
                'file_camera_id' => $this->nullableInt($data['file_camera_id'] ?? null),
                'file_video_date' => $this->nullableString($data['file_video_date'] ?? null),
                'file_time_from' => $this->nullableTime($data['file_time_from'] ?? null),
                'file_time_to' => $this->nullableTime($data['file_time_to'] ?? null),
                'file_size_bytes' => $this->nullableInt($data['file_size_bytes'] ?? null),
                'file_hash_sha256' => $this->nullableString($data['file_hash_sha256'] ?? null),
            ]);

            $this->requests->update($requestId, [
                'status' => RecordingRequestStatusCatalog::DELIVERED,
                'delivered_by' => $userId,
                'delivered_at' => $deliveredAt,
                'delivery_notes' => $this->nullableString($data['delivery_notes'] ?? null),
            ]);

            $this->logHistory(
                $requestId,
                (string) ($current['status'] ?? ''),
                RecordingRequestStatusCatalog::DELIVERED,
                $userId,
                sprintf('Entregada a %s (%s).', $receiverName, $receiverRut),
                RecordingHistoryEventCatalog::DELIVERED
            );

            $updated = $this->requests->findById($requestId);
            $this->cctvAudit->recordingRequestDelivered($requestId, $old, $this->snapshot($updated));
        });
    }

    public function detail(int $requestId): array
    {
        $record = $this->requireRequest($requestId);
        $record['status_label'] = $this->statuses->label((string) $record['status']);
        $record['status_tone'] = $this->statuses->tone((string) $record['status']);
        $record['complaint_institution_label'] = ComplaintInstitutionCatalog::label($record['complaint_institution'] ?? null);
        $record['complaint_verified'] = !empty($record['complaint_verified_by']);
        $record['complaint_informed'] = (int) ($record['has_complaint'] ?? 0) === 1;
        $record['recording_located'] = $this->hasRecordingLocated($record);
        $record['show_preserve_warning'] = $record['recording_located']
            && (int) ($record['recording_preserved'] ?? 0) !== 1
            && (string) ($record['status'] ?? '') === RecordingRequestStatusCatalog::RECORDING_FOUND;
        $record['is_immutable'] = (string) ($record['status'] ?? '') === RecordingRequestStatusCatalog::DELIVERED
            && !hasPermission('cctv.recordings.edit_delivered');
        $record['rejection_reason_label'] = !empty($record['rejection_reason'])
            ? RejectionReasonCatalog::label((string) $record['rejection_reason'])
            : null;
        $record['not_found_reason_label'] = !empty($record['not_found_reason'])
            ? NotFoundReasonCatalog::label((string) $record['not_found_reason'])
            : null;

        $record['history'] = array_map(function (array $row): array {
            $row['previous_status_label'] = $row['previous_status']
                ? $this->statuses->label((string) $row['previous_status'])
                : '—';
            $row['new_status_label'] = $this->statuses->label((string) $row['new_status']);
            $row['event_label'] = RecordingHistoryEventCatalog::label(
                (string) ($row['event_type'] ?? RecordingHistoryEventCatalog::STATUS_CHANGE),
                (string) ($row['new_status_label'] ?? '')
            );

            return $row;
        }, $this->history->listByRequest($requestId));

        $record['delivery'] = $this->deliveries->findByRequestId($requestId);
        if ($record['delivery'] !== null) {
            $record['delivery']['delivery_medium_label'] = DeliveryMediumCatalog::label((string) $record['delivery']['delivery_medium']);
            $record['delivery']['receiver_relationship_label'] = !empty($record['delivery']['receiver_relationship'])
                ? ReceiverRelationshipCatalog::label((string) $record['delivery']['receiver_relationship'])
                : null;
        }

        $record['cameras'] = array_map(function (array $row): array {
            $row['review_status_label'] = CameraReviewStatusCatalog::label((string) ($row['review_status'] ?? ''));

            return $row;
        }, $this->requestCameras->listByRequest($requestId));

        $record['allowed_statuses'] = $this->statuses->allowedTransitions(
            (string) $record['status'],
            hasPermission('cctv.recordings.approve'),
            hasPermission('cctv.recordings.review'),
            hasPermission('cctv.recordings.deliver')
        );

        $record['delivery_summary'] = [
            'request_number' => $record['request_number'] ?? '',
            'requester_name' => $record['requester_name'] ?? '',
            'complaint_verified' => $record['complaint_verified'] ? 'Verificada' : 'No verificada',
            'recording_located' => $record['recording_located'] ? 'Localizada' : 'No localizada',
            'authorized' => (string) ($record['status'] ?? '') === RecordingRequestStatusCatalog::APPROVED ? 'Aprobada' : 'Pendiente',
        ];

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDeliveryReceipt(int $requestId): array
    {
        $record = $this->detail($requestId);
        if ((string) ($record['status'] ?? '') !== RecordingRequestStatusCatalog::DELIVERED) {
            throw new HttpException(422, 'La constancia solo está disponible para solicitudes entregadas.');
        }

        return [
            'title' => 'Constancia de Entrega de Grabación CCTV',
            'request_number' => $record['request_number'],
            'request_datetime' => date('d/m/Y H:i', strtotime((string) ($record['created_at'] ?? 'now'))),
            'requester_name' => $record['requester_name'],
            'requester_rut' => $record['requester_rut'],
            'incident_description' => $record['incident_description'],
            'incident_date' => date('d/m/Y', strtotime((string) ($record['incident_date'] ?? 'now'))),
            'time_from' => substr((string) ($record['time_from'] ?? ''), 0, 5),
            'time_to' => substr((string) ($record['time_to'] ?? ''), 0, 5),
            'sector_name' => $record['sector_name'] ?? '—',
            'camera_name' => $record['camera_name'] ?? '—',
            'complaint_institution' => $record['complaint_institution_label'] ?? '—',
            'complaint_number' => $record['complaint_number'] ?? '—',
            'receiver_name' => $record['delivery']['receiver_name'] ?? '—',
            'receiver_rut' => $record['delivery']['receiver_rut'] ?? '—',
            'delivery_medium' => $record['delivery']['delivery_medium_label'] ?? '—',
            'delivered_at' => date('d/m/Y H:i', strtotime((string) ($record['delivery']['delivered_at'] ?? 'now'))),
            'delivered_by' => $record['delivery']['delivered_by_name'] ?? '—',
            'public_notes' => $record['delivery']['public_notes'] ?? $record['delivery']['notes'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $request
     */
    public function assertCanDeliver(array $request): void
    {
        if ((string) ($request['status'] ?? '') !== RecordingRequestStatusCatalog::APPROVED) {
            throw new HttpException(422, 'La solicitud debe estar autorizada para entrega.');
        }

        if ((int) ($request['has_complaint'] ?? 0) !== 1) {
            throw new HttpException(422, 'Debe existir una denuncia registrada antes de entregar.');
        }

        if (empty($request['complaint_verified_by'])) {
            throw new HttpException(422, 'La denuncia debe estar verificada antes de entregar.');
        }

        if (!$this->hasRecordingLocated($request)) {
            throw new HttpException(422, 'Debe existir grabación localizada antes de entregar.');
        }

        if (empty($request['approved_by'])) {
            throw new HttpException(422, 'La solicitud debe contar con autorización explícita.');
        }
    }

    /**
     * @param array<string, mixed> $request
     */
    private function assertComplaintVerified(array $request, string $targetStatus): void
    {
        if (in_array($targetStatus, [
            RecordingRequestStatusCatalog::PENDING_COMPLAINT,
            RecordingRequestStatusCatalog::INCOMPLETE_DOCUMENTATION,
            RecordingRequestStatusCatalog::REJECTED,
            RecordingRequestStatusCatalog::CANCELLED,
        ], true)) {
            return;
        }

        if ((int) ($request['has_complaint'] ?? 0) !== 1) {
            throw new HttpException(422, 'Debe registrar la denuncia antes de avanzar.');
        }

        if (empty($request['complaint_verified_by'])) {
            throw new HttpException(422, 'La denuncia debe estar verificada antes de continuar.');
        }
    }

    /**
     * @param array<string, mixed> $request
     */
    private function assertComplaintDataComplete(array $request): void
    {
        if (trim((string) ($request['complaint_number'] ?? '')) === '') {
            throw new HttpException(422, 'Falta el número de denuncia.');
        }

        if (trim((string) ($request['complaint_date'] ?? '')) === '') {
            throw new HttpException(422, 'Falta la fecha de la denuncia.');
        }

        if (!ComplaintInstitutionCatalog::isValid((string) ($request['complaint_institution'] ?? ''))) {
            throw new HttpException(422, 'Falta la institución de la denuncia.');
        }
    }

    /**
     * @param array<string, mixed> $request
     */
    private function hasRecordingLocated(array $request): bool
    {
        if ((string) ($request['status'] ?? '') === RecordingRequestStatusCatalog::RECORDING_FOUND) {
            return true;
        }

        if (in_array((string) ($request['status'] ?? ''), [
            RecordingRequestStatusCatalog::APPROVED,
            RecordingRequestStatusCatalog::DELIVERED,
        ], true)) {
            return $this->history->hasStatus((int) $request['id'], RecordingRequestStatusCatalog::RECORDING_FOUND);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $request
     */
    private function assertNotImmutable(array $request): void
    {
        if ((string) ($request['status'] ?? '') !== RecordingRequestStatusCatalog::DELIVERED) {
            return;
        }

        if (!hasPermission('cctv.recordings.edit_delivered')) {
            throw new HttpException(403, 'La solicitud entregada no puede modificarse.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function requireRequest(int $requestId): array
    {
        $record = $this->requests->findById($requestId);
        if ($record === null) {
            throw new HttpException(404, 'La solicitud de grabación no existe.');
        }

        return $record;
    }

    /**
     * @param array<string, mixed>|null $record
     * @return array<string, mixed>
     */
    private function snapshot(?array $record): array
    {
        if ($record === null) {
            return [];
        }

        return [
            'id' => $record['id'],
            'request_number' => $record['request_number'],
            'status' => $record['status'],
            'has_complaint' => (int) ($record['has_complaint'] ?? 0),
            'complaint_verified_by' => $record['complaint_verified_by'] ?? null,
            'complaint_institution' => $record['complaint_institution'] ?? null,
            'complaint_number' => $record['complaint_number'] ?? null,
            'complaint_date' => $record['complaint_date'] ?? null,
            'sector_id' => $record['sector_id'] ?? null,
            'cctv_camera_id' => $record['cctv_camera_id'] ?? null,
            'recording_preserved' => (int) ($record['recording_preserved'] ?? 0),
        ];
    }

    private function logHistory(
        int $requestId,
        ?string $previous,
        string $newStatus,
        int $userId,
        ?string $notes = null,
        string $eventType = RecordingHistoryEventCatalog::STATUS_CHANGE
    ): void {
        $this->history->create([
            'recording_request_id' => $requestId,
            'event_type' => $eventType,
            'previous_status' => $previous,
            'new_status' => $newStatus,
            'notes' => $notes,
            'changed_by' => $userId,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeComplaintInform(array $data): array
    {
        $institution = trim((string) ($data['complaint_institution'] ?? ''));
        $number = trim((string) ($data['complaint_number'] ?? ''));
        $date = trim((string) ($data['complaint_date'] ?? ''));

        if ($institution !== '' && !ComplaintInstitutionCatalog::isValid($institution)) {
            throw new HttpException(422, 'Seleccione la institución donde realizó la denuncia.');
        }

        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new HttpException(422, 'Indique la fecha de la denuncia.');
        }

        return [
            'complaint_institution' => $institution !== '' ? $institution : null,
            'complaint_number' => $number !== '' ? $number : null,
            'complaint_date' => $date !== '' ? $date : null,
            'complaint_observations' => $this->nullableString($data['complaint_observations'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $complaint
     */
    private function isComplaintInformComplete(array $complaint): bool
    {
        return !empty($complaint['complaint_institution'])
            && !empty($complaint['complaint_number'])
            && !empty($complaint['complaint_date']);
    }

    private function normalizeBool(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'yes', 'si', 'sí', 'on'], true);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function nullableRut(mixed $value, bool $required = false): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            if ($required) {
                throw new HttpException(422, 'Indique un RUT válido.');
            }

            return null;
        }

        $formatted = ChileanRutValidator::format($value);
        if ($formatted === null) {
            throw new HttpException(422, 'El RUT ingresado no es válido.');
        }

        return $formatted;
    }

    private function normalizeTime(mixed $value): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value)) {
            throw new HttpException(422, 'La hora indicada no es válida.');
        }

        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    private function nullableTime(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return $this->normalizeTime($value);
    }
}
