<?php

declare(strict_types=1);

/**
 * Pruebas funcionales de recepción de puesto CCTV.
 * Ejecutar: php tests/cctv_shift_reception_functional.php
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

use App\Models\Cctv\ShiftEquipmentCheck;
use App\Repositories\Cctv\EquipmentRepository;
use App\Services\AuditService;
use App\Services\Cctv\CctvAuditService;
use App\Services\Cctv\EquipmentCheckCatalog;
use App\Services\Cctv\ShiftService;
use App\Validators\Cctv\ShiftReceptionValidator;
use Core\Auth;
use Core\Database;
use Core\Permission;
use Core\Request;
use Core\Session;

final class CctvShiftReceptionFunctionalTests
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
            $this->testEquipmentSeed();
            $this->testCatalogStatuses();
            $this->testValidatorRequiresAllEquipment();
            $this->testOpenWithReception();
            $this->testCloseWithDelivery();
            $this->testReceptionAuditTrail();
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

    private function testEquipmentSeed(): void
    {
        $items = (new EquipmentRepository())->listActive();
        $names = array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $items);

        $this->assertTrue(count($items) >= 5, 'Existen equipos activos de recepción');
        $this->assertTrue(in_array('Celular', $names, true), 'Seed incluye Celular');
        $this->assertTrue(in_array('Computador', $names, true), 'Seed incluye Computador');
        $this->assertTrue(in_array('Monitores', $names, true), 'Seed incluye Monitores');
        $this->assertTrue(in_array('Joystick', $names, true), 'Seed incluye Joystick');
        $this->assertTrue(in_array('Sistema CCTV', $names, true), 'Seed incluye Sistema CCTV');
    }

    private function testCatalogStatuses(): void
    {
        $this->assertTrue(EquipmentCheckCatalog::isValidStatus(ShiftEquipmentCheck::STATUS_OPERATIONAL), 'Estado operativo es válido');
        $this->assertTrue(EquipmentCheckCatalog::isValidStatus(ShiftEquipmentCheck::STATUS_WITH_OBSERVATIONS), 'Estado con observaciones es válido');
        $this->assertTrue(EquipmentCheckCatalog::isValidStatus(ShiftEquipmentCheck::STATUS_NON_OPERATIONAL), 'Estado no operativo es válido');
        $this->assertSame('Recepción', EquipmentCheckCatalog::phaseLabel(ShiftEquipmentCheck::PHASE_OPENING), 'Etiqueta de fase opening');
        $this->assertSame('Entrega', EquipmentCheckCatalog::phaseLabel(ShiftEquipmentCheck::PHASE_CLOSING), 'Etiqueta de fase closing');
    }

    private function testValidatorRequiresAllEquipment(): void
    {
        $items = (new EquipmentRepository())->listActive();
        $validator = new ShiftReceptionValidator($items);
        $errors = $validator->validate(['equipment' => []]);

        $this->assertTrue($errors !== [], 'Validador exige estado por equipo');
        $this->assertTrue(isset($errors['equipment.' . $items[0]['id'] . '.status']), 'Validador señala equipo sin estado');
    }

    private function testOpenWithReception(): void
    {
        $service = new ShiftService();
        $items = (new EquipmentRepository())->listActive();
        $equipmentPayload = [];

        foreach ($items as $index => $item) {
            $equipmentId = (int) $item['id'];
            if ($index === 1) {
                $equipmentPayload[$equipmentId] = [
                    'status' => ShiftEquipmentCheck::STATUS_WITH_OBSERVATIONS,
                    'observations' => 'Pantalla con brillo irregular',
                ];
                continue;
            }

            $equipmentPayload[$equipmentId] = [
                'status' => ShiftEquipmentCheck::STATUS_OPERATIONAL,
                'observations' => '',
            ];
        }

        $shiftId = $service->openWithReception([
            'opening_notes' => 'Se recibe celular y equipos en buen estado, salvo observación en computador.',
            'equipment' => $equipmentPayload,
        ], Auth::id());
        $this->shiftIds[] = $shiftId;

        $shift = $service->find($shiftId);
        $this->assertSame('open', $shift['status'], 'Recepción abre el turno');
        $this->assertSame(Auth::id(), (int) $shift['operator_id'], 'Turno pertenece al operador autenticado');
        $this->assertStringContainsString('buen estado', (string) ($shift['opening_notes'] ?? ''), 'Guarda observaciones generales');

        $checks = $service->listEquipmentChecks($shiftId, ShiftEquipmentCheck::PHASE_OPENING);
        $this->assertSame(count($items), count($checks), 'Registra revisión opening por cada equipo');
        $this->assertSame('Computador', $checks[1]['equipment_name'] ?? null, 'Checks incluyen nombre del equipo');

        $withObservation = array_values(array_filter(
            $checks,
            static fn (array $row): bool => ($row['status'] ?? '') === ShiftEquipmentCheck::STATUS_WITH_OBSERVATIONS
        ));
        $this->assertSame(1, count($withObservation), 'Persiste equipo con observaciones');
        $this->assertSame('Pantalla con brillo irregular', $withObservation[0]['observations'] ?? null, 'Persiste detalle del equipo');

        $dashboard = $service->dashboardForOperator(Auth::id());
        $this->assertSame($shiftId, (int) ($dashboard['open_shift']['id'] ?? 0), 'Dashboard expone turno con recepción');
        $this->assertSame(count($items), count($dashboard['opening_checks']), 'Dashboard incluye checks de recepción');

        $service->closeWithDelivery($shiftId, [
            'closing_notes' => 'Entrega de prueba recepción',
            'equipment' => $equipmentPayload,
        ], Auth::id());
    }

    private function testCloseWithDelivery(): void
    {
        $service = new ShiftService();
        $items = (new EquipmentRepository())->listActive();
        $equipmentPayload = [];

        foreach ($items as $index => $item) {
            $equipmentId = (int) $item['id'];
            if ($index === 3) {
                $equipmentPayload[$equipmentId] = [
                    'status' => ShiftEquipmentCheck::STATUS_NON_OPERATIONAL,
                    'observations' => 'Joystick sin respuesta',
                ];
                continue;
            }

            $equipmentPayload[$equipmentId] = [
                'status' => ShiftEquipmentCheck::STATUS_OPERATIONAL,
                'observations' => '',
            ];
        }

        $shiftId = $service->openWithReception([
            'opening_notes' => 'Recepción previa a entrega',
            'equipment' => $equipmentPayload,
        ], Auth::id());
        $this->shiftIds[] = $shiftId;

        $summaryBeforeClose = $service->closingSummary($service->find($shiftId));
        $this->assertTrue($summaryBeforeClose['started_time'] !== '—', 'Resumen expone hora de inicio');
        $this->assertSame(0, $summaryBeforeClose['total_entries'], 'Resumen cuenta registros del turno');
        $this->assertSame(0, $summaryBeforeClose['general_entries'], 'Resumen incluye novedades');

        $service->closeWithDelivery($shiftId, [
            'closing_notes' => 'Se entrega puesto con joystick dañado.',
            'equipment' => $equipmentPayload,
        ], Auth::id());

        $closed = $service->find($shiftId);
        $this->assertSame('closed', $closed['status'], 'Entrega cierra el turno');
        $this->assertStringContainsString('joystick dañado', (string) ($closed['closing_notes'] ?? ''), 'Guarda observaciones de entrega');

        $closingChecks = $service->listEquipmentChecks($shiftId, ShiftEquipmentCheck::PHASE_CLOSING);
        $this->assertSame(count($items), count($closingChecks), 'Registra revisión closing por cada equipo');
        $this->assertSame('closing', $closingChecks[0]['check_phase'] ?? null, 'Checks de entrega usan fase closing');

        $nonOperational = array_values(array_filter(
            $closingChecks,
            static fn (array $row): bool => ($row['status'] ?? '') === ShiftEquipmentCheck::STATUS_NON_OPERATIONAL
        ));
        $this->assertSame(1, count($nonOperational), 'Persiste equipo no operativo en entrega');
        $this->assertSame('Joystick sin respuesta', $nonOperational[0]['observations'] ?? null, 'Persiste detalle del equipo en entrega');

        $openingChecks = $service->listEquipmentChecks($shiftId, ShiftEquipmentCheck::PHASE_OPENING);
        $this->assertSame(count($items), count($openingChecks), 'Recepción opening se conserva al cerrar');
    }

    private function testReceptionAuditTrail(): void
    {
        $service = new ShiftService();
        $items = (new EquipmentRepository())->listActive();
        $equipmentPayload = [];

        foreach ($items as $item) {
            $equipmentPayload[(int) $item['id']] = [
                'status' => ShiftEquipmentCheck::STATUS_OPERATIONAL,
                'observations' => '',
            ];
        }

        $shiftId = $service->openWithReception([
            'opening_notes' => 'Recepción auditada',
            'equipment' => $equipmentPayload,
        ], Auth::id());
        $this->shiftIds[] = $shiftId;

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT new_values FROM audit_logs
             WHERE resource = :resource AND resource_id = :id AND action = :action
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([
            'resource' => AuditService::RESOURCE_CCTV_SHIFT,
            'id' => (string) $shiftId,
            'action' => AuditService::ACTION_CREATED,
        ]);
        $payload = json_decode((string) $stmt->fetchColumn(), true);

        $this->assertTrue(is_array($payload), 'Auditoría guarda payload del turno');
        $this->assertSame(CctvAuditService::EVENT_SHIFT_OPENED, $payload['cctv_event'] ?? null, 'Auditoría marca inicio de turno');
        $this->assertSame('Recepción auditada', $payload['opening_notes_excerpt'] ?? null, 'Auditoría incluye extracto de observaciones generales');
        $this->assertTrue(!isset($payload['opening_notes']), 'Auditoría no incluye notas completas');
        $this->assertTrue(isset($payload['equipment_checks']) && is_array($payload['equipment_checks']), 'Auditoría incluye checks de equipos');
        $this->assertSame(count($items), count($payload['equipment_checks']), 'Auditoría registra todos los equipos');

        $service->close($shiftId);
    }

    private function cleanup(): void
    {
        $pdo = Database::connection();

        foreach (array_reverse($this->shiftIds) as $id) {
            $pdo->prepare('DELETE FROM cctv_shift_equipment_checks WHERE cctv_shift_id = :id')->execute(['id' => $id]);
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

    private function assertSame(mixed $expected, mixed $actual, string $label): void
    {
        if ($expected === $actual) {
            $this->pass($label);
            return;
        }

        $this->fail($label, 'esperado ' . var_export($expected, true) . ' / obtenido ' . var_export($actual, true));
    }

    private function assertStringContainsString(string $needle, string $haystack, string $label): void
    {
        if (str_contains($haystack, $needle)) {
            $this->pass($label);
            return;
        }

        $this->fail($label, 'no se encontró ' . var_export($needle, true));
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

exit((new CctvShiftReceptionFunctionalTests())->run());
