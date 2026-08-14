<?php

declare(strict_types=1);

/**
 * Pruebas funcionales de turnos CCTV.
 * Ejecutar: php tests/cctv_shifts_functional.php
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

use App\Models\Cctv\Shift;
use App\Exceptions\Cctv\OpenShiftAlreadyExistsException;
use App\Services\AuditService;
use App\Services\Cctv\LogEntryService;
use App\Services\Cctv\ShiftService;
use Core\Auth;
use Core\Database;
use Core\Permission;
use Core\Request;
use Core\Session;

final class CctvShiftsFunctionalTests
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var list<string> */
    private array $failures = [];

    /** @var list<int> */
    private array $shiftIds = [];

    public function run(): int
    {
        $this->boot();

        try {
            $this->testModelStatuses();
            $this->testOpenCloseShift();
            $this->testOperatorManyShifts();
            $this->testSearchHistoryPagination();
            $this->testShiftDetailForView();
            $this->testSupervisionDashboard();
            $this->testSingleOpenShiftPerOperator();
            $this->testDashboardContext();
            $this->testAuditTrail();
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

        $service = new ShiftService();
        $open = $service->findOpenForOperator($adminId);
        if ($open !== null) {
            $service->close((int) $open['id'], 'Limpieza previa a pruebas');
        }
    }

    private function testModelStatuses(): void
    {
        $this->assertTrue(Shift::isValidStatus(Shift::STATUS_OPEN), 'Estado open es válido');
        $this->assertTrue(Shift::isValidStatus(Shift::STATUS_CLOSED), 'Estado closed es válido');
        $this->assertFalse(Shift::isValidStatus('invalido'), 'Estado desconocido se rechaza');
        $this->assertTrue(Shift::isOpen(Shift::STATUS_OPEN), 'Helper isOpen reconoce open');
        $this->assertTrue(Shift::isClosed(Shift::STATUS_CLOSED), 'Helper isClosed reconoce closed');
    }

    private function testOpenCloseShift(): void
    {
        $service = new ShiftService();
        $operatorId = Auth::id();

        $id = $service->open([
            'shift_date' => date('Y-m-d'),
            'opening_notes' => 'Inicio de turno de prueba',
        ], $operatorId);
        $this->shiftIds[] = $id;
        $this->assertTrue($id > 0, 'Open crea un turno');

        $record = $service->find($id);
        $this->assertSame(Shift::STATUS_OPEN, $record['status'], 'Turno nuevo queda abierto');
        $this->assertSame($operatorId, (int) $record['operator_id'], 'Turno pertenece al operador');
        $this->assertSame('Abierto', $record['status_label'], 'Present incluye etiqueta de estado');
        $this->assertTrue($record['is_open'], 'Present marca turno abierto');

        $service->close($id, 'Cierre de turno de prueba');
        $closed = $service->find($id);
        $this->assertSame(Shift::STATUS_CLOSED, $closed['status'], 'Close cambia estado a closed');
        $this->assertTrue($closed['is_closed'], 'Present marca turno cerrado');
        $this->assertSame('Cierre de turno de prueba', $closed['closing_notes'], 'Close persiste notas');
        $this->assertNotSame('—', $closed['ended_at_formatted'], 'Close registra hora de término');
    }

    private function testOperatorManyShifts(): void
    {
        $service = new ShiftService();
        $operatorId = Auth::id();

        $first = $service->open(['shift_date' => date('Y-m-d', strtotime('-1 day'))], $operatorId);
        $this->shiftIds[] = $first;
        $service->close($first, 'Turno anterior');

        $second = $service->open(['shift_date' => date('Y-m-d')], $operatorId);
        $this->shiftIds[] = $second;
        $service->close($second, 'Turno actual');

        $history = $service->listForOperator($operatorId, 10);
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $history);

        $this->assertTrue(in_array($first, $ids, true), 'Historial incluye primer turno');
        $this->assertTrue(in_array($second, $ids, true), 'Historial incluye segundo turno');

        $paged = $service->paginateForOperator($operatorId, 1, 5);
        $this->assertTrue($paged['total'] >= 2, 'Paginación cuenta múltiples turnos del operador');
    }

    private function testSearchHistoryPagination(): void
    {
        $service = new ShiftService();
        $operatorId = Auth::id();
        $pdo = Database::connection();

        $shiftId = $service->open(['shift_date' => date('Y-m-d')], $operatorId);
        $this->shiftIds[] = $shiftId;

        $logTypeId = (int) $pdo->query(
            "SELECT id FROM cctv_log_types WHERE slug = 'novedad' AND deleted_at IS NULL LIMIT 1"
        )->fetchColumn();

        $expectedEntries = 0;
        if ($logTypeId > 0) {
            $logService = new LogEntryService();
            $logService->createForOpenShift([
                'event_date' => date('Y-m-d'),
                'event_time' => date('H:i'),
                'log_type_id' => $logTypeId,
                'observations' => 'Entrada para historial de turnos.',
            ], $operatorId);
            $expectedEntries = 1;
        }

        $service->close($shiftId, 'Cierre historial');

        $result = $service->searchHistory([
            'operator_id' => (string) $operatorId,
            'date_from' => date('Y-m-d'),
            'date_to' => date('Y-m-d'),
            'status' => Shift::STATUS_CLOSED,
        ], 1, 10);

        $this->assertTrue($result['total'] >= 1, 'Historial paginado cuenta turnos filtrados');
        $this->assertTrue($result['pages'] >= 1, 'Historial calcula páginas');
        $this->assertSame($expectedEntries, (int) ($result['data'][0]['total_entries'] ?? -1), 'Historial incluye conteo de registros');
        $this->assertSame('Cerrado', $result['data'][0]['status_label'] ?? null, 'Historial incluye estado presentado');
        $this->assertTrue(
            ($result['data'][0]['duration_label'] ?? '—') !== '—',
            'Historial incluye duración del turno cerrado'
        );
    }

    private function testShiftDetailForView(): void
    {
        $service = new ShiftService();
        $operatorId = Auth::id();
        $pdo = Database::connection();

        $shiftId = $service->open([
            'shift_date' => date('Y-m-d'),
            'opening_notes' => 'Recepción de prueba para detalle.',
        ], $operatorId);
        $this->shiftIds[] = $shiftId;

        $incidentTypeId = (int) $pdo->query(
            "SELECT id FROM cctv_log_types WHERE slug = 'incidente' AND deleted_at IS NULL LIMIT 1"
        )->fetchColumn();

        if ($incidentTypeId > 0) {
            $logService = new LogEntryService();
            $logService->createForOpenShift([
                'event_date' => date('Y-m-d'),
                'event_time' => date('H:i'),
                'log_type_id' => $incidentTypeId,
                'observations' => 'Incidente de prueba para detalle de turno.',
            ], $operatorId);
        }

        $service->close($shiftId, 'Observaciones de cierre para detalle.');

        $detail = $service->detailForView($shiftId, $operatorId, 'asc');
        $kinds = array_map(
            static fn (array $item): string => (string) ($item['kind'] ?? ''),
            $detail['timeline']['items']
        );

        $this->assertSame($shiftId, (int) ($detail['shift']['id'] ?? 0), 'Detalle expone el turno consultado');
        $this->assertSame('Recepción de prueba para detalle.', $detail['shift']['opening_notes'] ?? null, 'Detalle incluye observaciones de recepción');
        $this->assertSame('Observaciones de cierre para detalle.', $detail['shift']['closing_notes'] ?? null, 'Detalle incluye observaciones de cierre');
        $this->assertTrue(in_array('shift_opening', $kinds, true), 'Timeline incluye inicio de turno');
        $this->assertTrue(in_array('shift_closing', $kinds, true), 'Timeline incluye cierre de turno');
        $this->assertSame('asc', $detail['timeline']['order'] ?? null, 'Timeline respeta orden cronológico');

        if ($incidentTypeId > 0) {
            $this->assertTrue($detail['stats']['incidents'] >= 1, 'Detalle cuenta incidentes del turno');
            $this->assertTrue($detail['incidents'] !== [], 'Detalle lista incidentes del turno');
        }
    }

    private function testSupervisionDashboard(): void
    {
        $service = new ShiftService();
        $logService = new LogEntryService();
        $operatorId = Auth::id();

        $open = $service->findOpenForOperator($operatorId);
        if ($open !== null) {
            $service->close((int) $open['id'], 'Cierre previo a supervisión');
        }

        $shiftId = $service->open(['shift_date' => date('Y-m-d')], $operatorId);
        $this->shiftIds[] = $shiftId;

        $pdo = Database::connection();
        $incidentLogTypeId = (int) $pdo->query(
            "SELECT id FROM cctv_log_types WHERE slug = 'incidente' AND deleted_at IS NULL LIMIT 1"
        )->fetchColumn();

        if ($incidentLogTypeId > 0) {
            $logService->createForOpenShift([
                'event_date' => date('Y-m-d'),
                'event_time' => date('H:i'),
                'log_type_id' => $incidentLogTypeId,
                'observations' => 'Registro de supervisión para dashboard.',
            ], $operatorId);
        }

        $dashboard = $service->supervisionDashboard(2, 5);

        $this->assertTrue($dashboard['open_shift'] !== null, 'Supervisión expone turno abierto');
        $this->assertSame($shiftId, (int) ($dashboard['open_shift']['id'] ?? 0), 'Supervisión muestra el turno más reciente');
        $this->assertTrue($dashboard['open_shifts_count'] >= 1, 'Supervisión cuenta turnos abiertos');
        $this->assertSame(date('Y-m-d'), $dashboard['today'] ?? null, 'Supervisión usa fecha de hoy');
        $this->assertTrue($dashboard['today_stats']['total_entries'] >= 1, 'Supervisión cuenta actividad de hoy');
        $this->assertTrue(isset($dashboard['month_stats']['incidents']), 'Supervisión expone incidentes del mes');
        $this->assertTrue(is_array($dashboard['shifts_activity'] ?? null), 'Supervisión incluye registros por turno');
        $this->assertTrue($dashboard['recent_entries'] !== [], 'Supervisión incluye últimas novedades');

        $service->close($shiftId, 'Cierre tras prueba de supervisión');
    }

    private function testSingleOpenShiftPerOperator(): void
    {
        $service = new ShiftService();
        $operatorId = Auth::id();

        $openId = $service->open(['shift_date' => date('Y-m-d')], $operatorId);
        $this->shiftIds[] = $openId;

        $blocked = false;
        $blockedShiftId = 0;
        try {
            $service->open(['shift_date' => date('Y-m-d')], $operatorId);
        } catch (OpenShiftAlreadyExistsException $e) {
            $blocked = true;
            $blockedShiftId = $e->getShiftId();
        }

        $this->assertTrue($blocked, 'No se permiten dos turnos abiertos para el mismo operador');
        $this->assertSame($openId, $blockedShiftId, 'La excepción expone el turno abierto existente');
        $this->assertSame('Ya posee un turno CCTV abierto.', (new OpenShiftAlreadyExistsException($openId))->getMessage(), 'Mensaje de turno duplicado');
        $this->assertSame($openId, (int) ($service->findOpenForOperator($operatorId)['id'] ?? 0), 'findOpenForOperator devuelve el turno activo');

        $service->close($openId);
    }

    private function testDashboardContext(): void
    {
        $service = new ShiftService();
        $operatorId = Auth::id();

        $first = $service->open(['shift_date' => date('Y-m-d')], $operatorId);
        $this->shiftIds[] = $first;
        $service->close($first, 'Turno histórico');

        $withoutOpen = $service->dashboardForOperator($operatorId);
        $this->assertNull($withoutOpen['open_shift'], 'Dashboard sin turno abierto');
        $this->assertNotNull($withoutOpen['last_shift'], 'Dashboard expone último turno cerrado');
        $this->assertSame($first, (int) ($withoutOpen['last_shift']['id'] ?? 0), 'Último turno es el más reciente cerrado');
        $this->assertTrue($withoutOpen['can_start'], 'Superadmin puede iniciar turno sin uno abierto');
        $this->assertSame('Cerrado', $withoutOpen['last_shift']['status_label'] ?? null, 'Último turno incluye estado');
        $this->assertNotSame('—', $withoutOpen['last_shift']['shift_date_formatted'] ?? '—', 'Último turno incluye fecha formateada');

        $openId = $service->open(['shift_date' => date('Y-m-d')], $operatorId);
        $this->shiftIds[] = $openId;

        $withOpen = $service->dashboardForOperator($operatorId);
        $this->assertSame($openId, (int) ($withOpen['open_shift']['id'] ?? 0), 'Dashboard expone turno abierto');
        $this->assertFalse($withOpen['can_start'], 'No se puede iniciar turno si ya hay uno abierto');

        $service->close($openId);
    }

    private function testAuditTrail(): void
    {
        $service = new ShiftService();
        $operatorId = Auth::id();

        $id = $service->open(['shift_date' => date('Y-m-d')], $operatorId);
        $this->shiftIds[] = $id;

        $pdo = Database::connection();
        $created = $pdo->prepare(
            'SELECT action, module, resource FROM audit_logs
             WHERE resource = :resource AND resource_id = :id
             ORDER BY id DESC LIMIT 1'
        );
        $created->execute([
            'resource' => AuditService::RESOURCE_CCTV_SHIFT,
            'id' => (string) $id,
        ]);
        $row = $created->fetch();

        $this->assertSame('created', $row['action'] ?? null, 'Open registra auditoría');
        $this->assertSame(AuditService::MODULE_CCTV, $row['module'] ?? null, 'Auditoría usa módulo cctv');

        $service->close($id);

        $updated = $pdo->prepare(
            'SELECT action FROM audit_logs
             WHERE resource = :resource AND resource_id = :id AND action = :action
             ORDER BY id DESC LIMIT 1'
        );
        $updated->execute([
            'resource' => AuditService::RESOURCE_CCTV_SHIFT,
            'id' => (string) $id,
            'action' => 'updated',
        ]);

        $this->assertSame('updated', $updated->fetchColumn(), 'Close registra auditoría');
    }

    private function cleanup(): void
    {
        $pdo = Database::connection();

        foreach (array_reverse($this->shiftIds) as $id) {
            $pdo->prepare('DELETE FROM cctv_log_entries WHERE cctv_shift_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM cctv_shifts WHERE id = :id')->execute(['id' => $id]);
        }
    }

    private function assertTrue(bool $condition, string $label): void
    {
        if ($condition) {
            $this->pass($label);
            return;
        }

        $this->fail($label, 'se esperaba verdadero');
    }

    private function assertFalse(bool $condition, string $label): void
    {
        $this->assertTrue(!$condition, $label);
    }

    private function assertSame(mixed $expected, mixed $actual, string $label): void
    {
        if ($expected === $actual) {
            $this->pass($label);
            return;
        }

        $this->fail($label, 'esperado ' . var_export($expected, true) . ' / obtenido ' . var_export($actual, true));
    }

    private function assertNotSame(mixed $expected, mixed $actual, string $label): void
    {
        if ($expected !== $actual) {
            $this->pass($label);
            return;
        }

        $this->fail($label, 'se esperaba un valor distinto de ' . var_export($expected, true));
    }

    private function assertNull(mixed $actual, string $label): void
    {
        if ($actual === null) {
            $this->pass($label);
            return;
        }

        $this->fail($label, 'se esperaba null / obtenido ' . var_export($actual, true));
    }

    private function assertNotNull(mixed $actual, string $label): void
    {
        if ($actual !== null) {
            $this->pass($label);
            return;
        }

        $this->fail($label, 'se esperaba un valor no nulo');
    }

    private function pass(string $label): void
    {
        $this->passed++;
        echo '  PASS  ' . $label . PHP_EOL;
    }

    private function fail(string $label, string $detail): void
    {
        $this->failed++;
        $this->failures[] = $label . ' — ' . $detail;
        echo '  FAIL  ' . $label . ' — ' . $detail . PHP_EOL;
    }

    private function printSummary(): void
    {
        $total = $this->passed + $this->failed;
        echo PHP_EOL . "Resultado: {$this->passed}/{$total} pruebas OK";
        if ($this->failed > 0) {
            echo ", {$this->failed} fallidas";
        }
        echo PHP_EOL;

        foreach ($this->failures as $failure) {
            echo '  - ' . $failure . PHP_EOL;
        }
    }
}

exit((new CctvShiftsFunctionalTests())->run());
