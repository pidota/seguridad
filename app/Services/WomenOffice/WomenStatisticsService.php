<?php

declare(strict_types=1);

namespace App\Services\WomenOffice;

use App\Repositories\WomenOffice\StatsRepository;

final class WomenStatisticsService
{
    public function __construct(
        private readonly StatsRepository $stats = new StatsRepository()
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

        return [
            [
                'key' => 'cases_total',
                'label' => 'Casos en el periodo',
                'count' => $this->stats->totalCases($from, $to),
                'tone' => 'default',
            ],
            [
                'key' => 'referrals_total',
                'label' => 'Derivaciones en el periodo',
                'count' => $this->stats->referralsTotal($from, $to),
                'tone' => 'due',
            ],
            [
                'key' => 'followups_performed',
                'label' => 'Seguimientos realizados',
                'count' => $this->stats->followUpsPerformed($from, $to),
                'tone' => 'done',
            ],
            [
                'key' => 'followups_pending',
                'label' => 'Casos con seguimiento pendiente',
                'count' => $this->stats->pendingFollowUpCases($from, $to),
                'tone' => 'due',
            ],
            [
                'key' => 'followups_overdue',
                'label' => 'Casos con seguimiento atrasado',
                'count' => $this->stats->overdueFollowUpCases($from, $to),
                'tone' => 'overdue',
            ],
        ];
    }

    /**
     * Tablas agregadas. Sin datos personales identificables.
     *
     * @param array{date_from: string, date_to: string} $filters
     * @return list<array{title: string, columns: list<string>, rows: list<array{0: string, 1: int}>}>
     */
    public function tables(array $filters): array
    {
        $from = $filters['date_from'];
        $to = $filters['date_to'];

        return [
            [
                'title' => 'Casos por mes',
                'columns' => ['Mes', 'Casos'],
                'rows' => $this->monthlyRows($from, $to),
            ],
            [
                'title' => 'Tipo de violencia',
                'columns' => ['Tipo', 'Casos'],
                'rows' => $this->violenceRows($from, $to),
            ],
            [
                'title' => 'Rango etario',
                'columns' => ['Tramo', 'Casos'],
                'rows' => $this->filledRows(
                    $this->stats->casesByAgeRange($from, $to),
                    'bucket',
                    $this->ageLabels()
                ),
            ],
            [
                'title' => 'Sector territorial',
                'columns' => ['Sector', 'Casos'],
                'rows' => $this->labelRows($this->stats->casesBySector($from, $to)),
            ],
            [
                'title' => 'Relación con denunciado',
                'columns' => ['Relación', 'Casos'],
                'rows' => $this->labelRows($this->stats->casesByRelationship($from, $to)),
            ],
            [
                'title' => 'Denuncia formal actual',
                'columns' => ['Respuesta', 'Casos'],
                'rows' => $this->formalReportRows($from, $to),
            ],
            [
                'title' => 'Prioridad asignada',
                'columns' => ['Prioridad', 'Casos'],
                'rows' => $this->priorityRows($from, $to),
            ],
            [
                'title' => 'Estado del caso',
                'columns' => ['Estado', 'Casos'],
                'rows' => $this->labelRows($this->stats->casesByStatus($from, $to)),
            ],
            [
                'title' => 'Derivaciones por institución',
                'columns' => ['Institución', 'Derivaciones'],
                'rows' => $this->labelRows($this->stats->referralsByInstitution($from, $to)),
            ],
            [
                'title' => 'Derivaciones por estado',
                'columns' => ['Estado', 'Derivaciones'],
                'rows' => $this->labelRows($this->stats->referralsByStatus($from, $to)),
            ],
            [
                'title' => 'Seguimientos',
                'columns' => ['Indicador', 'Cantidad'],
                'rows' => [
                    ['Realizados en el periodo', $this->stats->followUpsPerformed($from, $to)],
                    ['Casos con seguimiento pendiente', $this->stats->pendingFollowUpCases($from, $to)],
                    ['Casos con seguimiento atrasado', $this->stats->overdueFollowUpCases($from, $to)],
                ],
            ],
        ];
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function monthlyRows(string $dateFrom, string $dateTo): array
    {
        $indexed = [];
        foreach ($this->stats->casesByMonth($dateFrom, $dateTo) as $row) {
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
    private function violenceRows(string $dateFrom, string $dateTo): array
    {
        $rows = [];
        foreach ($this->stats->casesByViolenceType($dateFrom, $dateTo) as $row) {
            $rows[] = [(string) $row['label'], (int) $row['total']];
        }

        return $rows;
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function formalReportRows(string $dateFrom, string $dateTo): array
    {
        $labels = [
            'yes' => 'Sí',
            'no' => 'No',
            'unknown' => 'No informado',
            '' => 'Sin registrar',
        ];

        return $this->filledRows(
            $this->stats->casesByFormalReport($dateFrom, $dateTo),
            'value',
            $labels
        );
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function priorityRows(string $dateFrom, string $dateTo): array
    {
        $labels = ['' => 'Sin prioridad'];
        foreach (WomenCaseService::priorityOptions() as $option) {
            $labels[(string) $option['value']] = (string) $option['label'];
        }

        return $this->filledRows(
            $this->stats->casesByPriority($dateFrom, $dateTo),
            'value',
            $labels
        );
    }

    /**
     * @param list<array{label: string, total: int}> $rows
     * @return list<array{0: string, 1: int}>
     */
    private function labelRows(array $rows): array
    {
        return array_map(
            static fn (array $row): array => [(string) $row['label'], (int) $row['total']],
            $rows
        );
    }

    /**
     * @return array<string, string>
     */
    private function ageLabels(): array
    {
        $labels = ['unknown' => 'Sin fecha de nacimiento'];
        foreach (WomenCaseService::ageRangeOptions() as $option) {
            $labels[(string) $option['value']] = (string) $option['label'];
        }

        return $labels;
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
