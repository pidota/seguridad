<?php

declare(strict_types=1);

namespace App\Services\Senda;

use App\Repositories\Senda\FollowUpRepository;
use App\Repositories\Senda\StatsRepository;

final class StatisticsService
{
    public function __construct(
        private readonly StatsRepository $stats = new StatsRepository(),
        private readonly FollowUpRepository $followUps = new FollowUpRepository(),
        private readonly AssistClassificationService $assistClassification = new AssistClassificationService()
    ) {
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
     * @return list<array{title: string, columns: list<string>, rows: list<array{0: string, 1: int}>}>
     */
    public function tables(): array
    {
        $today = FollowUpStatus::today();
        $monthStart = date('Y-m-01', strtotime($today));
        $monthEnd = date('Y-m-t', strtotime($today));
        $totals = $this->stats->dashboardTotals($today, $monthStart, $monthEnd);
        $schedule = $this->followUps->scheduleCounts($today);

        return [
            [
                'title' => 'Atenciones mensuales',
                'columns' => ['Mes', 'Atenciones'],
                'rows' => $this->monthlyRows(),
            ],
            [
                'title' => 'Atenciones por edad',
                'columns' => ['Tramo etario', 'Atenciones'],
                'rows' => $this->filledRows($this->stats->attentionsByAge(), 'bucket', $this->ageLabels()),
            ],
            [
                'title' => 'Tipo de ingreso',
                'columns' => ['Tipo', 'Atenciones'],
                'rows' => $this->entryTypeRows(),
            ],
            [
                'title' => 'Sustancias ASSIST',
                'columns' => ['Sustancia', 'Registros con puntaje'],
                'rows' => $this->substanceRows(),
            ],
            [
                'title' => 'Clasificaciones',
                'columns' => ['Clasificación ASSIST', 'Registros'],
                'rows' => $this->classificationRows(),
            ],
            [
                'title' => 'Seguimientos',
                'columns' => ['Indicador', 'Cantidad'],
                'rows' => [
                    ['Total', $this->stats->followUpTotal()],
                    ['Realizados hoy', (int) ($schedule[FollowUpStatus::DONE_TODAY] ?? 0)],
                    ['Realizados este mes', $totals['followups_month']],
                    ['Pendientes', (int) ($schedule[FollowUpStatus::PENDING] ?? 0)],
                    ['Atrasados', (int) ($schedule[FollowUpStatus::OVERDUE] ?? 0)],
                ],
            ],
            [
                'title' => 'Resultados de seguimiento',
                'columns' => ['Resultado', 'Seguimientos'],
                'rows' => $this->followUpResultRows(),
            ],
        ];
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function monthlyRows(): array
    {
        $indexed = [];
        foreach ($this->stats->attentionsByMonth(12) as $row) {
            $indexed[(string) $row['period']] = (int) $row['total'];
        }

        $cursor = new \DateTimeImmutable('first day of this month');
        $rows = [];

        for ($offset = 11; $offset >= 0; $offset--) {
            $periodDate = $cursor->modify('-' . $offset . ' months');
            $period = $periodDate->format('Y-m');
            $rows[] = [$periodDate->format('m-Y'), $indexed[$period] ?? 0];
        }

        return $rows;
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function entryTypeRows(): array
    {
        $labels = [];
        foreach (EntryType::values() as $type) {
            $labels[$type] = EntryType::label($type);
        }

        return $this->filledRows($this->stats->attentionsByEntryType(), 'entry_type', $labels);
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function substanceRows(): array
    {
        $labels = [];
        foreach (AssistedReferralCatalog::assistSubstances() as $substance) {
            $labels[$substance['key']] = $substance['label'];
        }

        return $this->filledRows($this->stats->assistBySubstance(), 'substance', $labels);
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function classificationRows(): array
    {
        $labels = ['sin_clasificar' => 'Sin clasificar'];
        foreach ($this->assistClassification->options() as $option) {
            $labels[$option['value']] = $option['label'];
        }

        return $this->filledRows($this->stats->assistByClassification(), 'risk_level', $labels);
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function followUpResultRows(): array
    {
        $labels = [];
        foreach (FollowUpCatalog::results() as $option) {
            $labels[$option['value']] = $option['label'];
        }

        return $this->filledRows($this->stats->followUpsByResult(), 'result', $labels);
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
