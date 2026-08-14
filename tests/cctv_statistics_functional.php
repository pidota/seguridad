<?php

declare(strict_types=1);

/**
 * Pruebas funcionales de estadísticas operacionales CCTV.
 * Ejecutar: php tests/cctv_statistics_functional.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse por consola.\n");
    exit(1);
}

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/cctv';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/seguridad/public/index.php';

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Models\Cctv\LogEntry;
use App\Repositories\Cctv\LogEntryRepository;
use App\Services\Cctv\LogEntryService;
use App\Services\Cctv\PoliceResponseTimeCalculator;
use App\Services\Cctv\ShiftService;
use App\Services\Cctv\StatisticsService;
use Core\Auth;
use Core\Database;
use Core\Permission;
use Core\Request;
use Core\Session;

final class CctvStatisticsFunctionalTests
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var list<string> */
    private array $failures = [];

    /** @var list<int> */
    private array $shiftIds = [];

    /** @var list<int> */
    private array $entryIds = [];

    public function run(): int
    {
        $this->boot();

        try {
            $this->testStatsForRange();
            $this->testIncidentsBreakdowns();
            $this->testPoliceCommunicationsCount();
            $this->testCoordinationFilter();
            $this->testSupervisionPanelStructure();
            $this->testShiftStatsIncludePolice();
            $this->testPoliceResponseTimeCalculation();
            $this->testPoliceResponseIntegration();
        } catch (\Throwable $e) {
            $this->fail('ejecución', $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        }

        try {
            $this->cleanup();
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Aviso: no se pudo limpiar todo el residuo de prueba: ' . $e->getMessage() . PHP_EOL);
        }

        $this->printSummary();

        return $this->failed === 0 ? 0 : 1;
    }

    private function boot(): void
    {
        Session::start();
        Request::capture();

        $pdo = Database::connection();
        $adminId = (int) $pdo->query(
            "SELECT u.id FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.slug = 'superadministrador' AND u.is_active = 1
             LIMIT 1"
        )->fetchColumn();

        if ($adminId < 1) {
            throw new RuntimeException('No hay un superadministrador activo para las pruebas.');
        }

        Session::put('auth_user_id', $adminId);
        Auth::forgetCache();
        Permission::flush();

        $shiftService = new ShiftService();
        $open = $shiftService->findOpenForOperator($adminId);
        if ($open !== null) {
            $shiftService->close((int) $open['id'], 'Limpieza previa a pruebas de estadísticas');
        }
    }

    private function testStatsForRange(): void
    {
        $repo = new LogEntryRepository();
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        $dayStats = $repo->statsForDate($today);
        $this->assertArrayHasKey('incidents', $dayStats, 'statsForDate incluye incidentes');
        $this->assertArrayHasKey('police_communications', $dayStats, 'statsForDate incluye Carabineros');

        $monthStats = $repo->statsForRange($monthStart, $monthEnd);
        $this->assertArrayHasKey('technical_issues', $monthStats, 'statsForRange incluye novedades técnicas');
        $this->assertTrue($monthStats['total_entries'] >= $dayStats['total_entries'], 'El mes acumula al menos lo de hoy');
    }

    private function testIncidentsBreakdowns(): void
    {
        $repo = new LogEntryRepository();
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        $bySector = $repo->incidentsBySector($monthStart, $monthEnd, 5);
        $this->assertTrue(is_array($bySector), 'incidentsBySector devuelve arreglo');

        foreach ($bySector as $row) {
            $this->assertTrue(isset($row['sector_name'], $row['total']), 'Fila de sector tiene nombre y total');
        }

        $byType = $repo->incidentsByType($monthStart, $monthEnd, 5);
        $this->assertTrue(is_array($byType), 'incidentsByType devuelve arreglo');

        foreach ($byType as $row) {
            $this->assertTrue(isset($row['slug'], $row['name'], $row['total']), 'Fila de tipo tiene slug, nombre y total');
        }
    }

    private function testPoliceCommunicationsCount(): void
    {
        $shiftService = new ShiftService();
        $logService = new LogEntryService();
        $repo = new LogEntryRepository();
        $operatorId = Auth::id();
        $today = date('Y-m-d');

        $shiftId = $shiftService->open(['shift_date' => $today], $operatorId);
        $this->shiftIds[] = $shiftId;

        $pdo = Database::connection();
        $incidentLogTypeId = (int) $pdo->query(
            "SELECT id FROM cctv_log_types WHERE slug = 'incidente' AND deleted_at IS NULL LIMIT 1"
        )->fetchColumn();
        $incidentTypeId = (int) $pdo->query(
            "SELECT id FROM cctv_incident_types WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1"
        )->fetchColumn();
        $sectorId = (int) $pdo->query(
            'SELECT id FROM sectors WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1'
        )->fetchColumn();

        if ($incidentLogTypeId < 1 || $incidentTypeId < 1) {
            $this->fail('police communications', 'Faltan catálogos de incidente para la prueba');
            return;
        }

        $entryId = $logService->createForOpenShift([
            'event_date' => $today,
            'event_time' => date('H:i'),
            'log_type_id' => $incidentLogTypeId,
            'incident_type_id' => $incidentTypeId,
            'sector_id' => $sectorId > 0 ? $sectorId : null,
            'police_arrived' => LogEntry::POLICE_ARRIVED_YES,
            'police_arrival_time' => date('H:i'),
            'observations' => 'Incidente con llegada de Carabineros para estadísticas.',
        ], $operatorId);
        $this->entryIds[] = $entryId;

        $dayStats = $repo->statsForDate($today);
        $this->assertTrue($dayStats['police_communications'] >= 1, 'Cuenta incidente con Carabineros presentes');

        $shiftStats = $repo->shiftStats($shiftId);
        $this->assertTrue($shiftStats['police_communications'] >= 1, 'shiftStats incluye comunicación policial');

        $shiftService->close($shiftId, 'Cierre tras prueba de estadísticas');
    }

    private function testCoordinationFilter(): void
    {
        $repo = new LogEntryRepository();
        $today = date('Y-m-d');
        $result = $repo->paginate([
            'date_from' => $today,
            'date_to' => $today,
            'coordination_notified' => '1',
        ], 1, 5);

        $this->assertTrue(isset($result['total'], $result['data']), 'Filtro coordination_notified pagina sin error');
    }

    private function testSupervisionPanelStructure(): void
    {
        $statsService = new StatisticsService();
        $panel = $statsService->supervisionPanel(3);

        $this->assertSame(date('Y-m-d'), $panel['today'] ?? null, 'Panel usa fecha de hoy');
        $this->assertTrue(isset($panel['month_stats']['incidents']), 'Panel incluye incidentes del mes');
        $this->assertTrue(is_array($panel['incidents_by_sector']), 'Panel incluye desglose por sector');
        $this->assertTrue(is_array($panel['incidents_by_type']), 'Panel incluye desglose por tipo');
        $this->assertTrue(is_array($panel['shifts_activity']), 'Panel incluye actividad por turno');
        $this->assertTrue(isset($panel['police_response_time']['eligible_count']), 'Panel incluye tiempo de respuesta Carabineros');

        $shiftService = new ShiftService();
        $dashboard = $shiftService->supervisionDashboard(3, 3);
        $this->assertTrue(isset($dashboard['month_label']), 'Supervisión expone etiqueta del mes');
        $this->assertTrue(isset($dashboard['month_stats']), 'Supervisión expone stats del mes');
        $this->assertTrue(isset($dashboard['police_response_time']['average_label']), 'Supervisión expone promedio de respuesta');
    }

    private function testShiftStatsIncludePolice(): void
    {
        $repo = new LogEntryRepository();
        $stats = $repo->shiftStats(0);
        $this->assertSame(0, $stats['police_communications'], 'Turno inexistente devuelve cero comunicaciones');
    }

    private function testPoliceResponseTimeCalculation(): void
    {
        $calculator = new PoliceResponseTimeCalculator();

        $fromContact = $calculator->calculate([
            'entry_id' => 1,
            'occurred_at' => '2026-08-14 13:00:00',
            'police_arrival_time' => '13:25:00',
            'carabineros_notified_at' => '2026-08-14 13:05:00',
        ]);
        $this->assertTrue($fromContact !== null, 'Calcula caso con aviso a Carabineros');
        $this->assertSame(1200, $fromContact['response_seconds'] ?? null, 'Aviso 13:05 a llegada 13:25 = 20 min');
        $this->assertSame(
            PoliceResponseTimeCalculator::SOURCE_CARABINEROS_CONTACT,
            $fromContact['notification_source'] ?? null,
            'Prioriza aviso a Carabineros'
        );

        $fromIncident = $calculator->calculate([
            'entry_id' => 2,
            'occurred_at' => '2026-08-14 14:00:00',
            'police_arrival_time' => '14:30:00',
            'carabineros_notified_at' => null,
        ]);
        $this->assertTrue($fromIncident !== null, 'Calcula caso sin aviso usando hora del suceso');
        $this->assertSame(1800, $fromIncident['response_seconds'] ?? null, 'Suceso 14:00 a llegada 14:30 = 30 min');
        $this->assertSame(
            PoliceResponseTimeCalculator::SOURCE_INCIDENT_OCCURRED,
            $fromIncident['notification_source'] ?? null,
            'Usa occurred_at cuando no hay aviso'
        );

        $missingArrival = $calculator->calculate([
            'entry_id' => 3,
            'occurred_at' => '2026-08-14 15:00:00',
            'police_arrival_time' => '',
            'carabineros_notified_at' => '2026-08-14 15:05:00',
        ]);
        $this->assertTrue($missingArrival === null, 'No inventa valor sin hora de llegada');

        $overnight = $calculator->calculate([
            'entry_id' => 4,
            'occurred_at' => '2026-08-14 23:50:00',
            'police_arrival_time' => '00:15:00',
            'carabineros_notified_at' => '2026-08-14 23:55:00',
        ]);
        $this->assertTrue($overnight !== null, 'Calcula cruce de medianoche');
        $this->assertSame(1200, $overnight['response_seconds'] ?? null, '23:55 a 00:15 del día siguiente = 20 min');

        $summary = $calculator->summarize([$fromContact, $fromIncident]);
        $this->assertSame(2, $summary['eligible_count'], 'Resumen cuenta casos calculables');
        $this->assertSame(1500, $summary['average_seconds'], 'Promedio de 1200 y 1800 = 1500');
        $this->assertSame('20 min', $summary['min_label'], 'Mínimo formateado');
        $this->assertSame('30 min', $summary['max_label'], 'Máximo formateado');

        $empty = $calculator->summarize([]);
        $this->assertSame('—', $empty['average_label'], 'Promedio vacío no inventa valor');
    }

    private function testPoliceResponseIntegration(): void
    {
        $shiftService = new ShiftService();
        $logService = new LogEntryService();
        $statsService = new StatisticsService();
        $operatorId = Auth::id();
        $today = date('Y-m-d');

        $shiftId = $shiftService->open(['shift_date' => $today], $operatorId);
        $this->shiftIds[] = $shiftId;

        $pdo = Database::connection();
        $incidentTypeId = (int) $pdo->query(
            "SELECT id FROM cctv_incident_types WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1"
        )->fetchColumn();

        if ($incidentTypeId < 1) {
            $this->fail('police response integration', 'Faltan catálogos de incidente');
            return;
        }

        $entryId = $logService->createIncidentForOpenShift([
            'event_date' => $today,
            'event_time' => '09:00',
            'incident_type_id' => $incidentTypeId,
            'coordination_notified' => 1,
            'contacts' => [
                [
                    'contact_type' => 'carabineros',
                    'contacted_at' => '09:10',
                ],
            ],
            'police_arrived' => LogEntry::POLICE_ARRIVED_YES,
            'police_arrival_time' => '09:40',
            'observations' => 'Incidente con aviso y llegada para tiempo de respuesta.',
        ], $operatorId);
        $this->entryIds[] = $entryId;

        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $calculated = $statsService->calculatePoliceResponseTimes($monthStart, $monthEnd);
        $match = null;
        foreach ($calculated as $row) {
            if ((int) ($row['entry_id'] ?? 0) === $entryId) {
                $match = $row;
                break;
            }
        }

        $this->assertTrue($match !== null, 'Integración calcula el incidente de prueba');
        $this->assertSame(1800, $match['response_seconds'] ?? null, '09:10 a 09:40 = 30 min en integración');

        $summary = $statsService->presentPoliceResponseTime($monthStart, $monthEnd);
        $this->assertTrue($summary['eligible_count'] >= 1, 'Resumen incluye casos calculables');

        $shiftService->close($shiftId, 'Cierre tras prueba de tiempo de respuesta');
    }

    private function cleanup(): void
    {
        $pdo = Database::connection();

        foreach ($this->entryIds as $entryId) {
            $pdo->prepare('DELETE FROM cctv_log_contacts WHERE cctv_log_entry_id = ?')->execute([$entryId]);
            $pdo->prepare('DELETE FROM cctv_log_entries WHERE id = ?')->execute([$entryId]);
        }

        foreach ($this->shiftIds as $shiftId) {
            $pdo->prepare('DELETE FROM cctv_shift_equipment_checks WHERE cctv_shift_id = ?')->execute([$shiftId]);
            $pdo->prepare('DELETE FROM cctv_log_entries WHERE cctv_shift_id = ?')->execute([$shiftId]);
            $pdo->prepare('DELETE FROM cctv_shifts WHERE id = ?')->execute([$shiftId]);
        }
    }

    private function assertArrayHasKey(string $key, array $array, string $message): void
    {
        if (array_key_exists($key, $array)) {
            $this->pass($message);
        } else {
            $this->fail($message, 'Falta clave ' . $key);
        }
    }

    private function assertTrue(bool $condition, string $message): void
    {
        if ($condition) {
            $this->pass($message);
        } else {
            $this->fail($message, 'Condición falsa');
        }
    }

    private function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected === $actual) {
            $this->pass($message);
        } else {
            $this->fail($message, 'Esperado ' . var_export($expected, true) . ', obtenido ' . var_export($actual, true));
        }
    }

    private function pass(string $message): void
    {
        $this->passed++;
        echo '  PASS  ' . $message . PHP_EOL;
    }

    private function fail(string $context, string $detail): void
    {
        $this->failed++;
        $this->failures[] = $context . ' — ' . $detail;
        echo '  FAIL  ' . $context . ' — ' . $detail . PHP_EOL;
    }

    private function printSummary(): void
    {
        echo PHP_EOL;
        echo 'Resultado: ' . $this->passed . '/' . ($this->passed + $this->failed) . ' pruebas OK';
        if ($this->failed > 0) {
            echo ', ' . $this->failed . ' fallidas' . PHP_EOL;
            foreach ($this->failures as $failure) {
                echo '  - ' . $failure . PHP_EOL;
            }
        } else {
            echo PHP_EOL;
        }
    }
}

exit((new CctvStatisticsFunctionalTests())->run());
