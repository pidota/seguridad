<?php

declare(strict_types=1);

namespace App\Services\Guards;

use App\Exceptions\Guards\OpenShiftAlreadyExistsException;
use App\Models\Guards\Shift;
use App\Repositories\Guards\LogEntryRepository;
use App\Repositories\Guards\ShiftRepository;
use Core\Auth;
use Core\Exceptions\HttpException;

final class ShiftService
{
    public function __construct(
        private readonly ShiftRepository $shifts = new ShiftRepository(),
        private readonly LogEntryRepository $logEntries = new LogEntryRepository(),
        private readonly GuardsAuditService $audit = new GuardsAuditService()
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

    public function findOpenForGuard(int $guardId): ?array
    {
        $record = $this->shifts->findOpenByGuard($guardId);

        return $record ? $this->present($record) : null;
    }

    /**
     * @return array{
     *     open_shift: array<string, mixed>|null,
     *     can_start: bool,
     *     open_shifts_count: int
     * }
     */
    public function dashboardForGuard(?int $guardId = null): array
    {
        $guardId ??= Auth::id();
        if ($guardId === null || $guardId < 1) {
            return [
                'open_shift' => null,
                'can_start' => false,
                'open_shifts_count' => 0,
            ];
        }

        $open = $this->findOpenForGuard($guardId);

        return [
            'open_shift' => $open,
            'can_start' => $open === null && hasPermission('guards.shifts.create'),
            'open_shifts_count' => $this->shifts->countOpen(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function open(array $data = [], ?int $guardId = null): int
    {
        $guardId ??= Auth::id();
        if ($guardId === null || $guardId < 1) {
            throw new HttpException(403, 'No hay un guardia autenticado.');
        }

        $existing = $this->shifts->findOpenByGuard($guardId);
        if ($existing !== null) {
            throw new OpenShiftAlreadyExistsException((int) $existing['id']);
        }

        $startedAt = date('Y-m-d H:i:s');
        $shiftDate = trim((string) ($data['shift_date'] ?? substr($startedAt, 0, 10)));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $shiftDate)) {
            throw new HttpException(422, 'Indique una fecha de turno válida.');
        }

        $payload = [
            'guard_id' => $guardId,
            'shift_date' => $shiftDate,
            'status' => Shift::STATUS_OPEN,
            'started_at' => $startedAt,
            'ended_at' => null,
            'opening_notes' => $this->nullable($data['opening_notes'] ?? null),
            'closing_notes' => null,
        ];

        $id = $this->shifts->create($payload);
        $created = $this->shifts->findById($id);
        $this->audit->shiftOpened($id, $created ? $this->present($created) : $payload);

        return $id;
    }

    public function close(int $id, ?string $closingNotes = null): void
    {
        $current = $this->find($id);

        if (Shift::isClosed((string) ($current['status'] ?? ''))) {
            throw new HttpException(422, 'El turno ya está cerrado.');
        }

        $guardId = Auth::id();
        if ($guardId !== null && (int) ($current['guard_id'] ?? 0) !== $guardId && !hasPermission('guards.shifts.view_all')) {
            throw new HttpException(403, 'Solo puede finalizar su propio turno.');
        }

        $endedAt = date('Y-m-d H:i:s');
        $notes = $this->nullable($closingNotes);

        $this->shifts->close($id, $endedAt, $notes);
        $updated = $this->shifts->findById($id);
        $presented = $updated ? $this->present($updated) : array_merge($current, [
            'status' => Shift::STATUS_CLOSED,
            'ended_at' => $endedAt,
            'closing_notes' => $notes,
        ]);

        $this->audit->shiftClosed($id, $current, $presented);
    }

    /**
     * @param array<string, mixed> $filters
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
    public function guardOptions(): array
    {
        return $this->shifts->guardOptions();
    }

    /**
     * @return array{
     *     shift: array<string, mixed>,
     *     stats: array<string, int>,
     *     log_entries: list<array<string, mixed>>
     * }
     */
    public function detailForView(int $id, ?int $viewerId = null): array
    {
        $shift = $this->find($id);
        $viewerId ??= Auth::id();

        if (!hasPermission('guards.shifts.view_all')) {
            if ($viewerId === null || (int) ($shift['guard_id'] ?? 0) !== $viewerId) {
                throw new HttpException(403, 'No puede consultar turnos de otros guardias.');
            }
        }

        $logService = new LogEntryService();

        return [
            'shift' => $shift,
            'stats' => $this->logEntries->shiftStats($id),
            'log_entries' => $logService->listByShift($id),
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
        $row['guard_label'] = trim((string) ($row['guard_name'] ?? '')) ?: '—';
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
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function normalizeHistoryFilters(array $filters): array
    {
        if (hasPermission('guards.shifts.view_all')) {
            return $filters;
        }

        $viewerId = Auth::id();
        if ($viewerId !== null && $viewerId > 0) {
            $filters['guard_id'] = (string) $viewerId;
        }

        return $filters;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Shift::STATUS_OPEN => 'Abierto',
            Shift::STATUS_CLOSED => 'Cerrado',
            default => $status !== '' ? $status : '—',
        };
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

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
