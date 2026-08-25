<?php

declare(strict_types=1);

namespace App\Services\Senda;

use App\Repositories\Senda\FollowUpRepository;
use App\Repositories\Senda\StatsRepository;
use App\Services\Export\StatisticsReportExporter;

final class StatisticsService
{
    public function __construct(
        private readonly StatsRepository $stats = new StatsRepository(),
        private readonly FollowUpRepository $followUps = new FollowUpRepository(),
        private readonly AssistClassificationService $assistClassification = new AssistClassificationService(),
        private readonly StatisticsReportExporter $exporter = new StatisticsReportExporter()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{date_from: string, date_to: string}
     */
    public function normalizeFilters(array $input): array
    {
        $dateTo = trim((string) ($input['date_to'] ?? ''));
        $dateFrom = trim((string) ($input['date_from'] ?? ''));

        if ($dateTo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = date('Y-m-d');
        }

        if ($dateFrom === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = date('Y-01-01', strtotime($dateTo));
        }

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    /**
     * @param array{date_from: string, date_to: string} $filters
     * @return list<array{key: string, label: string, count: int, tone: string}>
     */
    public function summaryCards(array $filters): array
    {
        $from = $filters['date_from'];
        $to = $filters['date_to'];
        $totals = $this->stats->periodTotals($from, $to);
        $schedule = $this->followUps->scheduleCounts();

        return [
            ['key' => 'attentions_period', 'label' => 'Atenciones en el periodo', 'count' => $totals['attentions_period'], 'tone' => 'due'],
            ['key' => 'derivations_period', 'label' => 'Derivaciones en el periodo', 'count' => $totals['derivations_period'], 'tone' => 'due'],
            ['key' => 'spontaneous_period', 'label' => 'Demandas espontáneas en el periodo', 'count' => $totals['spontaneous_period'], 'tone' => 'due'],
            ['key' => 'referrals_completed', 'label' => 'Fichas completadas en el periodo', 'count' => $totals['referrals_completed'], 'tone' => 'done'],
            ['key' => 'screenings_period', 'label' => 'Tamizajes en el periodo', 'count' => $totals['screenings_period'], 'tone' => 'done'],
            ['key' => 'followups_period', 'label' => 'Seguimientos realizados en el periodo', 'count' => $totals['followups_period'], 'tone' => 'done'],
            ['key' => FollowUpStatus::PENDING, 'label' => FollowUpStatus::label(FollowUpStatus::PENDING), 'count' => (int) ($schedule[FollowUpStatus::PENDING] ?? 0), 'tone' => FollowUpStatus::tone(FollowUpStatus::PENDING)],
            ['key' => FollowUpStatus::OVERDUE, 'label' => FollowUpStatus::label(FollowUpStatus::OVERDUE), 'count' => (int) ($schedule[FollowUpStatus::OVERDUE] ?? 0), 'tone' => FollowUpStatus::tone(FollowUpStatus::OVERDUE)],
        ];
    }

    /**
     * @param array{date_from: string, date_to: string} $filters
     * @return array{filename: string, content: string}
     */
    public function buildExport(array $filters): array
    {
        $summary = array_map(
            static fn (array $card): array => [
                'label' => (string) ($card['label'] ?? ''),
                'count' => (int) ($card['count'] ?? 0),
            ],
            $this->summaryCards($filters)
        );

        return [
            'filename' => $this->exporter->filename('senda_indicadores', $filters),
            'content' => $this->exporter->toCsv([
                'title' => 'Estadísticas SENDA',
                'module_label' => 'SENDA',
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
            ], $summary, $this->tables($filters)),
        ];
    }

    /**
     * @return list<array{key: string, label: string, count: int, path: string, tone: string}>
     */
    public function dashboardCards(?string $today = null): array
    {
        $today = FollowUpStatus::today($today);
        $monthStart = date('Y-m-01', strtotime($today));
        $monthEnd = date('Y-m-t', strtotime($today));
        $totals = $this->stats->dashboardTotals($today, $monthStart, $monthEnd);
        $schedule = $this->followUps->scheduleCounts($today);

        $cards = [
            [
                'key' => 'attentions_today',
                'label' => 'Atenciones de hoy',
                'count' => $totals['attentions_today'],
                'path' => '/senda/attentions?date_from=' . $today . '&date_to=' . $today,
                'tone' => 'due',
                'permission' => 'senda.attentions.view',
            ],
            [
                'key' => 'attentions_month',
                'label' => 'Atenciones del mes',
                'count' => $totals['attentions_month'],
                'path' => '/senda/attentions?date_from=' . $monthStart . '&date_to=' . $monthEnd,
                'tone' => 'due',
                'permission' => 'senda.attentions.view',
            ],
            [
                'key' => 'derivations_month',
                'label' => 'Derivaciones del mes',
                'count' => $totals['derivations_month'],
                'path' => '/senda/attentions?date_from=' . $monthStart . '&date_to=' . $monthEnd . '&entry_type=' . EntryType::DERIVACION,
                'tone' => 'due',
                'permission' => 'senda.attentions.view',
            ],
            [
                'key' => 'spontaneous_month',
                'label' => 'Demandas espontáneas del mes',
                'count' => $totals['spontaneous_month'],
                'path' => '/senda/attentions?date_from=' . $monthStart . '&date_to=' . $monthEnd . '&entry_type=' . EntryType::DEMANDA_ESPONTANEA,
                'tone' => 'due',
                'permission' => 'senda.attentions.view',
            ],
            [
                'key' => 'referrals_total',
                'label' => 'Fichas realizadas',
                'count' => $totals['referrals_total'],
                'path' => '/senda/referrals',
                'tone' => 'done',
                'permission' => 'senda.referrals.view',
            ],
            [
                'key' => 'screenings_total',
                'label' => 'Tamizajes aplicados',
                'count' => $totals['screenings_total'],
                'path' => '/senda/referrals',
                'tone' => 'done',
                'permission' => 'senda.referrals.view',
            ],
            [
                'key' => FollowUpStatus::DONE_TODAY,
                'label' => FollowUpStatus::label(FollowUpStatus::DONE_TODAY),
                'count' => (int) ($schedule[FollowUpStatus::DONE_TODAY] ?? 0),
                'path' => '/senda/follow-ups?status=' . FollowUpStatus::DONE_TODAY,
                'tone' => FollowUpStatus::tone(FollowUpStatus::DONE_TODAY),
                'permission' => 'senda.followups.view',
            ],
            [
                'key' => FollowUpStatus::PENDING,
                'label' => FollowUpStatus::label(FollowUpStatus::PENDING),
                'count' => (int) ($schedule[FollowUpStatus::PENDING] ?? 0),
                'path' => '/senda/follow-ups?status=' . FollowUpStatus::PENDING,
                'tone' => FollowUpStatus::tone(FollowUpStatus::PENDING),
                'permission' => 'senda.followups.view',
            ],
            [
                'key' => FollowUpStatus::OVERDUE,
                'label' => FollowUpStatus::label(FollowUpStatus::OVERDUE),
                'count' => (int) ($schedule[FollowUpStatus::OVERDUE] ?? 0),
                'path' => '/senda/follow-ups?status=' . FollowUpStatus::OVERDUE,
                'tone' => FollowUpStatus::tone(FollowUpStatus::OVERDUE),
                'permission' => 'senda.followups.view',
            ],
        ];

        return array_values(array_filter(
            $cards,
            static fn (array $card): bool => hasPermission($card['permission'])
        ));
    }

    /**
     * Tablas de indicadores. Sin gráficos: cada fila sale de una agregación MySQL.
     *
     * @param array{date_from?: string, date_to?: string}|null $filters
     * @return list<array{title: string, columns: list<string>, rows: list<array{0: string, 1: int}>}>
     */
    public function tables(?array $filters = null): array
    {
        $filters ??= $this->normalizeFilters([]);
        $from = $filters['date_from'];
        $to = $filters['date_to'];
        $totals = $this->stats->periodTotals($from, $to);
        $schedule = $this->followUps->scheduleCounts();

        return [
            [
                'title' => 'Atenciones mensuales',
                'columns' => ['Mes', 'Atenciones'],
                'rows' => $this->monthlyRows($from, $to),
            ],
            [
                'title' => 'Atenciones por edad',
                'columns' => ['Tramo etario', 'Atenciones'],
                'rows' => $this->filledRows($this->stats->attentionsByAge($from, $to), 'bucket', $this->ageLabels()),
            ],
            [
                'title' => 'Tipo de ingreso',
                'columns' => ['Tipo', 'Atenciones'],
                'rows' => $this->entryTypeRows($from, $to),
            ],
            [
                'title' => 'Centro o dispositivo referido',
                'columns' => ['Centro o dispositivo', 'Fichas'],
                'rows' => $this->destinationCenterRows($from, $to),
            ],
            [
                'title' => 'Clasificaciones',
                'columns' => ['Clasificación ASSIST', 'Registros'],
                'rows' => $this->classificationRows($from, $to),
            ],
            [
                'title' => 'Seguimientos',
                'columns' => ['Indicador', 'Cantidad'],
                'rows' => [
                    ['Realizados en el periodo', $totals['followups_period']],
                    ['Realizados hoy', (int) ($schedule[FollowUpStatus::DONE_TODAY] ?? 0)],
                    ['Pendientes', (int) ($schedule[FollowUpStatus::PENDING] ?? 0)],
                    ['Atrasados', (int) ($schedule[FollowUpStatus::OVERDUE] ?? 0)],
                ],
            ],
            [
                'title' => 'Resultados de seguimiento',
                'columns' => ['Resultado', 'Seguimientos'],
                'rows' => $this->followUpResultRows($from, $to),
            ],
        ];
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function monthlyRows(string $dateFrom, string $dateTo): array
    {
        $indexed = [];
        foreach ($this->stats->attentionsByMonthForPeriod($dateFrom, $dateTo) as $row) {
            $indexed[(string) $row['period']] = (int) $row['total'];
        }

        $start = new \DateTimeImmutable($dateFrom);
        $end = new \DateTimeImmutable($dateTo);
        $cursor = $start->modify('first day of this month');
        $last = $end->modify('first day of this month');
        $rows = [];

        while ($cursor <= $last) {
            $period = $cursor->format('Y-m');
            $rows[] = [$cursor->format('m-Y'), $indexed[$period] ?? 0];
            $cursor = $cursor->modify('+1 month');
        }

        return $rows;
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function entryTypeRows(string $dateFrom, string $dateTo): array
    {
        $labels = [];
        foreach (EntryType::values() as $type) {
            $labels[$type] = EntryType::label($type);
        }

        return $this->filledRows($this->stats->attentionsByEntryType($dateFrom, $dateTo), 'entry_type', $labels);
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function destinationCenterRows(string $dateFrom, string $dateTo): array
    {
        $labels = [];
        foreach (AssistedReferralCatalog::destinationCenters() as $option) {
            if ($option['value'] === 'otros') {
                continue;
            }

            $labels[$option['value']] = $option['label'];
        }

        return $this->filledRows($this->stats->referralsByDestinationCenter($dateFrom, $dateTo), 'destination_center', $labels);
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function classificationRows(string $dateFrom, string $dateTo): array
    {
        $labels = ['sin_clasificar' => 'Sin clasificar'];
        foreach ($this->assistClassification->options() as $option) {
            $labels[$option['value']] = $option['label'];
        }

        return $this->filledRows($this->stats->assistByClassification($dateFrom, $dateTo), 'risk_level', $labels);
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function followUpResultRows(string $dateFrom, string $dateTo): array
    {
        $labels = [];
        foreach (FollowUpCatalog::results() as $option) {
            $labels[$option['value']] = $option['label'];
        }

        return $this->filledRows($this->stats->followUpsByResult($dateFrom, $dateTo), 'result', $labels);
    }

    /**
     * @return array<string, string>
     */
    private function ageLabels(): array
    {
        return [
            '0_17' => 'Menores de 18',
            '18_29' => '18 a 29 años',
            '30_44' => '30 a 44 años',
            '45_59' => '45 a 59 años',
            '60_plus' => '60 años o más',
            'sin_dato' => 'Sin fecha de nacimiento',
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, string> $labels
     * @return list<array{0: string, 1: int}>
     */
    private function filledRows(array $rows, string $key, array $labels): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) ($row[$key] ?? '')] = (int) ($row['total'] ?? 0);
        }

        $presented = [];
        foreach ($labels as $value => $label) {
            $presented[] = [$label, $indexed[$value] ?? 0];
        }

        foreach ($indexed as $value => $total) {
            if (!isset($labels[$value])) {
                $presented[] = [$value !== '' ? $value : '—', $total];
            }
        }

        return $presented;
    }
}
