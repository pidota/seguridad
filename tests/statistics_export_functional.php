<?php

declare(strict_types=1);

/**
 * Pruebas de exportación de indicadores SENDA y Mujer.
 * Ejecutar: php tests/statistics_export_functional.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse por consola.\n");
    exit(1);
}

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/index.php';

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Services\Export\CsvExportService;
use App\Services\Export\StatisticsReportExporter;
use App\Services\Senda\StatisticsService as SendaStatisticsService;
use App\Services\WomenOffice\WomenStatisticsService;
use Core\Auth;
use Core\Database;
use Core\Permission;
use Core\Request;
use Core\Session;

final class StatisticsExportFunctionalTests
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var list<string> */
    private array $failures = [];

    public function run(): int
    {
        $this->boot();

        try {
            $this->testSendaExport();
            $this->testWomenExport();
            $this->testPermissionsExist();
        } catch (\Throwable $e) {
            $this->fail('ejecución', $e->getMessage());
        }

        $this->printSummary();

        return $this->failed === 0 ? 0 : 1;
    }

    private function boot(): void
    {
        Session::start();
        Request::capture();

        $adminId = (int) Database::connection()->query(
            "SELECT u.id FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.slug = 'superadministrador' LIMIT 1"
        )->fetchColumn();

        Session::put('auth_user_id', $adminId);
        Auth::forgetCache();
        Permission::flush();
    }

    private function testSendaExport(): void
    {
        $service = new SendaStatisticsService();
        $filters = $service->normalizeFilters([
            'date_from' => date('Y-01-01'),
            'date_to' => date('Y-m-d'),
        ]);

        $summary = $service->summaryCards($filters);
        $tables = $service->tables($filters);
        $export = $service->buildExport($filters);

        $this->assertTrue(count($summary) >= 6, 'SENDA summary cards');
        $this->assertTrue(count($tables) === 7, 'SENDA tiene 7 tablas');
        $this->assertTrue(str_contains($export['content'], 'SIGSM'), 'CSV SENDA contiene encabezado');
        $this->assertTrue(str_contains($export['content'], 'SENDA'), 'CSV SENDA identifica módulo');
        $this->assertTrue(str_ends_with($export['filename'], '.csv'), 'Nombre archivo SENDA termina en .csv');
    }

    private function testWomenExport(): void
    {
        $service = new WomenStatisticsService();
        $filters = $service->normalizeFilters([
            'date_from' => date('Y-01-01'),
            'date_to' => date('Y-m-d'),
        ]);

        $export = $service->buildExport($filters);

        $this->assertTrue(str_contains($export['content'], 'Oficina de la Mujer'), 'CSV Mujer identifica módulo');
        $this->assertTrue(str_contains($export['content'], 'Casos en el periodo'), 'CSV Mujer incluye resumen');
        $this->assertTrue(str_starts_with($export['content'], "\xEF\xBB\xBF"), 'CSV incluye BOM UTF-8');
    }

    private function testPermissionsExist(): void
    {
        $this->assertTrue(Permission::has('senda.statistics.export'), 'Permiso senda.statistics.export');
        $this->assertTrue(Permission::has('women.statistics.export'), 'Permiso women.statistics.export');
    }

    private function assertTrue(bool $condition, string $label): void
    {
        if ($condition) {
            $this->passed++;
            fwrite(STDOUT, "[OK] {$label}\n");
        } else {
            $this->fail($label, 'Condición falsa');
        }
    }

    private function fail(string $label, string $reason): void
    {
        $this->failed++;
        $this->failures[] = "{$label}: {$reason}";
        fwrite(STDOUT, "[FAIL] {$label}: {$reason}\n");
    }

    private function printSummary(): void
    {
        fwrite(STDOUT, PHP_EOL . "Resumen: {$this->passed} OK, {$this->failed} FAIL" . PHP_EOL);
    }
}

exit((new StatisticsExportFunctionalTests())->run());
