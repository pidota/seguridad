<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Exceptions\Cctv\OpenShiftAlreadyExistsException;
use App\Models\Cctv\LogType;
use App\Models\Cctv\Shift;
use App\Models\Cctv\ShiftEquipmentCheck;
use App\Repositories\Cctv\EquipmentRepository;
use App\Repositories\Cctv\LogEntryRepository;
use App\Repositories\Cctv\ShiftEquipmentCheckRepository;
use App\Repositories\Cctv\ShiftRepository;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;

final class ShiftService
{
    public function __construct(
        private readonly ShiftRepository $shifts = new ShiftRepository(),
        private readonly ShiftEquipmentCheckRepository $equipmentChecks = new ShiftEquipmentCheckRepository(),
        private readonly EquipmentRepository $equipment = new EquipmentRepository(),
        private readonly LogEntryRepository $logEntries = new LogEntryRepository(),
        private readonly CctvAuditService $cctvAudit = new CctvAuditService(),
        private readonly ClosedShiftPolicy $closedShiftPolicy = new ClosedShiftPolicy()
    ) {
    }

    public function find(int $id): array
    {
        $record = $this->shifts->findById($id);

        if ($record === null) {
            throw new HttpException(404, 'El turno no existe.');
        }

        return $this->present($record);
    }

    public function findOpenForOperator(int $operatorId): ?array
    {
        $record = $this->shifts->findOpenByOperator($operatorId);

        return $record ? $this->present($record) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLastClosedForOperator(int $operatorId): ?array
    {
        $record = $this->shifts->findLastClosedByOperator($operatorId);

        return $record ? $this->present($record) : null;
    }

    /**
     * @return array{
     *     open_shift: array<string, mixed>|null,
     *     last_shift: array<string, mixed>|null,
     *     can_start: bool
     * }
     */
    public function dashboardForOperator(?int $operatorId = null): array
    {
        $operatorId ??= Auth::id();
        if ($operatorId === null || $operatorId < 1) {
            return [
                'open_shift' => null,
                'last_shift' => null,
                'opening_checks' => [],
                'can_start' => false,
            ];
        }

        $open = $this->findOpenForOperator($operatorId);
        $last = $this->findLastClosedForOperator($operatorId);
        $openingChecks = $open ? $this->listEquipmentChecks((int) $open['id'], ShiftEquipmentCheck::PHASE_OPENING) : [];

        return [
            'open_shift' => $open,
            'last_shift' => $last,
            'opening_checks' => $openingChecks,
            'can_start' => $open === null && hasPermission('cctv.shifts.create'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listEquipmentChecks(int $shiftId, string $phase): array
    {
        if (!ShiftEquipmentCheck::isValidPhase($phase)) {
            throw new HttpException(422, 'La fase de revisión de equipos no es válida.');
        }

        return array_map(
            [$this, 'presentEquipmentCheck'],
            $this->equipmentChecks->listByShiftAndPhase($shiftId, $phase)
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function openWithReception(array $data, ?int $operatorId = null): int
    {
        $operatorId ??= Auth::id();
        if ($operatorId === null || $operatorId < 1) {
            throw new HttpException(403, 'No hay un operador autenticado.');
        }

        $existing = $this->shifts->findOpenByOperator($operatorId);
        if ($existing !== null) {
            throw new OpenShiftAlreadyExistsException((int) $existing['id']);
        }

        $equipmentItems = $this->equipment->listActive();
        $checks = $this->normalizeEquipmentInput($data['equipment'] ?? [], $equipmentItems);
        $startedAt = date('Y-m-d H:i:s');
        $shiftDate = substr($startedAt, 0, 10);
        $openingNotes = $this->nullable($data['opening_notes'] ?? null);

        return Database::transaction(function () use ($operatorId, $checks, $startedAt, $shiftDate, $openingNotes): int {
            $payload = [
                'operator_id' => $operatorId,
                'shift_date' => $shiftDate,
                'status' => Shift::STATUS_OPEN,
                'started_at' => $startedAt,
                'ended_at' => null,
                'opening_notes' => $openingNotes,
                'closing_notes' => null,
            ];

            $id = $this->shifts->create($payload);
            $savedChecks = $this->persistEquipmentChecks(
                $id,
                $operatorId,
                $checks,
                ShiftEquipmentCheck::PHASE_OPENING,
                $startedAt
            );
            $created = $this->shifts->findById($id);
            $presented = $created ? $this->present($created) : $payload;
            $this->cctvAudit->shiftOpened(
                $id,
                $presented,
                array_map(
                    fn (array $check): array => $this->presentEquipmentCheck($check),
                    $savedChecks
                )
            );

            return $id;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForOperator(int $operatorId, int $limit = 50): array
    {
        return array_map(
            [$this, 'present'],
            $this->shifts->listByOperator($operatorId, $limit)
        );
    }

    /**
     * @param array{
     *     operator_id?: int|string|null,
     *     date_from?: string,
     *     date_to?: string,
     *     status?: string
     * } $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function searchHistory(array $filters, int $page, int $perPage = 15): array
    {
        $page = max(1, $page);
        $filters = $this->normalizeHistoryFilters($filters);
        $result = $this->shifts->paginate($filters, $page, $perPage);
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
        return $this->shifts->operatorOptions();
    }

    /**
     * @return array{
     *     open_shift: array<string, mixed>|null,
     *     open_shifts_count: int,
     *     today: string,
     *     month_start: string,
     *     month_end: string,
     *     month_label: string,
     *     today_stats: array<string, int>,
     *     month_stats: array<string, int>,
     *     incidents_by_sector: list<array{label: string, count: int, url: string}>,
     *     incidents_by_type: list<array{label: string, count: int, url: string}>,
     *     shifts_activity: list<array<string, mixed>>,
     *     recent_entries: list<array<string, mixed>>
     * }
     */
    public function supervisionDashboard(int $camerasWithIssues = 0, int $recentLimit = 8): array
    {
        $logService = new LogEntryService();
        $statsService = new StatisticsService();
        $panel = $statsService->supervisionPanel($camerasWithIssues);
        $openRaw = $this->shifts->findLatestOpen();

        return array_merge($panel, [
            'open_shift' => $openRaw ? $this->present($openRaw) : null,
            'open_shifts_count' => $this->shifts->countOpen(),
            'recent_entries' => $logService->recentForSupervision($recentLimit),
        ]);
    }

    /**
     * @return array{data: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function paginateForOperator(int $operatorId, int $page, int $perPage = 15): array
    {
        $page = max(1, $page);
        $result = $this->shifts->paginateByOperator($operatorId, $page, $perPage);
        $pages = max(1, (int) ceil($result['total'] / $perPage));

        return [
            'data' => array_map([$this, 'present'], $result['data']),
            'total' => $result['total'],
            'page' => min($page, $pages),
            'pages' => $pages,
        ];
    }

    /**
     * Abre un turno para el operador autenticado (o el indicado en pruebas internas).
     *
     * @param array<string, mixed> $data Solo admite shift_date u opening_notes para uso interno.
     */
    public function open(array $data = [], ?int $operatorId = null): int
    {
        $operatorId ??= Auth::id();
        if ($operatorId === null || $operatorId < 1) {
            throw new HttpException(403, 'No hay un operador autenticado.');
        }

        $existing = $this->shifts->findOpenByOperator($operatorId);
        if ($existing !== null) {
            throw new OpenShiftAlreadyExistsException((int) $existing['id']);
        }

        $startedAt = date('Y-m-d H:i:s');
        $shiftDate = trim((string) ($data['shift_date'] ?? substr($startedAt, 0, 10)));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $shiftDate)) {
            throw new HttpException(422, 'Indique una fecha de turno válida.');
        }

        $payload = [
            'operator_id' => $operatorId,
            'shift_date' => $shiftDate,
            'status' => Shift::STATUS_OPEN,
            'started_at' => $startedAt,
            'ended_at' => null,
            'opening_notes' => $this->nullable($data['opening_notes'] ?? null),
            'closing_notes' => null,
        ];

        $id = $this->shifts->create($payload);
        $created = $this->shifts->findById($id);

        $this->cctvAudit->shiftOpened(
            $id,
            $created ? $this->present($created) : $payload
        );

        return $id;
    }

    public function close(int $id, ?string $closingNotes = null, ?string $endedAt = null): void
    {
        $current = $this->find($id);

        if (Shift::isClosed((string) ($current['status'] ?? ''))) {
            throw new HttpException(422, 'El turno ya está cerrado.');
        }

        $endedAt = $this->normalizeDateTime($endedAt ?? date('Y-m-d H:i:s'));
        $notes = $this->nullable($closingNotes);

        $this->shifts->close($id, $endedAt, $notes);
        $updated = $this->shifts->findById($id);
        $presented = $updated ? $this->present($updated) : array_merge($current, [
            'status' => Shift::STATUS_CLOSED,
            'ended_at' => $endedAt,
            'closing_notes' => $notes,
        ]);

        $this->cctvAudit->shiftClosed(
            $id,
            $current,
            $presented
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function closeWithDelivery(int $id, array $data, ?int $operatorId = null): void
    {
        $operatorId ??= Auth::id();
        if ($operatorId === null || $operatorId < 1) {
            throw new HttpException(403, 'No hay un operador autenticado.');
        }

        $current = $this->find($id);

        if ((int) ($current['operator_id'] ?? 0) !== $operatorId) {
            throw new HttpException(403, 'Solo puede finalizar su propio turno.');
        }

        if (Shift::isClosed((string) ($current['status'] ?? ''))) {
            throw new HttpException(422, 'El turno ya está cerrado.');
        }

        $equipmentItems = $this->equipment->listActive();
        $checks = $this->normalizeEquipmentInput($data['equipment'] ?? [], $equipmentItems);
        $endedAt = date('Y-m-d H:i:s');
        $closingNotes = $this->nullable($data['closing_notes'] ?? null);

        Database::transaction(function () use ($id, $operatorId, $checks, $endedAt, $closingNotes, $current): void {
            $savedChecks = $this->persistEquipmentChecks(
                $id,
                $operatorId,
                $checks,
                ShiftEquipmentCheck::PHASE_CLOSING,
                $endedAt
            );

            $this->shifts->close($id, $endedAt, $closingNotes);
            $updated = $this->shifts->findById($id);
            $presented = $updated ? $this->present($updated) : array_merge($current, [
                'status' => Shift::STATUS_CLOSED,
                'ended_at' => $endedAt,
                'closing_notes' => $closingNotes,
            ]);

            $this->cctvAudit->shiftClosed(
                $id,
                $current,
                $presented,
                array_map(
                    fn (array $check): array => $this->presentEquipmentCheck($check),
                    $savedChecks
                )
            );
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $current = $this->find($id);
        $wasClosed = Shift::isClosed((string) ($current['status'] ?? ''));

        if ($wasClosed) {
            $this->closedShiftPolicy->assertShiftMutation($current);
        }

        $payload = [
            'shift_date' => trim((string) ($data['shift_date'] ?? $current['shift_date'] ?? '')),
            'status' => (string) ($current['status'] ?? Shift::STATUS_OPEN),
            'started_at' => (string) ($current['started_at'] ?? ''),
            'ended_at' => $current['ended_at'] ?? null,
            'opening_notes' => array_key_exists('opening_notes', $data)
                ? $this->nullable($data['opening_notes'])
                : ($current['opening_notes'] ?? null),
            'closing_notes' => array_key_exists('closing_notes', $data)
                ? $this->nullable($data['closing_notes'])
                : ($current['closing_notes'] ?? null),
        ];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['shift_date'])) {
            throw new HttpException(422, 'Indique una fecha de turno válida.');
        }

        $this->shifts->update($id, $payload);
        $updated = $this->shifts->findById($id);
        $presented = $updated ? $this->present($updated) : array_merge($current, $payload);

        $this->cctvAudit->shiftUpdated(
            $id,
            $current,
            $presented,
            $wasClosed
        );
    }

    /**
     * @param array<string, mixed> $openShift
     * @return array{
     *     started_time: string,
     *     ending_time: string,
     *     total_entries: int,
     *     incidents: int,
     *     general_entries: int,
     *     technical_issues: int,
     *     coordinations: int
     * }
     */
    public function closingSummary(array $openShift): array
    {
        $shiftId = (int) ($openShift['id'] ?? 0);
        $stats = $shiftId > 0 ? $this->logEntries->shiftStats($shiftId) : [
            'total_entries' => 0,
            'incidents' => 0,
            'general_entries' => 0,
            'technical_issues' => 0,
            'coordinations' => 0,
        ];

        return [
            'started_time' => (string) ($openShift['started_time_formatted'] ?? '—'),
            'ending_time' => date('H:i'),
            'total_entries' => (int) ($stats['total_entries'] ?? 0),
            'incidents' => (int) ($stats['incidents'] ?? 0),
            'general_entries' => (int) ($stats['general_entries'] ?? 0),
            'technical_issues' => (int) ($stats['technical_issues'] ?? 0),
            'coordinations' => (int) ($stats['coordinations'] ?? 0),
        ];
    }

    /**
     * @return array{
     *     shift: array<string, mixed>,
     *     stats: array<string, int>,
     *     opening_checks: list<array<string, mixed>>,
     *     closing_checks: list<array<string, mixed>>,
     *     timeline: array{items: list<array<string, mixed>>, order: string, total: int},
     *     incidents: list<array<string, mixed>>,
     *     technical_issues: list<array<string, mixed>>,
     *     coordinations: list<array<string, mixed>>
     * }
     */
    public function detailForView(int $id, ?int $viewerId = null, string $logOrder = 'asc'): array
    {
        $shift = $this->find($id);
        $viewerId ??= Auth::id();

        if (!hasPermission('cctv.shifts.view_all')) {
            if ($viewerId === null || (int) ($shift['operator_id'] ?? 0) !== $viewerId) {
                throw new HttpException(403, 'No puede consultar turnos de otros operadores.');
            }
        }

        $openingChecks = $this->listEquipmentChecks($id, ShiftEquipmentCheck::PHASE_OPENING);
        $closingChecks = Shift::isClosed($shift['status'] ?? null)
            ? $this->listEquipmentChecks($id, ShiftEquipmentCheck::PHASE_CLOSING)
            : [];

        $stats = $this->logEntries->shiftStats($id);
        $logService = new LogEntryService();
        $timeline = $logService->shiftTimeline($shift, $openingChecks, $logOrder, $closingChecks);

        $logItems = array_values(array_filter(
            $timeline['items'],
            static fn (array $item): bool => ($item['kind'] ?? '') === 'log_entry'
        ));

        $incidents = array_values(array_filter(
            $logItems,
            static fn (array $item): bool => ($item['log_type_slug'] ?? '') === LogType::SLUG_INCIDENT
        ));
        $technicalIssues = array_values(array_filter(
            $logItems,
            static fn (array $item): bool => ($item['log_type_slug'] ?? '') === LogType::SLUG_TECHNICAL
        ));
        $coordinations = array_values(array_filter(
            $logItems,
            static fn (array $item): bool => !empty($item['is_coordination'])
        ));

        return [
            'shift' => $shift,
            'stats' => $stats,
            'opening_checks' => $openingChecks,
            'closing_checks' => $closingChecks,
            'timeline' => $timeline,
            'incidents' => $incidents,
            'technical_issues' => $technicalIssues,
            'coordinations' => $coordinations,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $row['status_label'] = $this->statusLabel((string) ($row['status'] ?? ''));
        $row['is_open'] = Shift::isOpen($row['status'] ?? null);
        $row['is_closed'] = Shift::isClosed($row['status'] ?? null);
        $row['started_at_formatted'] = $this->formatDateTime($row['started_at'] ?? null);
        $row['ended_at_formatted'] = $this->formatDateTime($row['ended_at'] ?? null);
        $row['shift_date_formatted'] = $this->formatDate($row['shift_date'] ?? null);
        $row['operator_label'] = trim((string) ($row['operator_name'] ?? '')) ?: '—';
        $row['started_time_formatted'] = $this->formatTime($row['started_at'] ?? null);
        $row['ended_time_formatted'] = $this->formatTime($row['ended_at'] ?? null);
        $row['status_tone'] = $row['is_open'] ? 'success' : 'other';

        $startedAt = trim((string) ($row['started_at'] ?? ''));
        $startedTimestamp = $startedAt !== '' ? strtotime($startedAt) : false;

        if ($row['is_open'] && $startedTimestamp !== false) {
            $seconds = max(0, time() - $startedTimestamp);
            $row['duration_seconds'] = $seconds;
            $row['duration_label'] = $this->formatDuration($seconds);
        } elseif ($row['is_closed'] && $startedTimestamp !== false) {
            $endedAt = trim((string) ($row['ended_at'] ?? ''));
            $endedTimestamp = $endedAt !== '' ? strtotime($endedAt) : false;
            if ($endedTimestamp !== false) {
                $seconds = max(0, $endedTimestamp - $startedTimestamp);
                $row['duration_seconds'] = $seconds;
                $row['duration_label'] = $this->formatDuration($seconds);
            } else {
                $row['duration_seconds'] = 0;
                $row['duration_label'] = '—';
            }
        } else {
            $row['duration_seconds'] = 0;
            $row['duration_label'] = '—';
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function presentHistory(array $row): array
    {
        $presented = $this->present($row);
        $presented['total_entries'] = (int) ($row['total_entries'] ?? 0);
        $presented['incidents'] = (int) ($row['incidents'] ?? 0);

        return $presented;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function presentEquipmentCheck(array $row): array
    {
        $status = (string) ($row['status'] ?? '');
        $meta = EquipmentCheckCatalog::statusMeta($status);
        $row['status_label'] = $meta['label'];
        $row['status_tone'] = $meta['tone'];
        $row['phase_label'] = EquipmentCheckCatalog::phaseLabel((string) ($row['check_phase'] ?? ''));
        $row['checked_at_formatted'] = $this->formatDateTime($row['checked_at'] ?? null);

        return $row;
    }

    /**
     * @param array<string, mixed> $equipmentInput
     * @param list<array<string, mixed>> $equipmentItems
     * @return list<array{cctv_equipment_id: int, status: string, observations: ?string}>
     */
    private function normalizeEquipmentInput(array $equipmentInput, array $equipmentItems): array
    {
        $checks = [];

        foreach ($equipmentItems as $item) {
            $equipmentId = (int) ($item['id'] ?? 0);
            if ($equipmentId < 1) {
                continue;
            }

            $entry = is_array($equipmentInput[$equipmentId] ?? null)
                ? $equipmentInput[$equipmentId]
                : (is_array($equipmentInput[(string) $equipmentId] ?? null) ? $equipmentInput[(string) $equipmentId] : []);

            $checks[] = [
                'cctv_equipment_id' => $equipmentId,
                'status' => trim((string) ($entry['status'] ?? '')),
                'observations' => $this->nullable($entry['observations'] ?? null),
            ];
        }

        return $checks;
    }

    /**
     * @param list<array{cctv_equipment_id: int, status: string, observations: ?string}> $checks
     * @return list<array<string, mixed>>
     */
    private function persistEquipmentChecks(
        int $shiftId,
        int $operatorId,
        array $checks,
        string $phase,
        string $checkedAt
    ): array {
        foreach ($checks as $check) {
            $this->equipmentChecks->create([
                'cctv_shift_id' => $shiftId,
                'cctv_equipment_id' => $check['cctv_equipment_id'],
                'check_phase' => $phase,
                'status' => $check['status'],
                'observations' => $check['observations'],
                'checked_at' => $checkedAt,
                'checked_by' => $operatorId,
            ]);
        }

        return $this->listEquipmentChecks($shiftId, $phase);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Shift::STATUS_OPEN => 'Abierto',
            Shift::STATUS_CLOSED => 'Cerrado',
            default => $status !== '' ? $status : '—',
        };
    }

    private function normalizeDateTime(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return date('Y-m-d H:i:s');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value . ' 00:00:00';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
            return $value . ':00';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        throw new HttpException(422, 'Indique una fecha y hora válidas.');
    }

    private function formatDateTime(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '—';
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('d-m-Y H:i', $timestamp) : $value;
    }

    private function formatDate(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '—';
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('d-m-Y', $timestamp) : $value;
    }

    private function formatTime(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '—';
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('H:i', $timestamp) : $value;
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return '0 min';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours < 1) {
            return $minutes . ' min';
        }

        if ($minutes === 0) {
            return $hours . ' h';
        }

        return $hours . ' h ' . $minutes . ' min';
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function normalizeHistoryFilters(array $filters): array
    {
        if (hasPermission('cctv.shifts.view_all')) {
            return $filters;
        }

        $viewerId = Auth::id();
        if ($viewerId !== null && $viewerId > 0) {
            $filters['operator_id'] = (string) $viewerId;
        }

        return $filters;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
