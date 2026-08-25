<?php

declare(strict_types=1);

namespace App\Services\Guards;

use App\Models\Guards\LogEntry;
use App\Models\Guards\Shift;
use App\Repositories\Guards\CctvLinkRepository;
use App\Repositories\Guards\LogEntryRepository;
use App\Repositories\Guards\LogTypeRepository;
use App\Repositories\Guards\ShiftRepository;
use App\Repositories\SectorRepository;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;

final class LogEntryService
{
    public function __construct(
        private readonly LogEntryRepository $entries = new LogEntryRepository(),
        private readonly LogTypeRepository $types = new LogTypeRepository(),
        private readonly ShiftRepository $shifts = new ShiftRepository(),
        private readonly SectorRepository $sectors = new SectorRepository(),
        private readonly CctvLinkService $cctvLink = new CctvLinkService(),
        private readonly CctvLinkRepository $cctvLinks = new CctvLinkRepository(),
        private readonly GuardsAuditService $audit = new GuardsAuditService()
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
            'sector_id' => '',
            'observations' => '',
            'status' => LogEntry::STATUS_REGISTERED,
            'notify_cctv' => '',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{cctv_linked: bool, cctv_pending: bool}
     */
    public function createForOpenShift(array $data, ?int $guardId = null): array
    {
        $guardId ??= Auth::id();
        if ($guardId === null || $guardId < 1) {
            throw new HttpException(403, 'No hay un guardia autenticado.');
        }

        $openShift = $this->shifts->findOpenByGuard($guardId);
        if ($openShift === null) {
            throw new HttpException(422, 'Debe iniciar un turno antes de registrar novedades.');
        }

        $payload = $this->buildPayload($data, $guardId, (int) $openShift['id']);

        return Database::transaction(function () use ($payload, $data, $openShift, $guardId): array {
            $id = $this->entries->create($payload);
            $created = $this->entries->findById($id);
            $presented = $created ? $this->present($created) : $payload;
            $this->audit->logEntryCreated($id, $presented);

            $cctvLinked = false;
            $cctvPending = false;

            if (!empty($data['notify_cctv']) && hasPermission('guards.log.link_cctv')) {
                $type = $this->types->findById((int) $payload['guards_log_type_id']);
                $sector = !empty($payload['sector_id']) ? $this->sectors->findById((int) $payload['sector_id']) : null;
                $guardName = trim((string) ($openShift['guard_name'] ?? ''));
                if ($guardName === '') {
                    $user = Auth::user();
                    $guardName = trim((string) ($user['name'] ?? ''));
                }

                $cctvId = $this->cctvLink->notifyMonitoring(
                    $id,
                    (string) ($type['name'] ?? 'Novedad'),
                    (string) $payload['observations'],
                    (string) $payload['occurred_at'],
                    isset($sector['name']) ? (string) $sector['name'] : null,
                    $guardName !== '' ? $guardName : null
                );

                if ($cctvId !== null) {
                    $this->entries->attachCctvLogEntry($id, $cctvId);
                    $cctvLinked = true;
                } else {
                    $cctvPending = true;
                }
            }

            return ['cctv_linked' => $cctvLinked, 'cctv_pending' => $cctvPending];
        });
    }

    public function find(int $id): array
    {
        $record = $this->entries->findById($id);

        if ($record === null) {
            throw new HttpException(404, 'La novedad no existe.');
        }

        return $this->presentDetail($record);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByShift(int $shiftId): array
    {
        return array_map([$this, 'present'], $this->entries->listByShift($shiftId));
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function searchHistory(array $filters, int $page, int $perPage = 20): array
    {
        $page = max(1, $page);
        $filters = $this->normalizeHistoryFilters($filters);
        $result = $this->entries->paginate($filters, $page, $perPage);
        $pages = max(1, (int) ceil($result['total'] / $perPage));

        return [
            'data' => array_map([$this, 'present'], $result['data']),
            'total' => $result['total'],
            'page' => min($page, $pages),
            'pages' => $pages,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function logTypeOptions(): array
    {
        return $this->types->listActive();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildPayload(array $data, int $guardId, int $shiftId): array
    {
        $typeId = (int) ($data['log_type_id'] ?? 0);
        $type = $typeId > 0 ? $this->types->findById($typeId) : null;
        if ($type === null) {
            throw new HttpException(422, 'Seleccione un tipo de novedad válido.');
        }

        $observations = trim((string) ($data['observations'] ?? ''));
        if ($observations === '') {
            throw new HttpException(422, 'Describa la novedad registrada.');
        }

        $sectorId = (int) ($data['sector_id'] ?? 0);
        if ($sectorId > 0 && $this->sectors->findById($sectorId) === null) {
            throw new HttpException(422, 'El sector seleccionado no es válido.');
        }

        $status = trim((string) ($data['status'] ?? LogEntry::STATUS_REGISTERED));
        if (!LogEntry::isValidStatus($status)) {
            $status = LogEntry::STATUS_REGISTERED;
        }

        $occurredAt = $this->normalizeOccurredAt(
            (string) ($data['event_date'] ?? date('Y-m-d')),
            (string) ($data['event_time'] ?? date('H:i'))
        );

        return [
            'guards_shift_id' => $shiftId,
            'guards_log_type_id' => $typeId,
            'sector_id' => $sectorId > 0 ? $sectorId : null,
            'occurred_at' => $occurredAt,
            'observations' => $observations,
            'status' => $status,
            'cctv_log_entry_id' => null,
            'created_by' => $guardId,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $row['type_label'] = trim((string) ($row['log_type_name'] ?? '')) ?: '—';
        $row['type_tone'] = trim((string) ($row['log_type_tone'] ?? '')) ?: 'other';
        $row['sector_label'] = trim((string) ($row['sector_name'] ?? '')) ?: '—';
        $row['guard_label'] = trim((string) ($row['guard_name'] ?? '')) ?: '—';
        $row['status_label'] = LogEntryCatalog::statusLabel((string) ($row['status'] ?? ''));
        $row['status_tone'] = LogEntryCatalog::statusTone((string) ($row['status'] ?? ''));
        $row['occurred_at_formatted'] = $this->formatDateTime($row['occurred_at'] ?? null);
        $row['time_label'] = $this->formatTime($row['occurred_at'] ?? null);
        $row['summary'] = $this->excerpt((string) ($row['observations'] ?? ''));
        $row['has_cctv_link'] = !empty($row['cctv_log_entry_id']);
        $row['cctv_log_url'] = !empty($row['cctv_log_entry_id']) && hasPermission('cctv.log.view')
            ? url('/cctv/log/' . (int) $row['cctv_log_entry_id'])
            : null;

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentDetail(array $row): array
    {
        $presented = $this->present($row);
        $viewerId = Auth::id();

        if (!hasPermission('guards.log.view') && !hasPermission('guards.shifts.view_all')) {
            if ($viewerId === null || (int) ($row['created_by'] ?? 0) !== $viewerId) {
                throw new HttpException(403, 'No puede consultar novedades de otros guardias.');
            }
        }

        if (empty($presented['cctv_log_entry_id'])) {
            $linked = $this->cctvLinks->findCctvEntryByGuardEntry((int) ($row['id'] ?? 0));
            if ($linked !== null) {
                $presented['cctv_log_entry_id'] = (int) $linked['id'];
                $presented['has_cctv_link'] = true;
                $presented['cctv_log_url'] = hasPermission('cctv.log.view')
                    ? url('/cctv/log/' . (int) $linked['id'])
                    : null;
            }
        }

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

    private function normalizeOccurredAt(string $date, string $time): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new HttpException(422, 'Indique una fecha válida.');
        }

        $time = trim($time);
        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            $time .= ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time) !== 1) {
            throw new HttpException(422, 'Indique una hora válida.');
        }

        return $date . ' ' . $time;
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

    private function formatTime(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '—';
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('H:i', $timestamp) : $value;
    }

    private function excerpt(string $text, int $max = 140): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        if ($text === '') {
            return '—';
        }

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }
}
