<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Models\Cctv\LogContact;
use App\Models\Cctv\LogEntry;
use App\Repositories\Cctv\LogEntryRepository;
use App\Repositories\Cctv\ShiftRepository;

final class StatisticsService
{
    public function __construct(
        private readonly LogEntryRepository $entries = new LogEntryRepository(),
        private readonly ShiftRepository $shifts = new ShiftRepository(),
        private readonly PoliceResponseTimeCalculator $policeResponseTime = new PoliceResponseTimeCalculator()
    ) {
    }

    /**
     * Indicadores operacionales para el panel de supervisión CCTV.
     *
     * @return array{
     *     today: string,
     *     month_start: string,
     *     month_end: string,
     *     month_label: string,
     *     today_stats: array<string, int>,
     *     month_stats: array<string, int>,
     *     incidents_by_sector: list<array{label: string, count: int, url: string}>,
     *     incidents_by_type: list<array{label: string, count: int, url: string}>,
     *     shifts_activity: list<array{
     *         shift_id: int,
     *         operator_label: string,
     *         shift_date_label: string,
     *         status_label: string,
     *         total_entries: int,
     *         incidents: int,
     *         url: string
     *     }>,
     *     police_response_time: array<string, mixed>
     * }
     */
    public function supervisionPanel(int $camerasWithIssues = 0, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $monthStart = date('Y-m-01', strtotime($today));
        $monthEnd = date('Y-m-t', strtotime($today));
        $monthLabel = $this->formatMonthLabel($monthStart);

        $todayStats = $this->entries->statsForDate($today);
        $todayStats['cameras_with_issues'] = max(0, $camerasWithIssues);

        $monthStats = $this->entries->statsForRange($monthStart, $monthEnd);
        $monthStats['cameras_with_issues'] = max(0, $camerasWithIssues);

        return [
            'today' => $today,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
            'month_label' => $monthLabel,
            'today_stats' => $todayStats,
            'month_stats' => $monthStats,
            'incidents_by_sector' => $this->presentIncidentsBySector($monthStart, $monthEnd),
            'incidents_by_type' => $this->presentIncidentsByType($monthStart, $monthEnd),
            'shifts_activity' => $this->presentShiftsActivity(6),
            'police_response_time' => $this->presentPoliceResponseTime($monthStart, $monthEnd),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentPoliceResponseTime(string $dateFrom, string $dateTo): array
    {
        $calculated = $this->calculatePoliceResponseTimes($dateFrom, $dateTo);
        $summary = $this->policeResponseTime->summarize($calculated);

        return array_merge($summary, [
            'period_from' => $dateFrom,
            'period_to' => $dateTo,
            'filter_url' => $this->policeArrivedLogUrl($dateFrom, $dateTo),
        ]);
    }

    /**
     * @return list<array{
     *     entry_id: int,
     *     notification_source: string,
     *     notification_at: string,
     *     arrival_at: string,
     *     response_seconds: int
     * }>
     */
    public function calculatePoliceResponseTimes(string $dateFrom, string $dateTo): array
    {
        $rows = $this->entries->policeResponseCandidates($dateFrom, $dateTo);
        $calculated = [];

        foreach ($rows as $row) {
            $result = $this->policeResponseTime->calculate($row);
            if ($result !== null) {
                $calculated[] = $result;
            }
        }

        return $calculated;
    }

    /**
     * @return list<array{label: string, count: int, url: string}>
     */
    private function presentIncidentsBySector(string $monthStart, string $monthEnd): array
    {
        $rows = $this->entries->incidentsBySector($monthStart, $monthEnd, 8);
        $baseQuery = [
            'date_from' => $monthStart,
            'date_to' => $monthEnd,
            'log_type' => 'incidente',
        ];

        return array_map(function (array $row) use ($baseQuery): array {
            $query = $baseQuery;
            $sectorId = $row['sector_id'] ?? null;
            if ($sectorId !== null && (int) $sectorId > 0) {
                $query['sector_id'] = (string) $sectorId;
            }

            return [
                'label' => (string) ($row['sector_name'] ?? 'Sin sector'),
                'count' => (int) ($row['total'] ?? 0),
                'url' => $this->logUrl($query),
            ];
        }, $rows);
    }

    /**
     * @return list<array{label: string, count: int, url: string}>
     */
    private function presentIncidentsByType(string $monthStart, string $monthEnd): array
    {
        $rows = $this->entries->incidentsByType($monthStart, $monthEnd, 8);
        $baseQuery = [
            'date_from' => $monthStart,
            'date_to' => $monthEnd,
            'log_type' => 'incidente',
        ];

        return array_map(function (array $row) use ($baseQuery): array {
            $slug = trim((string) ($row['slug'] ?? ''));
            $query = $baseQuery;
            if ($slug !== '') {
                $query['incident_type'] = $slug;
            }

            return [
                'label' => (string) ($row['name'] ?? '—'),
                'count' => (int) ($row['total'] ?? 0),
                'url' => $this->logUrl($query),
            ];
        }, $rows);
    }

    /**
     * @return list<array{
     *     shift_id: int,
     *     operator_label: string,
     *     shift_date_label: string,
     *     status_label: string,
     *     total_entries: int,
     *     incidents: int,
     *     url: string
     * }>
     */
    private function presentShiftsActivity(int $limit): array
    {
        $rows = $this->shifts->recentWithEntryCounts($limit);

        return array_map(static function (array $row): array {
            $shiftId = (int) ($row['id'] ?? 0);
            $shiftDate = (string) ($row['shift_date'] ?? '');
            $status = (string) ($row['status'] ?? '');

            return [
                'shift_id' => $shiftId,
                'operator_label' => trim((string) ($row['operator_name'] ?? '')) ?: '—',
                'shift_date_label' => $shiftDate !== ''
                    ? date('d-m-Y', strtotime($shiftDate))
                    : '—',
                'status_label' => $status === 'open' ? 'Abierto' : 'Cerrado',
                'total_entries' => (int) ($row['total_entries'] ?? 0),
                'incidents' => (int) ($row['incidents'] ?? 0),
                'url' => url('/cctv/shifts/' . $shiftId),
            ];
        }, $rows);
    }

    /**
     * @param array<string, scalar|null> $query
     */
    public function logUrl(array $query): string
    {
        $filtered = array_filter(
            $query,
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );

        return url('/cctv/log?' . http_build_query($filtered));
    }

    public function policeLogUrl(string $dateFrom, string $dateTo): string
    {
        return $this->logUrl([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'log_type' => 'incidente',
            'contact_type' => LogContact::TYPE_CARABINEROS,
        ]);
    }

    public function policeArrivedLogUrl(string $dateFrom, string $dateTo): string
    {
        return $this->logUrl([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'log_type' => 'incidente',
            'police_arrived' => (string) LogEntry::POLICE_ARRIVED_YES,
        ]);
    }

    private function formatMonthLabel(string $monthStart): string
    {
        $months = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        $monthNumber = (int) date('n', strtotime($monthStart));
        $year = date('Y', strtotime($monthStart));

        return ($months[$monthNumber] ?? date('F', strtotime($monthStart))) . ' ' . $year;
    }
}
