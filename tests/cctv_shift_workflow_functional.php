<?php

declare(strict_types=1);

/**
 * Pruebas integradas del ciclo operativo de turnos CCTV.
 * Ejecutar: php tests/cctv_shift_workflow_functional.php
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

use App\Exceptions\Cctv\OpenShiftAlreadyExistsException;
use App\Models\Cctv\LogEntry;
use App\Models\Cctv\Shift;
use App\Models\Cctv\ShiftEquipmentCheck;
use App\Repositories\Cctv\EquipmentRepository;
use App\Services\AuditService;
use App\Services\Cctv\CctvAuditService;
use App\Services\Cctv\LogEntryService;
use App\Services\Cctv\ShiftService;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Permission;
use Core\Request;
use Core\Session;

final class CctvShiftWorkflowFunctionalTests
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var list<string> */
    private array $failures = [];

    /** @var list<int> */
    private array $shiftIds = [];

    /** @var list<int> */
    private array $entryIds = [];

    /** @var list<int> */
    private array $tempUserIds = [];

    private int $adminId = 0;

    /** @var array<string, int|null> */
    private array $catalog = [];

    public function run(): int
    {
        $this->boot();

        try {
            $this->testStartShiftWithReception();
            $this->testPreventDoubleOpenShift();
            $this->testCreateNovedadDuringOpenShift();
            $this->testCreateIncidentDuringOpenShift();
            $this->testCloseShiftWithDelivery();
            $this->testBlockMutationsOnClosedShift();
            $this->testAllowMutationsWithSpecialPermission();
            $this->testWorkflowAuditTrail();
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
        $this->adminId = (int) $pdo->query(
            "SELECT u.id FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.slug = 'superadministrador' AND u.is_active = 1
             LIMIT 1"
        )->fetchColumn();

        if ($this->adminId < 1) {
            throw new RuntimeException('No hay un superadministrador activo para las pruebas.');
        }

        Session::put('auth_user_id', $this->adminId);
        Auth::forgetCache();
        Permission::flush();

        $this->catalog = [
            'novedad_type_id' => (int) $pdo->query(
                "SELECT id FROM cctv_log_types WHERE slug = 'novedad' AND deleted_at IS NULL LIMIT 1"
            )->fetchColumn(),
            'incident_type_id' => (int) $pdo->query(
                "SELECT id FROM cctv_incident_types WHERE slug = 'rina_via_publica' AND deleted_at IS NULL LIMIT 1"
            )->fetchColumn(),
        ];

        $service = new ShiftService();
        $open = $service->findOpenForOperator($this->adminId);
        if ($open !== null) {
            $service->close((int) $open['id'], 'Limpieza previa a pruebas de flujo');
        }
    }

    private function testStartShiftWithReception(): void
    {
        $service = new ShiftService();
        $items = (new EquipmentRepository())->listActive();
        $equipmentPayload = $this->operationalEquipmentPayload($items);

        $shiftId = $service->openWithReception([
            'opening_notes' => 'Recepción completa del puesto operativo.',
            'equipment' => $equipmentPayload,
        ], $this->adminId);
        $this->shiftIds[] = $shiftId;

        $shift = $service->find($shiftId);
        $this->assertSame(Shift::STATUS_OPEN, $shift['status'], 'Inicio de turno deja estado open');
        $this->assertSame($this->adminId, (int) ($shift['operator_id'] ?? 0), 'Turno inicia para el operador autenticado');
        $this->assertStringContainsString('Recepción completa', (string) ($shift['opening_notes'] ?? ''), 'Recepción guarda observaciones generales');

        $checks = $service->listEquipmentChecks($shiftId, ShiftEquipmentCheck::PHASE_OPENING);
        $this->assertSame(count($items), count($checks), 'Recepción registra checklist opening por equipo');

        $service->close($shiftId, 'Cierre auxiliar tras prueba de recepción');
    }

    private function testPreventDoubleOpenShift(): void
    {
        $service = new ShiftService();

        $firstId = $service->openWithReception([
            'opening_notes' => 'Primer turno abierto',
            'equipment' => $this->operationalEquipmentPayload((new EquipmentRepository())->listActive()),
        ], $this->adminId);
        $this->shiftIds[] = $firstId;

        $blocked = false;
        $existingId = 0;

        try {
            $service->openWithReception([
                'opening_notes' => 'Segundo turno no permitido',
                'equipment' => $this->operationalEquipmentPayload((new EquipmentRepository())->listActive()),
            ], $this->adminId);
        } catch (OpenShiftAlreadyExistsException $e) {
            $blocked = true;
            $existingId = $e->getShiftId();
        }

        $this->assertTrue($blocked, 'Evita doble turno abierto del mismo operador');
        $this->assertSame($firstId, $existingId, 'La excepción referencia el turno abierto existente');

        $service->close($firstId, 'Cierre tras prueba de doble turno');
    }

    private function testCreateNovedadDuringOpenShift(): void
    {
        $shiftService = new ShiftService();
        $logService = new LogEntryService();
        $shiftId = $this->openShiftWithReception($shiftService);

        $this->assertTrue($this->catalog['novedad_type_id'] > 0, 'Existe tipo de novedad para la prueba');

        $entryId = $logService->createForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'log_type_id' => $this->catalog['novedad_type_id'],
            'observations' => 'Novedad operativa durante turno abierto.',
            'status' => LogEntry::STATUS_REGISTERED,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $entry = $logService->find($entryId);
        $this->assertSame($shiftId, (int) ($entry['shift_id'] ?? 0), 'Novedad queda asociada al turno abierto');
        $this->assertStringContainsString('Novedad operativa', (string) ($entry['observations'] ?? ''), 'Novedad persiste observaciones');

        $stats = (new ShiftService())->closingSummary($shiftService->find($shiftId));
        $this->assertTrue($stats['general_entries'] >= 1, 'Turno cuenta la novedad registrada');
    }

    private function testCreateIncidentDuringOpenShift(): void
    {
        $shiftService = new ShiftService();
        $logService = new LogEntryService();
        $this->openShiftWithReception($shiftService);

        $this->assertTrue($this->catalog['incident_type_id'] > 0, 'Existe tipo de incidente para la prueba');

        $entryId = $logService->createIncidentForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'incident_type_id' => $this->catalog['incident_type_id'],
            'observations' => 'Incidente registrado durante turno operativo.',
            'coordination_notified' => 0,
            'police_arrived' => LogEntry::POLICE_ARRIVED_NOT_APPLICABLE,
            'status' => LogEntry::STATUS_REGISTERED,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $entry = $logService->find($entryId);
        $this->assertSame('incidente', $entry['log_type_slug'] ?? null, 'Incidente usa tipo de registro incidente');
        $this->assertStringContainsString('Incidente registrado', (string) ($entry['observations'] ?? ''), 'Incidente persiste observaciones');
    }

    private function testCloseShiftWithDelivery(): void
    {
        $service = new ShiftService();
        $logService = new LogEntryService();
        $items = (new EquipmentRepository())->listActive();
        $equipmentPayload = $this->operationalEquipmentPayload($items);

        $shiftId = $this->openShiftWithReception($service);
        $logService->createForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'log_type_id' => $this->catalog['novedad_type_id'],
            'observations' => 'Entrada previa al cierre.',
            'status' => LogEntry::STATUS_REGISTERED,
        ], $this->adminId);

        $summary = $service->closingSummary($service->find($shiftId));
        $this->assertTrue($summary['total_entries'] >= 1, 'Resumen de cierre incluye registros del turno');

        $service->closeWithDelivery($shiftId, [
            'closing_notes' => 'Entrega del puesto con bitácora completa.',
            'equipment' => $equipmentPayload,
        ], $this->adminId);

        $closed = $service->find($shiftId);
        $this->assertSame(Shift::STATUS_CLOSED, $closed['status'], 'Finalizar turno deja estado closed');
        $this->assertStringContainsString('Entrega del puesto', (string) ($closed['closing_notes'] ?? ''), 'Finalizar turno guarda observaciones de entrega');

        $closingChecks = $service->listEquipmentChecks($shiftId, ShiftEquipmentCheck::PHASE_CLOSING);
        $this->assertSame(count($items), count($closingChecks), 'Finalizar turno registra checklist closing');

        $blockedCreate = false;
        try {
            $logService->createForOpenShift([
                'event_date' => date('Y-m-d'),
                'event_time' => date('H:i'),
                'log_type_id' => $this->catalog['novedad_type_id'],
                'observations' => 'No debe registrarse tras cierre.',
                'status' => LogEntry::STATUS_REGISTERED,
            ], $this->adminId);
        } catch (HttpException $e) {
            $blockedCreate = $e->getStatusCode() === 422;
        }

        $this->assertTrue($blockedCreate, 'No permite novedades después de cerrar el turno');
    }

    private function testBlockMutationsOnClosedShift(): void
    {
        $shiftService = new ShiftService();
        $logService = new LogEntryService();
        $items = (new EquipmentRepository())->listActive();
        $equipmentPayload = $this->operationalEquipmentPayload($items);

        $shiftId = $this->openShiftWithReception($shiftService);
        $entryId = $logService->createForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'log_type_id' => $this->catalog['novedad_type_id'],
            'observations' => 'Entrada para prueba de turno cerrado.',
            'status' => LogEntry::STATUS_REGISTERED,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $shiftService->closeWithDelivery($shiftId, [
            'closing_notes' => 'Cierre para prueba de bloqueo.',
            'equipment' => $equipmentPayload,
        ], $this->adminId);

        $operatorId = $this->loginAsOperadorCamaras();
        $blockedEntryUpdate = false;
        $blockedShiftUpdate = false;

        try {
            $logService->update($entryId, [
                'event_date' => date('Y-m-d'),
                'event_time' => date('H:i'),
                'log_type_id' => $this->catalog['novedad_type_id'],
                'observations' => 'Intento de edición sin permiso especial.',
            ]);
        } catch (HttpException $e) {
            $blockedEntryUpdate = $e->getStatusCode() === 403;
        }

        try {
            $shiftService->update($shiftId, [
                'opening_notes' => 'Intento de modificar turno cerrado.',
            ]);
        } catch (HttpException $e) {
            $blockedShiftUpdate = $e->getStatusCode() === 403;
        }

        $this->assertTrue($blockedEntryUpdate, 'Operador normal no edita registros de turno cerrado');
        $this->assertTrue($blockedShiftUpdate, 'Operador normal no modifica turno cerrado');

        $this->restoreAdminSession();
        $this->deleteTempUser($operatorId);
    }

    private function testAllowMutationsWithSpecialPermission(): void
    {
        $shiftService = new ShiftService();
        $logService = new LogEntryService();
        $items = (new EquipmentRepository())->listActive();
        $equipmentPayload = $this->operationalEquipmentPayload($items);

        $shiftId = $this->openShiftWithReception($shiftService);
        $entryId = $logService->createForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'log_type_id' => $this->catalog['novedad_type_id'],
            'observations' => 'Entrada antes de cierre supervisado.',
            'status' => LogEntry::STATUS_REGISTERED,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $shiftService->closeWithDelivery($shiftId, [
            'closing_notes' => 'Cierre supervisado.',
            'equipment' => $equipmentPayload,
        ], $this->adminId);

        $this->restoreAdminSession();

        $logService->update($entryId, [
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'log_type_id' => $this->catalog['novedad_type_id'],
            'observations' => 'Corrección autorizada en turno cerrado.',
        ]);

        $updated = $logService->find($entryId);
        $this->assertStringContainsString(
            'Corrección autorizada',
            (string) ($updated['observations'] ?? ''),
            'Supervisor con edit_closed puede editar registros de turno cerrado'
        );

        $shiftService->update($shiftId, [
            'opening_notes' => 'Recepción corregida por supervisor.',
        ]);

        $shift = $shiftService->find($shiftId);
        $this->assertStringContainsString(
            'Recepción corregida',
            (string) ($shift['opening_notes'] ?? ''),
            'Supervisor con shifts.edit_closed puede modificar turno cerrado'
        );
    }

    private function testWorkflowAuditTrail(): void
    {
        $pdo = Database::connection();
        $shiftService = new ShiftService();
        $logService = new LogEntryService();
        $items = (new EquipmentRepository())->listActive();
        $equipmentPayload = $this->operationalEquipmentPayload($items);

        $shiftId = $shiftService->openWithReception([
            'opening_notes' => 'Auditoría de flujo completo',
            'equipment' => $equipmentPayload,
        ], $this->adminId);
        $this->shiftIds[] = $shiftId;

        $openPayload = $this->latestAuditPayload(
            AuditService::RESOURCE_CCTV_SHIFT,
            (string) $shiftId,
            AuditService::ACTION_CREATED
        );
        $this->assertSame(CctvAuditService::EVENT_SHIFT_OPENED, $openPayload['cctv_event'] ?? null, 'Auditoría registra apertura de turno');

        $entryId = $logService->createForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'log_type_id' => $this->catalog['novedad_type_id'],
            'observations' => 'Observación larga para auditoría de flujo operativo completo del turno.',
            'status' => LogEntry::STATUS_REGISTERED,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $entryPayload = $this->latestAuditPayload(
            AuditService::RESOURCE_CCTV_LOG_ENTRY,
            (string) $entryId,
            AuditService::ACTION_CREATED
        );
        $this->assertSame(CctvAuditService::EVENT_LOG_ENTRY_CREATED, $entryPayload['cctv_event'] ?? null, 'Auditoría registra creación de novedad');
        $this->assertTrue(isset($entryPayload['observations_excerpt']), 'Auditoría guarda extracto de observaciones');
        $this->assertTrue(!isset($entryPayload['observations']), 'Auditoría no guarda observaciones completas');

        $incidentId = $logService->createIncidentForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'incident_type_id' => $this->catalog['incident_type_id'],
            'observations' => 'Incidente auditado en flujo completo.',
            'coordination_notified' => 0,
            'police_arrived' => LogEntry::POLICE_ARRIVED_NOT_APPLICABLE,
            'status' => LogEntry::STATUS_REGISTERED,
        ], $this->adminId);
        $this->entryIds[] = $incidentId;

        $incidentPayload = $this->latestAuditPayload(
            AuditService::RESOURCE_CCTV_LOG_ENTRY,
            (string) $incidentId,
            AuditService::ACTION_CREATED
        );
        $this->assertSame(CctvAuditService::EVENT_INCIDENT_CREATED, $incidentPayload['cctv_event'] ?? null, 'Auditoría registra creación de incidente');

        $shiftService->closeWithDelivery($shiftId, [
            'closing_notes' => 'Cierre auditado del flujo completo.',
            'equipment' => $equipmentPayload,
        ], $this->adminId);

        $closePayload = $this->latestAuditPayload(
            AuditService::RESOURCE_CCTV_SHIFT,
            (string) $shiftId,
            AuditService::ACTION_UPDATED
        );
        $this->assertSame(CctvAuditService::EVENT_SHIFT_CLOSED, $closePayload['cctv_event'] ?? null, 'Auditoría registra cierre de turno');
        $this->assertTrue(!isset($closePayload['closing_notes']), 'Auditoría de cierre no guarda notas completas');

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM audit_logs
             WHERE module = :module AND resource = :resource AND resource_id = :id'
        );
        $stmt->execute([
            'module' => AuditService::MODULE_CCTV,
            'resource' => AuditService::RESOURCE_CCTV_SHIFT,
            'id' => (string) $shiftId,
        ]);
        $this->assertTrue((int) $stmt->fetchColumn() >= 2, 'Turno genera al menos apertura y cierre en auditoría');
    }

    private function openShiftWithReception(ShiftService $service): int
    {
        $open = $service->findOpenForOperator($this->adminId);
        if ($open !== null) {
            return (int) $open['id'];
        }

        $shiftId = $service->openWithReception([
            'opening_notes' => 'Turno operativo de prueba',
            'equipment' => $this->operationalEquipmentPayload((new EquipmentRepository())->listActive()),
        ], $this->adminId);
        $this->shiftIds[] = $shiftId;

        return $shiftId;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<int, array{status: string, observations: string}>
     */
    private function operationalEquipmentPayload(array $items): array
    {
        $payload = [];

        foreach ($items as $item) {
            $payload[(int) $item['id']] = [
                'status' => ShiftEquipmentCheck::STATUS_OPERATIONAL,
                'observations' => '',
            ];
        }

        return $payload;
    }

    private function loginAsOperadorCamaras(): int
    {
        $pdo = Database::connection();
        $roleId = (int) $pdo->query(
            "SELECT id FROM roles WHERE slug = 'operador_camaras' LIMIT 1"
        )->fetchColumn();

        if ($roleId < 1) {
            throw new RuntimeException('No existe el rol operador_camaras.');
        }

        $email = 'cctv.workflow.' . bin2hex(random_bytes(4)) . '@municipalidad.local';
        $pdo->prepare(
            'INSERT INTO users (name, email, password, is_active, must_change_password)
             VALUES (:name, :email, :password, 1, 0)'
        )->execute([
            'name' => 'Operador flujo CCTV',
            'email' => $email,
            'password' => password_hash('TestCamera123!', PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();
        $this->tempUserIds[] = $userId;

        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
            ->execute(['user_id' => $userId, 'role_id' => $roleId]);

        Session::put('auth_user_id', $userId);
        Auth::forgetCache();
        Permission::flush();

        return $userId;
    }

    private function restoreAdminSession(): void
    {
        Session::put('auth_user_id', $this->adminId);
        Auth::forgetCache();
        Permission::flush();
    }

    private function deleteTempUser(int $userId): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM user_roles WHERE user_id = :id')->execute(['id' => $userId]);
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
        $this->tempUserIds = array_values(array_filter(
            $this->tempUserIds,
            static fn (int $id): bool => $id !== $userId
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function latestAuditPayload(string $resource, string $resourceId, string $action): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT new_values, old_values FROM audit_logs
             WHERE resource = :resource AND resource_id = :id AND action = :action
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([
            'resource' => $resource,
            'id' => $resourceId,
            'action' => $action,
        ]);
        $row = $stmt->fetch();
        $payload = json_decode((string) ($row['new_values'] ?? $row['old_values'] ?? ''), true);

        return is_array($payload) ? $payload : [];
    }

    private function cleanup(): void
    {
        $pdo = Database::connection();

        foreach ($this->entryIds as $entryId) {
            $pdo->prepare('DELETE FROM cctv_log_contacts WHERE cctv_log_entry_id = :id')->execute(['id' => $entryId]);
            $pdo->prepare('DELETE FROM cctv_log_entries WHERE id = :id')->execute(['id' => $entryId]);
        }

        foreach (array_reverse($this->shiftIds) as $shiftId) {
            $pdo->prepare('DELETE FROM cctv_log_entries WHERE cctv_shift_id = :id')->execute(['id' => $shiftId]);
            $pdo->prepare('DELETE FROM cctv_shift_equipment_checks WHERE cctv_shift_id = :id')->execute(['id' => $shiftId]);
            $pdo->prepare('DELETE FROM cctv_shifts WHERE id = :id')->execute(['id' => $shiftId]);
        }

        foreach ($this->tempUserIds as $userId) {
            $pdo->prepare('DELETE FROM user_roles WHERE user_id = :id')->execute(['id' => $userId]);
            $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
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

exit((new CctvShiftWorkflowFunctionalTests())->run());
