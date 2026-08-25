<?php

declare(strict_types=1);

namespace App\Controllers\WomenOffice;

use App\Services\WomenOffice\WomenStatisticsService;
use App\Services\Export\CsvExportService;
use Core\Request;

final class StatisticsController extends WomenOfficeController
{
    public function __construct(
        private readonly WomenStatisticsService $statistics = new WomenStatisticsService(),
        private readonly CsvExportService $csv = new CsvExportService()
    ) {
    }

    public function index(Request $request): void
    {
        $filters = $this->statistics->normalizeFilters([
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ]);

        $this->womenView('statistics/index', [
            'title' => 'Estadísticas — Oficina de la Mujer',
            'filters' => $filters,
            'summary' => $this->statistics->summaryCards($filters),
            'tables' => $this->statistics->tables($filters),
        ]);
    }

    public function export(Request $request): void
    {
        $filters = $this->statistics->normalizeFilters([
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ]);

        $export = $this->statistics->buildExport($filters);
        $this->csv->download($export['filename'], $export['content']);
    }
}
