<?php

declare(strict_types=1);

namespace App\Services\Export;

final class StatisticsReportExporter
{
    public function __construct(
        private readonly CsvExportService $csv = new CsvExportService()
    ) {
    }

    /**
     * @param array{
     *     title: string,
     *     module_label: string,
     *     date_from: string,
     *     date_to: string,
     *     generated_at?: string
     * } $meta
     * @param list<array{label: string, count: int}> $summary
     * @param list<array{title: string, columns: list<string>, rows: list<array{0: string, 1: int}>}> $tables
     */
    public function toCsv(array $meta, array $summary, array $tables): string
    {
        $rows = [
            ['SIGSM — Indicadores para rendición'],
            ['Módulo', $meta['module_label']],
            ['Reporte', $meta['title']],
            ['Periodo desde', $this->formatDate($meta['date_from'])],
            ['Periodo hasta', $this->formatDate($meta['date_to'])],
            ['Generado el', $meta['generated_at'] ?? date('d-m-Y H:i')],
            [''],
            ['Resumen del periodo'],
            ['Indicador', 'Cantidad'],
        ];

        foreach ($summary as $card) {
            $rows[] = [(string) ($card['label'] ?? ''), (int) ($card['count'] ?? 0)];
        }

        foreach ($tables as $table) {
            $rows[] = [''];
            $rows[] = [(string) ($table['title'] ?? 'Indicador')];
            $rows[] = array_map(static fn ($column): string => (string) $column, $table['columns'] ?? []);

            foreach ($table['rows'] ?? [] as $row) {
                $rows[] = [(string) ($row[0] ?? ''), (int) ($row[1] ?? 0)];
            }
        }

        return $this->csv->build($rows);
    }

    /**
     * @param array{date_from: string, date_to: string} $filters
     */
    public function filename(string $prefix, array $filters): string
    {
        return sprintf(
            '%s_%s_%s.csv',
            $prefix,
            str_replace('-', '', $filters['date_from']),
            str_replace('-', '', $filters['date_to'])
        );
    }

    private function formatDate(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp !== false ? date('d-m-Y', $timestamp) : $value;
    }
}
