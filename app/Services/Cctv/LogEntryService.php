<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Models\Cctv\LogContact;
use App\Models\Cctv\LogEntry;
use App\Models\Cctv\LogType;
use App\Models\Cctv\Shift;
use App\Models\Cctv\ShiftEquipmentCheck;
use App\Repositories\Cctv\LogContactRepository;
use App\Repositories\Cctv\LogEntryRepository;
use App\Repositories\Cctv\ShiftRepository;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;

final class LogEntryService
{
    public function __construct(
        private readonly LogEntryRepository $entries = new LogEntryRepository(),
        private readonly LogContactRepository $contacts = new LogContactRepository(),
        private readonly ShiftRepository $shifts = new ShiftRepository(),
        private readonly CatalogService $catalogs = new CatalogService(),
        private readonly CameraService $cameras = new CameraService(),
        private readonly CctvAuditService $cctvAudit = new CctvAuditService(),
        private readonly ClosedShiftPolicy $closedShiftPolicy = new ClosedShiftPolicy()
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'log_type_id' => '',
            'camera_id' => '',
            'sector_id' => '',
            'observations' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function incidentDefaults(): array
    {
        return [
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'incident_type_id' => '',
            'incident_type_other' => '',
            'sector_id' => '',
            'camera_id' => '',
            'observations' => '',
            'coordination_notified' => '',
            'police_arrived' => '',
            'police_arrival_time' => '',
            'status' => LogEntry::STATUS_REGISTERED,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function technicalDefaults(): array
    {
        return [
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'target_type' => 'camera',
            'camera_id' => '',
            'equipment_id' => '',
            'technical_issue_type_id' => '',
            'technical_issue_other' => '',
            'observations' => '',
            'status' => TechnicalEntryCatalog::STATUS_DETECTED,
            'camera_status' => '',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createTechnicalForOpenShift(array $data, ?int $operatorId = null): int
    {
        $operatorId ??= Auth::id();
        if ($operatorId === null || $operatorId < 1) {
            throw new HttpException(403, 'No hay un operador autenticado.');
        }

        $openShift = $this->shifts->findOpenByOperator($operatorId);
        if ($openShift === null) {
            throw new HttpException(422, 'Debe iniciar un turno antes de registrar una novedad técnica.');
        }

        $shiftId = (int) ($openShift['id'] ?? 0);
        $this->assertShiftIsOpen($shiftId, $operatorId);

        $logType = $this->catalogs->findLogTypeBySlug(LogType::SLUG_TECHNICAL);
        if ($logType === null) {
            throw new HttpException(422, 'No está configurado el tipo de registro de novedad técnica.');
        }

        $cameraStatus = trim((string) ($data['camera_status'] ?? ''));
        unset($data['camera_status']);

        $data['shift_id'] = $shiftId;
        $data['log_type_id'] = (int) ($logType['id'] ?? 0);

        return Database::transaction(function () use ($data, $operatorId, $cameraStatus): int {
            $payload = $this->buildPayload($data, $operatorId);
            $cameraId = (int) ($payload['cctv_camera_id'] ?? 0);

            if ($cameraStatus !== '') {
                if ($cameraId < 1) {
                    throw new HttpException(422, 'Seleccione una cámara para actualizar su estado.');
                }

                if (!CameraCatalog::isValidStatus($cameraStatus)) {
                    throw new HttpException(422, 'Seleccione un estado de cámara válido.');
                }

                $payload['camera_status_applied'] = $cameraStatus;
            }

            $id = $this->entries->create($payload);

            if ($cameraId > 0 && $cameraStatus !== '') {
                $this->cameras->applyStatus($cameraId, $cameraStatus, $id);
            }

            $created = $this->entries->findById($id);
            $presented = $created ? $this->present($created) : $payload;

            $this->cctvAudit->logEntryCreated($id, $presented);

            return $id;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createIncidentForOpenShift(array $data, ?int $operatorId = null): int
    {
        $operatorId ??= Auth::id();
        if ($operatorId === null || $operatorId < 1) {
            throw new HttpException(403, 'No hay un operador autenticado.');
        }

        $openShift = $this->shifts->findOpenByOperator($operatorId);
        if ($openShift === null) {
            throw new HttpException(422, 'Debe iniciar un turno antes de registrar un incidente.');
        }

        $shiftId = (int) ($openShift['id'] ?? 0);
        $this->assertShiftIsOpen($shiftId, $operatorId);

        $logType = $this->catalogs->findLogTypeBySlug('incidente');
        if ($logType === null) {
            throw new HttpException(422, 'No está configurado el tipo de registro de incidentes.');
        }

        $data['shift_id'] = $shiftId;
        $data['log_type_id'] = (int) ($logType['id'] ?? 0);

        return $this->create($data, $operatorId);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createForOpenShift(array $data, ?int $operatorId = null): int
    {
        $operatorId ??= Auth::id();
        if ($operatorId === null || $operatorId < 1) {
            throw new HttpException(403, 'No hay un operador autenticado.');
        }

        $openShift = $this->shifts->findOpenByOperator($operatorId);
        if ($openShift === null) {
            throw new HttpException(422, 'Debe iniciar un turno antes de registrar una novedad.');
        }

        $shiftId = (int) ($openShift['id'] ?? 0);
        $this->assertShiftIsOpen($shiftId, $operatorId);

        $data['shift_id'] = $shiftId;

        return $this->create($data, $operatorId);
    }

    public function find(int $id): array
    {
        $record = $this->entries->findById($id);

        if ($record === null) {
            throw new HttpException(404, 'La entrada de bitácora no existe.');
        }

        $presented = $this->present($record);
        $presented['contacts'] = $this->presentContacts($id);
        $presented['contacts_summary'] = $this->buildContactsSummary($presented['contacts']);

        return $presented;
    }

    /**
     * @return array<string, mixed>
     */
    public function recordForEdit(int $id): array
    {
        $record = $this->find($id);

        if (PoliceArrivalCatalog::isYes($record['police_arrived'] ?? null)) {
            $record['police_arrival_time'] = (string) ($record['police_arrival_time_formatted'] ?? '');
        } else {
            $record['police_arrival_time'] = '';
        }

        $record['coordination_notified'] = (string) ((int) ($record['coordination_notified'] ?? 0));
        $record['police_arrived'] = $record['police_arrived'] !== null && $record['police_arrived'] !== ''
            ? (string) (int) $record['police_arrived']
            : '';

        if (($record['log_type_slug'] ?? '') === LogType::SLUG_TECHNICAL) {
            $record['target_type'] = ((int) ($record['equipment_id'] ?? 0)) > 0 ? 'equipment' : 'camera';
            $record['camera_status'] = (string) ($record['camera_status_applied'] ?? '');
        }

        $record['contacts_form'] = $this->contactsFormRows($record['contacts'] ?? []);

        return $record;
    }

    public function detailForView(int $id, ?int $viewerId = null): array
    {
        $record = $this->entries->findById($id);

        if ($record === null) {
            throw new HttpException(404, 'La entrada de bitácora no existe.');
        }

        $viewerId ??= Auth::id();
        if (!hasPermission('cctv.log.view_all')) {
            if ($viewerId === null || (int) ($record['created_by'] ?? 0) !== $viewerId) {
                throw new HttpException(403, 'No puede consultar registros de otros operadores.');
            }
        }

        $presented = $this->presentDetail($record);
        $presented['contacts'] = $this->presentContacts($id);
        if ($presented['contacts'] !== []) {
            $presented['show_coordinations'] = true;
        }

        return $presented;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentContacts(int $entryId): array
    {
        if ($entryId < 1) {
            return [];
        }

        return array_map(
            [$this, 'presentContact'],
            $this->contacts->listByEntry($entryId)
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByShift(int $shiftId, int $limit = 100, string $order = 'desc'): array
    {
        return array_map(
            [$this, 'present'],
            $this->entries->listByShift($shiftId, $limit, $order)
        );
    }

    /**
     * @param array<string, mixed> $openShift
     * @param list<array<string, mixed>> $openingChecks
     * @param list<array<string, mixed>> $closingChecks
     * @return array{
     *     items: list<array<string, mixed>>,
     *     order: string,
     *     total: int
     * }
     */
    public function shiftTimeline(
        array $openShift,
        array $openingChecks = [],
        string $order = 'desc',
        array $closingChecks = []
    ): array {
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';
        $shiftId = (int) ($openShift['id'] ?? 0);

        if ($shiftId < 1) {
            return ['items' => [], 'order' => $order, 'total' => 0];
        }

        $items = $this->presentTimelineRows(
            $this->entries->listByShift($shiftId, 500, $order)
        );

        $items[] = $this->presentOpeningTimelineRow($openShift, $openingChecks);

        if (Shift::isClosed($openShift['status'] ?? null)) {
            $items[] = $this->presentClosingTimelineRow($openShift, $closingChecks);
        }

        $items = $this->sortTimeline($items, $order);

        return [
            'items' => $items,
            'order' => $order,
            'total' => count($items),
        ];
    }

    /**
     * @param array<string, mixed> $openShift
     * @param list<array<string, mixed>> $openingChecks
     * @return array{
     *     stats: array<string, int>,
     *     recent_items: list<array<string, mixed>>,
     *     timeline: array{items: list<array<string, mixed>>, order: string, total: int}
     * }
     */
    public function activeShiftDashboard(
        array $openShift,
        array $openingChecks = [],
        int $camerasWithIssues = 0,
        int $recentLimit = 10,
        string $logOrder = 'desc'
    ): array {
        $shiftId = (int) ($openShift['id'] ?? 0);
        $stats = $shiftId > 0 ? $this->entries->shiftStats($shiftId) : [
            'total_entries' => 0,
            'incidents' => 0,
            'general_entries' => 0,
            'technical_issues' => 0,
            'coordinations' => 0,
            'police_communications' => 0,
        ];
        $stats['cameras_with_issues'] = $camerasWithIssues;

        $recentLimit = max(1, min($recentLimit, 10));
        $recentItems = $this->presentTimelineRows(
            $this->entries->listByShift($shiftId, $recentLimit, $logOrder)
        );

        $timeline = $this->shiftTimeline($openShift, $openingChecks, $logOrder);

        return [
            'stats' => $stats,
            'recent_items' => $recentItems,
            'timeline' => $timeline,
        ];
    }

    /**
     * @return array{desc: string, asc: string}
     */
    public static function timelineOrderOptions(): array
    {
        return [
            'desc' => 'Más reciente primero',
            'asc' => 'Cronológico',
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function paginateByShift(int $shiftId, int $page, int $perPage = 15): array
    {
        $page = max(1, $page);
        $result = $this->entries->paginateByShift($shiftId, $page, $perPage);
        $pages = max(1, (int) ceil($result['total'] / $perPage));

        return [
            'data' => array_map([$this, 'present'], $result['data']),
            'total' => $result['total'],
            'page' => min($page, $pages),
            'pages' => $pages,
        ];
    }

    /**
     * @param array{
     *     created_by?: int|string|null,
     *     date_from?: string,
     *     date_to?: string,
     *     log_type?: string,
     *     incident_type?: string,
     *     sector_id?: int|string|null,
     *     camera_id?: int|string|null,
     *     contact_type?: string,
     *     status?: string,
     *     q?: string,
     *     police_arrived?: string
     * } $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function searchHistory(array $filters, int $page, int $perPage = 15): array
    {
        $page = max(1, $page);
        $result = $this->entries->paginate($filters, $page, $perPage);
        $pages = max(1, (int) ceil($result['total'] / $perPage));

        return [
            'data' => array_map([$this, 'presentHistory'], $result['data']),
            'total' => $result['total'],
            'page' => min($page, $pages),
            'pages' => $pages,
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function operatorOptions(): array
    {
        return $this->entries->operatorOptions();
    }

    /**
     * @return array{
     *     total_entries: int,
     *     incidents: int,
     *     technical_issues: int,
     *     coordinations: int
     * }
     */
    public function todayActivityStats(?string $date = null): array
    {
        $date ??= date('Y-m-d');

        return $this->entries->statsForDate($date);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentForSupervision(int $limit = 8): array
    {
        return array_map(
            [$this, 'presentHistory'],
            $this->entries->recentEntries($limit)
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function presentHistory(array $row): array
    {
        $presented = $this->present($row);
        $presented['operator_label'] = trim((string) ($presented['created_by_name'] ?? '')) ?: '—';
        $presented['type_label'] = trim((string) ($presented['log_type_name'] ?? '')) ?: '—';
        $presented['incident_label'] = (string) ($presented['incident_type_display'] ?? '—');
        $presented['sector_label'] = trim((string) ($presented['sector_name'] ?? '')) ?: '—';
        $presented['camera_label'] = $this->listCameraLabel($presented);
        $presented['coordination_label'] = (string) ($presented['coordination_notified_label'] ?? '—');
        $presented['police_label'] = $this->listPoliceLabel($presented);
        $presented['summary'] = $this->summarizeText((string) ($presented['observations'] ?? ''), 160);

        return $presented;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, ?int $createdBy = null): int
    {
        $createdBy ??= Auth::id();
        if ($createdBy === null || $createdBy < 1) {
            throw new HttpException(403, 'No hay un usuario autenticado para registrar la entrada.');
        }

        $payload = $this->buildPayload($data, $createdBy);
        $this->assertShiftIsOpen((int) $payload['cctv_shift_id'], $createdBy);
        $contactPayload = $this->normalizeContactsInput($data, $data['contacts'] ?? []);

        return Database::transaction(function () use ($payload, $data, $createdBy, $contactPayload): int {
            $id = $this->entries->create($payload);

            if ($this->nullableBool($data['coordination_notified'] ?? null) === 1 && $contactPayload !== []) {
                $this->contacts->createMany($id, $contactPayload);
            }

            $created = $this->entries->findById($id);
            $presented = $created ? $this->present($created) : $payload;

            if ($created !== null) {
                $presented['contacts'] = $this->presentContacts($id);
                $presented['contacts_summary'] = $this->buildContactsSummary($presented['contacts']);
            }

            $this->cctvAudit->logEntryCreated($id, $presented);

            if ($contactPayload !== []) {
                $this->cctvAudit->coordinationRegistered(
                    $id,
                    $presented['contacts'] ?? [],
                    $this->cctvAudit->sanitizeLogEntry($presented)
                );
            }

            return $id;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $currentRecord = $this->entries->findById($id);
        if ($currentRecord === null) {
            throw new HttpException(404, 'La entrada de bitácora no existe.');
        }

        $current = $this->find($id);
        $shift = $this->closedShiftPolicy->assertLogEntryMutation(
            (int) ($currentRecord['cctv_shift_id'] ?? 0),
            $currentRecord
        );
        $wasClosed = Shift::isClosed((string) ($shift['status'] ?? ''));
        $logTypeSlug = (string) ($current['log_type_slug'] ?? '');

        $merged = array_merge($currentRecord, $data);
        $merged['log_type_slug'] = $logTypeSlug;

        if ($logTypeSlug === LogType::SLUG_INCIDENT) {
            $logType = $this->catalogs->findLogTypeBySlug(LogType::SLUG_INCIDENT);
            $merged['log_type_id'] = (int) ($logType['id'] ?? $current['log_type_id'] ?? 0);
        } elseif ($logTypeSlug === LogType::SLUG_TECHNICAL) {
            $logType = $this->catalogs->findLogTypeBySlug(LogType::SLUG_TECHNICAL);
            $merged['log_type_id'] = (int) ($logType['id'] ?? $current['log_type_id'] ?? 0);
        }

        $cameraStatus = trim((string) ($data['camera_status'] ?? ''));
        $contactPayload = $this->normalizeContactsInput($merged, $data['contacts'] ?? []);
        $payload = $this->buildPayload($merged, (int) ($currentRecord['created_by'] ?? Auth::id() ?? 0));
        $payload['cctv_shift_id'] = (int) ($currentRecord['cctv_shift_id'] ?? 0);

        Database::transaction(function () use (
            $id,
            $payload,
            $data,
            $contactPayload,
            $logTypeSlug,
            $cameraStatus
        ): void {
            $this->entries->update($id, $payload);
            $this->contacts->deleteByEntry($id);

            if ($this->nullableBool($data['coordination_notified'] ?? null) === 1 && $contactPayload !== []) {
                $this->contacts->createMany($id, $contactPayload);
            }

            if ($logTypeSlug === LogType::SLUG_TECHNICAL) {
                $cameraId = (int) ($payload['cctv_camera_id'] ?? 0);
                if ($cameraId > 0 && $cameraStatus !== '') {
                    $this->cameras->applyStatus($cameraId, $cameraStatus, $id);
                }
            }
        });

        $updated = $this->find($id);
        $this->cctvAudit->logEntryUpdated($id, $current, $updated, $wasClosed);
    }

    public function cancel(int $id, ?int $cancelledBy = null): void
    {
        $cancelledBy ??= Auth::id();
        if ($cancelledBy === null || $cancelledBy < 1) {
            throw new HttpException(403, 'No hay un usuario autenticado para anular el registro.');
        }

        $currentRecord = $this->entries->findById($id);
        if ($currentRecord === null) {
            throw new HttpException(404, 'La entrada de bitácora no existe.');
        }

        $current = $this->find($id);
        $shift = $this->closedShiftPolicy->assertLogEntryCancellation(
            (int) ($currentRecord['cctv_shift_id'] ?? 0),
            $currentRecord,
            $cancelledBy
        );
        $wasClosed = Shift::isClosed((string) ($shift['status'] ?? ''));

        $this->entries->softDelete($id, $cancelledBy);

        $user = Auth::user();
        $current['cancelled_by'] = $cancelledBy;
        $current['cancelled_by_name'] = trim((string) ($user['name'] ?? '')) ?: null;
        $current['cancelled_at'] = date('Y-m-d H:i:s');

        $this->cctvAudit->logEntryCancelled($id, $current, $wasClosed);
    }

    /**
     * @deprecated Use cancel() — conserva compatibilidad con pruebas internas.
     */
    public function delete(int $id): void
    {
        $this->cancel($id);
    }

    /**
     * Combina fecha y hora de interfaz en occurred_at.
     */
    public function buildOccurredAt(string $eventDate, ?string $eventTime = null): string
    {
        $eventDate = trim($eventDate);
        $eventTime = trim((string) $eventTime);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            throw new HttpException(422, 'Indique una fecha de evento válida.');
        }

        if ($eventTime === '') {
            return $eventDate . ' 00:00:00';
        }

        if (preg_match('/^\d{2}:\d{2}$/', $eventTime) === 1) {
            return $eventDate . ' ' . $eventTime . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $eventTime) === 1) {
            return $eventDate . ' ' . $eventTime;
        }

        throw new HttpException(422, 'Indique una hora de evento válida.');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $occurredAt = trim((string) ($row['occurred_at'] ?? ''));
        $timestamp = $occurredAt !== '' ? strtotime($occurredAt) : false;

        $row['shift_id'] = (int) ($row['cctv_shift_id'] ?? 0);
        $row['log_type_id'] = (int) ($row['cctv_log_type_id'] ?? 0);
        $row['incident_type_id'] = isset($row['cctv_incident_type_id']) && $row['cctv_incident_type_id'] !== null
            ? (int) $row['cctv_incident_type_id']
            : null;
        $row['camera_id'] = isset($row['cctv_camera_id']) && $row['cctv_camera_id'] !== null
            ? (int) $row['cctv_camera_id']
            : null;
        $row['sector_id'] = isset($row['sector_id']) && $row['sector_id'] !== null
            ? (int) $row['sector_id']
            : null;

        $row['equipment_id'] = isset($row['cctv_equipment_id']) && $row['cctv_equipment_id'] !== null
            ? (int) $row['cctv_equipment_id']
            : null;
        $row['technical_issue_type_id'] = isset($row['cctv_technical_issue_type_id']) && $row['cctv_technical_issue_type_id'] !== null
            ? (int) $row['cctv_technical_issue_type_id']
            : null;

        $row['event_date'] = $timestamp !== false ? date('Y-m-d', $timestamp) : '';
        $row['event_time'] = $timestamp !== false ? date('H:i', $timestamp) : '';
        $row['event_date_formatted'] = $timestamp !== false ? date('d-m-Y', $timestamp) : '—';
        $row['event_time_formatted'] = $timestamp !== false ? date('H:i', $timestamp) : '—';
        $row['occurred_at_formatted'] = $timestamp !== false ? date('d-m-Y H:i', $timestamp) : '—';

        $logTypeSlug = (string) ($row['log_type_slug'] ?? '');
        $row['status_label'] = $this->statusLabel((string) ($row['status'] ?? ''), $logTypeSlug);
        $statusMeta = $this->statusMeta((string) ($row['status'] ?? ''), $logTypeSlug);
        $row['status_tone'] = $statusMeta['tone'];
        $row['police_arrived_label'] = PoliceArrivalCatalog::label($row['police_arrived'] ?? null);
        $row['coordination_notified_label'] = $this->booleanLabel($row['coordination_notified'] ?? null);
        $row['police_arrival_time_formatted'] = PoliceArrivalCatalog::isYes($row['police_arrived'] ?? null)
            ? $this->formatTime($row['police_arrival_time'] ?? null)
            : '—';
        $row['log_type_tone'] = trim((string) ($row['log_type_tone'] ?? 'other')) ?: 'other';
        $row['incident_type_display'] = $this->incidentTypeDisplay($row);
        $row['technical_issue_display'] = $this->technicalIssueDisplay($row);
        $row['target_label'] = $this->targetLabel($row);
        $row['camera_status_applied_label'] = CameraCatalog::label(
            CameraCatalog::statuses(),
            $row['camera_status_applied'] ?? null
        );

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentDetail(array $row): array
    {
        $presented = $this->present($row);
        $logTypeSlug = (string) ($presented['log_type_slug'] ?? '');

        $shiftDate = trim((string) ($row['shift_date'] ?? ''));
        $shiftTimestamp = $shiftDate !== '' ? strtotime($shiftDate) : false;
        $shiftDateFormatted = $shiftTimestamp !== false ? date('d-m-Y', $shiftTimestamp) : '—';
        $shiftOperator = trim((string) ($row['shift_operator_name'] ?? '')) ?: '—';

        $presented['shift_date_formatted'] = $shiftDateFormatted;
        $presented['shift_operator_label'] = $shiftOperator;
        $presented['shift_label'] = $shiftDateFormatted . ' · ' . $shiftOperator;
        $presented['operator_label'] = trim((string) ($presented['created_by_name'] ?? '')) ?: '—';
        $presented['type_label'] = trim((string) ($presented['log_type_name'] ?? '')) ?: '—';
        $presented['sector_label'] = trim((string) ($presented['sector_name'] ?? '')) ?: '—';
        $presented['camera_label'] = $this->listCameraLabel($presented);

        if ($presented['camera_label'] === '—' && $logTypeSlug === LogType::SLUG_TECHNICAL) {
            $presented['camera_label'] = (string) ($presented['target_label'] ?? '—');
        }

        if ($logTypeSlug === LogType::SLUG_INCIDENT) {
            $presented['incident_label'] = (string) ($presented['incident_type_display'] ?? '—');
        } elseif ($logTypeSlug === LogType::SLUG_TECHNICAL) {
            $presented['incident_label'] = (string) ($presented['technical_issue_display'] ?? '—');
        } else {
            $presented['incident_label'] = '—';
        }

        $createdAt = trim((string) ($row['created_at'] ?? ''));
        $createdTimestamp = $createdAt !== '' ? strtotime($createdAt) : false;
        $presented['created_at_formatted'] = $createdTimestamp !== false
            ? date('d-m-Y H:i', $createdTimestamp)
            : '—';

        $updatedAt = trim((string) ($row['updated_at'] ?? ''));
        $updatedTimestamp = $updatedAt !== '' ? strtotime($updatedAt) : false;
        $presented['updated_at_formatted'] = $updatedTimestamp !== false
            ? date('d-m-Y H:i', $updatedTimestamp)
            : '—';

        $presented['show_coordinations'] = $this->nullableBool($presented['coordination_notified'] ?? null) === 1;
        $presented['show_police'] = $logTypeSlug === LogType::SLUG_INCIDENT
            || ($presented['police_arrived'] ?? null) !== null;

        return $presented;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function technicalIssueDisplay(array $row): string
    {
        $name = trim((string) ($row['technical_issue_type_name'] ?? ''));
        $other = trim((string) ($row['technical_issue_other'] ?? ''));

        if ($name === '') {
            return $other !== '' ? $other : '—';
        }

        if ($other !== '' && ($row['technical_issue_type_slug'] ?? '') === 'otro') {
            return $name . ' — ' . $other;
        }

        return $name;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function listCameraLabel(array $row): string
    {
        $camera = trim((string) ($row['camera_name'] ?? ''));
        if ($camera === '') {
            return '—';
        }

        $code = trim((string) ($row['camera_code'] ?? ''));

        return $code !== '' ? $code . ' — ' . $camera : $camera;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function listPoliceLabel(array $row): string
    {
        $label = (string) ($row['police_arrived_label'] ?? '—');
        if (!PoliceArrivalCatalog::isYes($row['police_arrived'] ?? null)) {
            return $label;
        }

        $time = trim((string) ($row['police_arrival_time_formatted'] ?? ''));
        if ($time !== '' && $time !== '—') {
            return $label . ' (' . $time . ')';
        }

        return $label;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function targetLabel(array $row): string
    {
        $camera = trim((string) ($row['camera_name'] ?? ''));
        if ($camera !== '') {
            $code = trim((string) ($row['camera_code'] ?? ''));

            return $code !== '' ? $code . ' — ' . $camera : $camera;
        }

        $equipment = trim((string) ($row['equipment_name'] ?? ''));

        return $equipment !== '' ? $equipment : '—';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function incidentTypeDisplay(array $row): string
    {
        $name = trim((string) ($row['incident_type_name'] ?? ''));
        $other = trim((string) ($row['incident_type_other'] ?? ''));

        if ($name === '') {
            return $other !== '' ? $other : '—';
        }

        if ($other !== '' && ($row['incident_type_slug'] ?? '') === 'otro') {
            return $name . ' — ' . $other;
        }

        return $name;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function presentTimelineRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $coordinationIds = [];
        foreach ($rows as $row) {
            if ((int) ($row['coordination_notified'] ?? 0) === 1) {
                $coordinationIds[] = (int) ($row['id'] ?? 0);
            }
        }

        $contactsByEntry = $this->contactsMapForEntries($coordinationIds);

        return array_map(
            fn (array $row): array => $this->presentTimelineRow(
                $row,
                $contactsByEntry[(int) ($row['id'] ?? 0)] ?? []
            ),
            $rows
        );
    }

    /**
     * @param list<int> $entryIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function contactsMapForEntries(array $entryIds): array
    {
        $entryIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $entryIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($entryIds === []) {
            return [];
        }

        $grouped = $this->contacts->listByEntries($entryIds);
        $presented = [];

        foreach ($grouped as $entryId => $contacts) {
            $presented[$entryId] = array_map([$this, 'presentContact'], $contacts);
        }

        return $presented;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $presentedContacts
     * @return array<string, mixed>
     */
    private function presentTimelineRow(array $row, array $presentedContacts = []): array
    {
        $presented = $this->present($row);
        $summary = $this->summarizeText((string) ($presented['observations'] ?? ''));

        if ($this->nullableBool($presented['coordination_notified'] ?? null) === 1) {
            if ($presentedContacts === [] && (int) ($presented['id'] ?? 0) > 0) {
                $presentedContacts = $this->presentContacts((int) $presented['id']);
            }

            $contactsSummary = $this->buildContactsSummary($presentedContacts);

            if ($contactsSummary !== '') {
                $summary = $summary !== ''
                    ? $contactsSummary . ' — ' . $summary
                    : $contactsSummary;
            }
        }

        return [
            'kind' => 'log_entry',
            'id' => (int) ($presented['id'] ?? 0),
            'occurred_at' => (string) ($presented['occurred_at'] ?? ''),
            'time_label' => (string) ($presented['event_time_formatted'] ?? '—'),
            'type_label' => $this->timelineTypeLabel($presented),
            'type_tone' => (string) (($presented['log_type_slug'] ?? '') === LogType::SLUG_TECHNICAL
                ? (($presented['technical_issue_type_tone'] ?? '') !== ''
                    ? $presented['technical_issue_type_tone']
                    : ($presented['log_type_tone'] ?? 'other'))
                : (($presented['incident_type_tone'] ?? '') !== ''
                    ? $presented['incident_type_tone']
                    : ($presented['log_type_tone'] ?? 'other'))),
            'camera_label' => $this->timelineCameraLabel($presented),
            'sector_label' => $this->timelineSectorLabel($presented),
            'summary' => $summary,
            'user_label' => trim((string) ($presented['created_by_name'] ?? '')) ?: '—',
            'can_view' => true,
            'log_type_slug' => (string) ($presented['log_type_slug'] ?? ''),
            'is_coordination' => $this->nullableBool($presented['coordination_notified'] ?? null) === 1,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function timelineTypeLabel(array $row): string
    {
        if (($row['log_type_slug'] ?? '') === LogType::SLUG_TECHNICAL) {
            $technical = trim((string) ($row['technical_issue_display'] ?? ''));
            if ($technical !== '' && $technical !== '—') {
                return mb_strtoupper($technical);
            }

            return 'NOVEDAD TÉCNICA';
        }

        $incident = trim((string) ($row['incident_type_display'] ?? ''));
        if ($incident !== '' && $incident !== '—') {
            return mb_strtoupper($incident);
        }

        return mb_strtoupper((string) ($row['log_type_name'] ?? 'REGISTRO'));
    }

    /**
     * @param array<string, mixed> $openShift
     * @param list<array<string, mixed>> $openingChecks
     * @return array<string, mixed>
     */
    private function presentOpeningTimelineRow(array $openShift, array $openingChecks): array
    {
        $startedAt = trim((string) ($openShift['started_at'] ?? ''));
        $timestamp = $startedAt !== '' ? strtotime($startedAt) : false;

        return [
            'kind' => 'shift_opening',
            'id' => null,
            'occurred_at' => $startedAt,
            'time_label' => $timestamp !== false ? date('H:i', $timestamp) : '—',
            'type_label' => 'INICIO DE TURNO',
            'type_tone' => 'success',
            'camera_label' => '—',
            'sector_label' => '—',
            'summary' => $this->buildOpeningSummary($openShift, $openingChecks),
            'user_label' => trim((string) ($openShift['operator_name'] ?? '')) ?: '—',
            'can_view' => false,
            'log_type_slug' => '',
            'is_coordination' => false,
        ];
    }

    /**
     * @param array<string, mixed> $shift
     * @param list<array<string, mixed>> $closingChecks
     * @return array<string, mixed>
     */
    private function presentClosingTimelineRow(array $shift, array $closingChecks): array
    {
        $endedAt = trim((string) ($shift['ended_at'] ?? ''));
        $timestamp = $endedAt !== '' ? strtotime($endedAt) : false;

        return [
            'kind' => 'shift_closing',
            'id' => null,
            'occurred_at' => $endedAt,
            'time_label' => $timestamp !== false ? date('H:i', $timestamp) : '—',
            'type_label' => 'CIERRE DE TURNO',
            'type_tone' => 'other',
            'camera_label' => '—',
            'sector_label' => '—',
            'summary' => $this->buildClosingSummary($shift, $closingChecks),
            'user_label' => trim((string) ($shift['operator_name'] ?? '')) ?: '—',
            'can_view' => false,
            'log_type_slug' => '',
            'is_coordination' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function sortTimeline(array $items, string $order): array
    {
        usort($items, static function (array $left, array $right) use ($order): int {
            $leftAt = strtotime((string) ($left['occurred_at'] ?? '')) ?: 0;
            $rightAt = strtotime((string) ($right['occurred_at'] ?? '')) ?: 0;

            if ($leftAt === $rightAt) {
                $leftId = (int) ($left['id'] ?? 0);
                $rightId = (int) ($right['id'] ?? 0);

                return $order === 'asc' ? $leftId <=> $rightId : $rightId <=> $leftId;
            }

            return $order === 'asc' ? $leftAt <=> $rightAt : $rightAt <=> $leftAt;
        });

        return $items;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function timelineCameraLabel(array $row): string
    {
        $name = trim((string) ($row['camera_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $equipment = trim((string) ($row['equipment_name'] ?? ''));
        if ($equipment !== '') {
            return $equipment;
        }

        $code = trim((string) ($row['camera_code'] ?? ''));

        return $code !== '' ? $code : '—';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function timelineSectorLabel(array $row): string
    {
        $name = trim((string) ($row['sector_name'] ?? ''));

        return $name !== '' ? $name : '—';
    }

    private function summarizeText(string $text, int $limit = 140): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '') {
            return '—';
        }

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit - 1)) . '…';
    }

    /**
     * @param array<string, mixed> $openShift
     * @param list<array<string, mixed>> $openingChecks
     */
    private function buildOpeningSummary(array $openShift, array $openingChecks): string
    {
        $notes = trim((string) ($openShift['opening_notes'] ?? ''));
        if ($notes !== '') {
            return $this->summarizeText($notes, 180);
        }

        if ($openingChecks === []) {
            return 'Turno iniciado.';
        }

        $issues = [];
        $allOperational = true;

        foreach ($openingChecks as $check) {
            $status = (string) ($check['status'] ?? '');
            if ($status === ShiftEquipmentCheck::STATUS_OPERATIONAL) {
                continue;
            }

            $allOperational = false;
            $equipment = trim((string) ($check['equipment_name'] ?? 'Equipo'));
            $issues[] = $equipment . ': ' . strtolower((string) ($check['status_label'] ?? 'observación'));
        }

        if ($allOperational) {
            return 'Equipos recibidos operativos.';
        }

        return $this->summarizeText('Recepción con observaciones — ' . implode('; ', $issues), 180);
    }

    /**
     * @param array<string, mixed> $shift
     * @param list<array<string, mixed>> $closingChecks
     */
    private function buildClosingSummary(array $shift, array $closingChecks): string
    {
        $notes = trim((string) ($shift['closing_notes'] ?? ''));
        if ($notes !== '') {
            return $this->summarizeText($notes, 180);
        }

        if ($closingChecks === []) {
            return 'Turno finalizado.';
        }

        $issues = [];
        $allOperational = true;

        foreach ($closingChecks as $check) {
            $status = (string) ($check['status'] ?? '');
            if ($status === ShiftEquipmentCheck::STATUS_OPERATIONAL) {
                continue;
            }

            $allOperational = false;
            $equipment = trim((string) ($check['equipment_name'] ?? 'Equipo'));
            $issues[] = $equipment . ': ' . strtolower((string) ($check['status_label'] ?? 'observación'));
        }

        if ($allOperational) {
            return 'Equipos entregados operativos.';
        }

        return $this->summarizeText('Entrega con observaciones — ' . implode('; ', $issues), 180);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildPayload(array $data, int $createdBy): array
    {
        $occurredAt = trim((string) ($data['occurred_at'] ?? ''));
        if ($occurredAt === '') {
            $occurredAt = $this->buildOccurredAt(
                (string) ($data['event_date'] ?? ''),
                isset($data['event_time']) ? (string) $data['event_time'] : null
            );
        }

        $status = trim((string) ($data['status'] ?? LogEntry::STATUS_REGISTERED));
        $shiftId = (int) ($data['cctv_shift_id'] ?? $data['shift_id'] ?? 0);
        $logTypeId = (int) ($data['cctv_log_type_id'] ?? $data['log_type_id'] ?? 0);

        if ($shiftId < 1) {
            throw new HttpException(422, 'Debe asociar la entrada a un turno.');
        }

        if ($logTypeId < 1) {
            throw new HttpException(422, 'Seleccione un tipo de registro.');
        }

        $logTypeSlug = trim((string) ($data['log_type_slug'] ?? ''));
        if ($logTypeSlug === '') {
            $logType = $this->catalogs->findLogTypeById($logTypeId);
            $logTypeSlug = (string) ($logType['slug'] ?? '');
        }

        if ($status === LogEntry::STATUS_REGISTERED && $logTypeSlug === LogType::SLUG_TECHNICAL) {
            $status = TechnicalEntryCatalog::STATUS_DETECTED;
        }

        if (!$this->isValidEntryStatus($status, $logTypeSlug)) {
            throw new HttpException(422, 'El estado de la entrada no es válido.');
        }

        $isTechnical = $logTypeSlug === LogType::SLUG_TECHNICAL;
        $policeArrivedRaw = $data['police_arrived'] ?? null;
        $policeArrived = null;
        $policeArrivalTime = null;

        if (!$isTechnical && $policeArrivedRaw !== null && $policeArrivedRaw !== '') {
            $policeArrived = $this->normalizePoliceArrived($policeArrivedRaw);

            if ($policeArrived === LogEntry::POLICE_ARRIVED_YES) {
                $policeArrivalTime = $this->nullableTime($data['police_arrival_time'] ?? null);

                if ($policeArrivalTime === null) {
                    throw new HttpException(422, 'Indique la hora de llegada de Carabineros.');
                }
            }
        }

        $observations = trim((string) ($data['observations'] ?? ''));
        if ($observations === '') {
            throw new HttpException(422, 'Las observaciones son obligatorias.');
        }

        return [
            'cctv_shift_id' => $shiftId,
            'cctv_log_type_id' => $logTypeId,
            'cctv_incident_type_id' => $isTechnical
                ? null
                : $this->nullableInt($data['cctv_incident_type_id'] ?? $data['incident_type_id'] ?? null),
            'cctv_technical_issue_type_id' => $isTechnical
                ? $this->nullableInt($data['cctv_technical_issue_type_id'] ?? $data['technical_issue_type_id'] ?? null)
                : null,
            'incident_type_other' => $isTechnical
                ? null
                : $this->nullableString($data['incident_type_other'] ?? null),
            'technical_issue_other' => $isTechnical
                ? $this->nullableString($data['technical_issue_other'] ?? null)
                : null,
            'cctv_camera_id' => $this->nullableInt($data['cctv_camera_id'] ?? $data['camera_id'] ?? null),
            'cctv_equipment_id' => $isTechnical
                ? $this->nullableInt($data['cctv_equipment_id'] ?? $data['equipment_id'] ?? null)
                : null,
            'camera_status_applied' => $this->nullableString($data['camera_status_applied'] ?? null),
            'sector_id' => $this->nullableInt($data['sector_id'] ?? null),
            'occurred_at' => $this->normalizeDateTime($occurredAt),
            'observations' => $observations,
            'police_arrived' => $policeArrived,
            'police_arrival_time' => $policeArrivalTime,
            'coordination_notified' => $isTechnical
                ? null
                : $this->nullableBool($data['coordination_notified'] ?? null),
            'status' => $status,
            'related_entity_type' => $this->nullableString($data['related_entity_type'] ?? null),
            'related_entity_id' => $this->nullableInt($data['related_entity_id'] ?? null),
            'created_by' => $createdBy,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param mixed $rawContacts
     * @return list<array<string, mixed>>
     */
    public function normalizeContactsInput(array $data, mixed $rawContacts): array
    {
        if ($this->nullableBool($data['coordination_notified'] ?? null) !== 1) {
            return [];
        }

        if (!is_array($rawContacts)) {
            return [];
        }

        $eventDate = trim((string) ($data['event_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            throw new HttpException(422, 'Indique una fecha válida para los avisos.');
        }

        $normalized = [];

        foreach ($rawContacts as $contact) {
            if (!is_array($contact)) {
                continue;
            }

            $type = trim((string) ($contact['contact_type'] ?? ''));
            $name = trim((string) ($contact['contact_name'] ?? ''));
            $time = trim((string) ($contact['contacted_at'] ?? ''));
            $notes = trim((string) ($contact['notes'] ?? ''));

            if ($type === '' && $time === '' && $name === '' && $notes === '') {
                continue;
            }

            if (!LogContact::isValidType($type)) {
                throw new HttpException(422, 'Seleccione un tipo de contacto válido.');
            }

            if ($time === '') {
                throw new HttpException(422, 'Indique la hora de cada aviso o coordinación.');
            }

            if ($type === LogContact::TYPE_OTHER && $name === '') {
                throw new HttpException(422, 'Especifique el contacto cuando el tipo es Otro.');
            }

            $normalized[] = [
                'contact_type' => $type,
                'contact_name' => $name !== '' ? $name : null,
                'contacted_at' => $this->buildOccurredAt($eventDate, $time),
                'notes' => $notes !== '' ? $notes : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $contacts
     * @return list<array{contact_type: string, contact_name: string, contacted_at: string, notes: string}>
     */
    private function contactsFormRows(array $contacts): array
    {
        if ($contacts === []) {
            return [[
                'contact_type' => '',
                'contact_name' => '',
                'contacted_at' => '',
                'notes' => '',
            ]];
        }

        return array_map(static function (array $contact): array {
            return [
                'contact_type' => (string) ($contact['contact_type'] ?? ''),
                'contact_name' => (string) ($contact['contact_name'] ?? ''),
                'contacted_at' => (string) ($contact['contacted_at_formatted'] ?? ''),
                'notes' => (string) ($contact['notes'] ?? ''),
            ];
        }, $contacts);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentContact(array $row): array
    {
        $type = (string) ($row['contact_type'] ?? '');
        $name = trim((string) ($row['contact_name'] ?? ''));
        $typeLabel = LogContactCatalog::label($type);
        $displayLabel = $type === LogContact::TYPE_OTHER && $name !== '' ? $name : $typeLabel;
        $contactedAt = trim((string) ($row['contacted_at'] ?? ''));
        $timestamp = $contactedAt !== '' ? strtotime($contactedAt) : false;
        $notes = trim((string) ($row['notes'] ?? ''));

        $row['contact_type_label'] = $typeLabel;
        $row['institution_label'] = $typeLabel;
        $row['contact_person_label'] = $name !== '' ? $name : '—';
        $row['contacted_at_formatted'] = $timestamp !== false ? date('H:i', $timestamp) : '—';
        $row['notes_label'] = $notes !== '' ? $notes : '—';
        $row['display'] = $displayLabel . ' — ' . ($timestamp !== false ? date('H:i', $timestamp) : '—');

        return $row;
    }

    /**
     * @param list<array<string, mixed>> $contacts
     */
    private function buildContactsSummary(array $contacts): string
    {
        if ($contacts === []) {
            return '';
        }

        return implode(' · ', array_map(
            static fn (array $contact): string => (string) ($contact['display'] ?? ''),
            $contacts
        ));
    }

    private function statusLabel(string $status, string $logTypeSlug = ''): string
    {
        if ($logTypeSlug === LogType::SLUG_TECHNICAL) {
            return TechnicalEntryCatalog::statusLabel($status);
        }

        return LogEntryCatalog::statusLabel($status);
    }

    /**
     * @return array{value: string, label: string, tone: string}
     */
    private function statusMeta(string $status, string $logTypeSlug = ''): array
    {
        if ($logTypeSlug === LogType::SLUG_TECHNICAL) {
            return TechnicalEntryCatalog::statusMeta($status);
        }

        return LogEntryCatalog::statusMeta($status);
    }

    private function isValidEntryStatus(string $status, string $logTypeSlug): bool
    {
        if ($logTypeSlug === LogType::SLUG_TECHNICAL) {
            return TechnicalEntryCatalog::isValidStatus($status);
        }

        return LogEntry::isValidStatus($status);
    }

    private function normalizePoliceArrived(mixed $value): int
    {
        if (!PoliceArrivalCatalog::isValid($value)) {
            throw new HttpException(422, 'Indique si llegó Carabineros.');
        }

        return (int) $value;
    }

    private function booleanLabel(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return (int) $value === 1 ? 'Sí' : 'No';
    }

    private function formatTime(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '—';
        }

        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) === 1) {
            return substr($value, 0, 5);
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('H:i', $timestamp) : $value;
    }

    private function normalizeDateTime(string $value): string
    {
        $timestamp = strtotime($value);

        if ($timestamp === false) {
            throw new HttpException(422, 'Indique una fecha y hora de evento válidas.');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private function nullableBool(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return in_array($value, [1, '1', true, 'true', 'yes', 'si', 'sí'], true) ? 1 : 0;
    }

    private function nullableTime(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        throw new HttpException(422, 'Indique una hora de llegada policial válida.');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function createOfficeSummary(
        int $shiftId,
        int $operatorId,
        string $logTypeSlug,
        string $summary,
        string $eventDate,
        string $eventTime,
        ?string $relatedEntityType = null,
        ?int $relatedEntityId = null
    ): int {
        $logType = $this->catalogs->findLogTypeBySlug($logTypeSlug);
        if ($logType === null) {
            throw new HttpException(422, 'No está configurado el tipo de registro para la visita.');
        }

        $payload = $this->buildPayload([
            'shift_id' => $shiftId,
            'log_type_id' => (int) ($logType['id'] ?? 0),
            'event_date' => $eventDate,
            'event_time' => $eventTime,
            'observations' => $summary,
            'related_entity_type' => $relatedEntityType,
            'related_entity_id' => $relatedEntityId,
        ], $operatorId);

        $this->assertShiftIsOpen($shiftId, $operatorId);
        $id = $this->entries->create($payload);
        $created = $this->entries->findById($id);
        $presented = $created ? $this->present($created) : $payload;
        $this->cctvAudit->logEntryCreated($id, $presented);

        return $id;
    }

    private function assertShiftIsOpen(int $shiftId, int $operatorId): void
    {
        if ($shiftId < 1) {
            throw new HttpException(422, 'Debe asociar la entrada a un turno.');
        }

        $shift = $this->shifts->findById($shiftId);
        if ($shift === null) {
            throw new HttpException(422, 'El turno asociado ya no existe.');
        }

        if (!Shift::isOpen((string) ($shift['status'] ?? ''))) {
            throw new HttpException(422, 'El turno ya no está abierto. No puede registrar novedades.');
        }

        if ((int) ($shift['operator_id'] ?? 0) !== $operatorId) {
            throw new HttpException(403, 'Solo puede registrar novedades en su turno abierto.');
        }
    }
}
