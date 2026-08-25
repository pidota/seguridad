<?php

declare(strict_types=1);

namespace App\Services\Guards;

use App\Models\Cctv\Shift as CctvShift;
use App\Models\Guards\LogEntry;
use App\Repositories\Cctv\ShiftRepository as CctvShiftRepository;
use App\Repositories\Guards\CctvLinkRepository;
use App\Services\Cctv\LogEntryService as CctvLogEntryService;

final class CctvLinkService
{
    public const CCTV_LOG_TYPE_SLUG = 'comunicacion_coordinacion';

    public function __construct(
        private readonly CctvShiftRepository $cctvShifts = new CctvShiftRepository(),
        private readonly CctvLogEntryService $cctvLogEntries = new CctvLogEntryService(),
        private readonly CctvLinkRepository $links = new CctvLinkRepository()
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function incomingCoordinations(int $limit = 10): array
    {
        return array_map([$this, 'presentIncoming'], $this->links->listIncomingCoordinations($limit));
    }

    public function incomingCount(): int
    {
        return $this->links->countIncomingCoordinations();
    }

    /**
     * Registra la novedad de guardias en la bitácora del turno CCTV activo más reciente.
     *
     * @return int|null ID de la entrada CCTV creada, o null si no hay turno abierto.
     */
    public function notifyMonitoring(
        int $guardLogEntryId,
        string $typeLabel,
        string $observations,
        string $occurredAt,
        ?string $sectorName,
        ?string $guardName
    ): ?int {
        if (!hasPermission('guards.log.link_cctv')) {
            return null;
        }

        $openShift = $this->cctvShifts->findLatestOpen();
        if ($openShift === null || !CctvShift::isOpen((string) ($openShift['status'] ?? ''))) {
            return null;
        }

        $shiftId = (int) ($openShift['id'] ?? 0);
        $operatorId = (int) ($openShift['operator_id'] ?? 0);
        if ($shiftId < 1 || $operatorId < 1) {
            return null;
        }

        $sectorPart = $sectorName !== null && $sectorName !== '' ? ' Sector: ' . $sectorName . '.' : '';
        $guardPart = $guardName !== null && $guardName !== '' ? ' Guardia: ' . $guardName . '.' : '';
        $summary = sprintf(
            'Novedad desde Guardias Municipales (%s).%s%s %s',
            $typeLabel,
            $guardPart,
            $sectorPart,
            $this->excerpt($observations)
        );

        $timestamp = strtotime($occurredAt);
        $eventDate = $timestamp !== false ? date('Y-m-d', $timestamp) : date('Y-m-d');
        $eventTime = $timestamp !== false ? date('H:i', $timestamp) : date('H:i');

        return $this->cctvLogEntries->createOfficeSummary(
            $shiftId,
            $operatorId,
            self::CCTV_LOG_TYPE_SLUG,
            trim($summary),
            $eventDate,
            $eventTime,
            LogEntry::RELATED_ENTITY_TYPE,
            $guardLogEntryId
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function presentIncoming(array $row): array
    {
        $occurredAt = trim((string) ($row['occurred_at'] ?? ''));
        $timestamp = $occurredAt !== '' ? strtotime($occurredAt) : false;

        $row['occurred_at_formatted'] = $timestamp !== false ? date('d-m-Y H:i', $timestamp) : '—';
        $row['summary'] = $this->excerpt((string) ($row['observations'] ?? ''));
        $row['sector_label'] = trim((string) ($row['sector_name'] ?? '')) ?: '—';
        $row['cctv_log_url'] = hasPermission('cctv.log.view') && !empty($row['cctv_log_entry_id'])
            ? url('/cctv/log/' . (int) $row['cctv_log_entry_id'])
            : null;

        return $row;
    }

    private function excerpt(string $text, int $max = 180): string
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
