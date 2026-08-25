<?php

declare(strict_types=1);

namespace App\Controllers\Senda;

use App\Services\Senda\StatisticsService;
use App\Services\Export\CsvExportService;
use Core\Request;

final class StatisticsController extends SendaController
{
    public function __construct(
        private readonly StatisticsService $statistics = new StatisticsService(),
        private readonly CsvExportService $csv = new CsvExportService()
    ) {
    }

    public function index(Request $request): void
    {
        $filters = $this->statistics->normalizeFilters([
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ]);

        $this->sendaView('statistics/index', [
            'title' => 'Estadísticas — SENDA',
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
