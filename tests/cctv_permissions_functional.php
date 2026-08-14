<?php

declare(strict_types=1);

/**
 * Pruebas funcionales de permisos del módulo CCTV.
 * Ejecutar: php tests/cctv_permissions_functional.php
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

use App\Middleware\PermissionMiddleware;
use App\Models\Cctv\LogEntry;
use App\Models\Cctv\Shift;
use App\Models\Cctv\ShiftEquipmentCheck;
use App\Repositories\Cctv\EquipmentRepository;
use App\Services\Cctv\LogEntryService;
use App\Services\Cctv\ShiftService;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Permission;
use Core\Request;
use Core\Session;

final class CctvPermissionsFunctionalTests
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

    /** @var array{novedad_type_id: int, incident_type_id: int|null} */
    private array $catalog = [
        'novedad_type_id' => 0,
        'incident_type_id' => null,
    ];

    public function run(): int
    {
        $this->boot();

        try {
            $this->testUserWithoutCctvGets403();
            $this->testOperadorCanAccessModule();
            $this->testOperadorCanOperateShiftAndLog();
            $this->testOperadorCannotAdminUsers();
            $this->testOperadorCannotAlterHistoricalShift();
            $this->testOperadorCannotViewForeignShift();
            $this->testOperadorCannotManageCameras();
            $this->testSupervisorCanConsultAllowedShifts();
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

        $this->catalog = [
            'novedad_type_id' => (int) $pdo->query(
                "SELECT id FROM cctv_log_types WHERE slug = 'novedad' AND deleted_at IS NULL LIMIT 1"
            )->fetchColumn(),
            'incident_type_id' => (int) $pdo->query(
                "SELECT id FROM cctv_incident_types WHERE slug = 'rina_via_publica' AND deleted_at IS NULL LIMIT 1"
            )->fetchColumn() ?: null,
        ];

        $this->loginAs($this->adminId);
        $this->closeOpenShiftFor($this->adminId);
    }

    private function testUserWithoutCctvGets403(): void
    {
        $userId = $this->createTestUser('senda');

        $this->loginAs($userId);
        $this->assertFalse(Permission::has('cctv.access'), 'Usuario SENDA no tiene cctv.access');
        $this->assertMiddlewareStatus('cctv.access', 403, 'Acceso a /cctv exige cctv.access y responde 403');

        $this->loginAs($this->adminId);
    }

    private function testOperadorCanAccessModule(): void
    {
        $userId = $this->createTestUser('operador_camaras');
        $this->loginAs($userId);

        foreach ([
            'cctv.access',
            'cctv.dashboard.view',
            'cctv.shifts.view',
            'cctv.shifts.create',
            'cctv.shifts.close',
            'cctv.log.view',
            'cctv.log.create',
            'cctv.log.edit',
            'cctv.cameras.view',
        ] as $permission) {
            $this->assertTrue(Permission::has($permission), 'Operador CCTV tiene ' . $permission);
            $this->assertMiddlewareAllows($permission, 'Operador CCTV puede pasar middleware ' . $permission);
        }

        foreach ([
            'users.view',
            'cctv.shifts.view_all',
            'cctv.shifts.edit_closed',
            'cctv.log.view_all',
            'cctv.log.edit_closed',
            'cctv.log.delete',
            'cctv.cameras.manage',
        ] as $permission) {
            $this->assertFalse(Permission::has($permission), 'Operador CCTV no tiene ' . $permission);
        }

        $this->loginAs($this->adminId);
    }

    private function testOperadorCanOperateShiftAndLog(): void
    {
        $userId = $this->createTestUser('operador_camaras');
        $this->loginAs($userId);
        $this->closeOpenShiftFor($userId);

        $shiftService = new ShiftService();
        $logService = new LogEntryService();
        $equipmentPayload = $this->operationalEquipmentPayload();

        $shiftId = $shiftService->openWithReception([
            'opening_notes' => 'Turno operador permisos',
            'equipment' => $equipmentPayload,
        ], $userId);
        $this->shiftIds[] = $shiftId;

        $this->assertSame(Shift::STATUS_OPEN, $shiftService->find($shiftId)['status'], 'Operador puede iniciar turno');
        $this->assertSame($userId, (int) $shiftService->find($shiftId)['operator_id'], 'Turno iniciado pertenece al operador');

        $this->assertTrue($this->catalog['novedad_type_id'] > 0, 'Existe tipo novedad para prueba de permisos');

        $novedadId = $logService->createForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => '08:10',
            'log_type_id' => $this->catalog['novedad_type_id'],
            'observations' => 'Novedad registrada por operador CCTV.',
            'status' => LogEntry::STATUS_REGISTERED,
        ], $userId);
        $this->entryIds[] = $novedadId;
        $this->assertTrue($novedadId > 0, 'Operador puede registrar novedades');

        $this->assertTrue($this->catalog['incident_type_id'] !== null, 'Existe tipo incidente para prueba de permisos');

        $incidentId = $logService->createIncidentForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => '08:20',
            'incident_type_id' => $this->catalog['incident_type_id'],
            'observations' => 'Incidente registrado por operador CCTV.',
            'coordination_notified' => 0,
            'police_arrived' => LogEntry::POLICE_ARRIVED_NO,
            'status' => LogEntry::STATUS_REGISTERED,
        ], $userId);
        $this->entryIds[] = $incidentId;
        $this->assertTrue($incidentId > 0, 'Operador puede registrar incidentes');

        $shiftService->closeWithDelivery($shiftId, [
            'closing_notes' => 'Cierre operador permisos',
            'equipment' => $equipmentPayload,
        ], $userId);

        $this->assertSame(Shift::STATUS_CLOSED, $shiftService->find($shiftId)['status'], 'Operador puede cerrar su turno');

        $this->loginAs($this->adminId);
    }

    private function testOperadorCannotAdminUsers(): void
    {
        $userId = $this->createTestUser('operador_camaras');
        $this->loginAs($userId);

        $this->assertFalse(Permission::has('users.view'), 'Operador no administra usuarios (permiso)');
        $this->assertMiddlewareStatus('users.view', 403, 'Operador recibe 403 en administración de usuarios');

        $this->loginAs($this->adminId);
    }

    private function testOperadorCannotAlterHistoricalShift(): void
    {
        $shiftService = new ShiftService();
        $this->closeOpenShiftFor($this->adminId);

        $shiftId = $shiftService->open(['shift_date' => date('Y-m-d')], $this->adminId);
        $this->shiftIds[] = $shiftId;
        $shiftService->close($shiftId, 'Turno histórico de otro operador');

        $operadorId = $this->createTestUser('operador_camaras');
        $this->loginAs($operadorId);

        $blocked = false;
        try {
            $shiftService->update($shiftId, [
                'opening_notes' => 'Intento de alterar turno cerrado sin autorización.',
            ]);
        } catch (HttpException $e) {
            $blocked = $e->getStatusCode() === 403;
        }

        $this->assertTrue($blocked, 'Operador no altera turnos históricos cerrados sin permiso especial');

        $this->loginAs($this->adminId);
    }

    private function testOperadorCannotViewForeignShift(): void
    {
        $shiftService = new ShiftService();
        $this->closeOpenShiftFor($this->adminId);

        $shiftId = $shiftService->open(['shift_date' => date('Y-m-d')], $this->adminId);
        $this->shiftIds[] = $shiftId;
        $shiftService->close($shiftId, 'Turno ajeno para prueba de consulta');

        $operadorId = $this->createTestUser('operador_camaras');
        $this->loginAs($operadorId);

        $blocked = false;
        try {
            $shiftService->detailForView($shiftId, $operadorId);
        } catch (HttpException $e) {
            $blocked = $e->getStatusCode() === 403;
        }

        $this->assertTrue($blocked, 'Operador no consulta turnos de otros operadores');

        $history = $shiftService->searchHistory([], 1, 50);
        $ids = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $history['data']);
        $this->assertFalse(in_array($shiftId, $ids, true), 'Historial del operador no expone turnos ajenos');

        $tampered = $shiftService->searchHistory(['operator_id' => (string) $this->adminId], 1, 50);
        $tamperedIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $tampered['data']);
        $this->assertFalse(in_array($shiftId, $tamperedIds, true), 'Operador no puede filtrar historial por otro operador');

        $this->loginAs($this->adminId);
    }

    private function testOperadorCannotManageCameras(): void
    {
        $userId = $this->createTestUser('operador_camaras');
        $this->loginAs($userId);

        $this->assertTrue(Permission::has('cctv.cameras.view'), 'Operador puede ver inventario de cámaras');
        $this->assertFalse(Permission::has('cctv.cameras.manage'), 'Operador no administra cámaras');
        $this->assertMiddlewareStatus('cctv.cameras.manage', 403, 'Operador recibe 403 al administrar cámaras');

        $this->loginAs($this->adminId);
    }

    private function testSupervisorCanConsultAllowedShifts(): void
    {
        $shiftService = new ShiftService();
        $this->closeOpenShiftFor($this->adminId);

        $shiftId = $shiftService->open([
            'shift_date' => date('Y-m-d'),
            'opening_notes' => 'Turno visible para supervisor',
        ], $this->adminId);
        $this->shiftIds[] = $shiftId;
        $shiftService->close($shiftId, 'Cierre consultable por supervisor');

        $supervisorId = $this->createTestUser('consulta');
        $this->loginAs($supervisorId);

        $this->assertTrue(Permission::has('cctv.access'), 'Supervisor tiene acceso CCTV');
        $this->assertTrue(Permission::has('cctv.shifts.view_all'), 'Supervisor puede consultar todos los turnos');
        $this->assertTrue(Permission::has('cctv.log.view_all'), 'Supervisor puede consultar toda la bitácora');
        $this->assertFalse(Permission::has('cctv.shifts.create'), 'Supervisor consulta sin abrir turnos');

        $detail = $shiftService->detailForView($shiftId, $supervisorId);
        $this->assertSame($shiftId, (int) ($detail['shift']['id'] ?? 0), 'Supervisor consulta turno de otro operador');

        $history = $shiftService->searchHistory([
            'operator_id' => (string) $this->adminId,
            'status' => Shift::STATUS_CLOSED,
        ], 1, 20);
        $ids = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $history['data']);
        $this->assertTrue(in_array($shiftId, $ids, true), 'Supervisor filtra historial de turnos permitidos');

        $this->loginAs($this->adminId);
    }

    private function createTestUser(string $roleSlug): int
    {
        $pdo = Database::connection();
        $roleId = (int) $pdo->query(
            'SELECT id FROM roles WHERE slug = ' . $pdo->quote($roleSlug) . ' LIMIT 1'
        )->fetchColumn();

        if ($roleId < 1) {
            throw new RuntimeException('No existe el rol ' . $roleSlug . '.');
        }

        $email = 'cctv.perm.' . $roleSlug . '.' . bin2hex(random_bytes(4)) . '@municipalidad.local';
        $pdo->prepare(
            'INSERT INTO users (name, email, password, is_active, must_change_password)
             VALUES (:name, :email, :password, 1, 0)'
        )->execute([
            'name' => 'Prueba permisos ' . $roleSlug,
            'email' => $email,
            'password' => password_hash('TestPerm123!', PASSWORD_DEFAULT),
        ]);

        $userId = (int) $pdo->lastInsertId();
        $this->tempUserIds[] = $userId;

        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
            ->execute(['user_id' => $userId, 'role_id' => $roleId]);

        return $userId;
    }

    private function loginAs(int $userId): void
    {
        Session::put('auth_user_id', $userId);
        Auth::forgetCache();
        Permission::flush();
    }

    private function closeOpenShiftFor(int $operatorId): void
    {
        $service = new ShiftService();
        $open = $service->findOpenForOperator($operatorId);

        if ($open !== null) {
            $service->close((int) $open['id'], 'Limpieza previa a prueba de permisos');
        }
    }

    /**
     * @return array<int, array{status: string, observations: string}>
     */
    private function operationalEquipmentPayload(): array
    {
        $payload = [];

        foreach ((new EquipmentRepository())->listActive() as $item) {
            $payload[(int) $item['id']] = [
                'status' => ShiftEquipmentCheck::STATUS_OPERATIONAL,
                'observations' => '',
            ];
        }

        return $payload;
    }

    private function assertMiddlewareAllows(string $permission, string $label): void
    {
        $blocked = false;

        try {
            (new PermissionMiddleware())->handle(Request::capture(), $permission);
        } catch (HttpException) {
            $blocked = true;
        }

        $this->assertFalse($blocked, $label);
    }

    private function assertMiddlewareStatus(string $permission, int $expectedStatus, string $label): void
    {
        $status = null;

        try {
            (new PermissionMiddleware())->handle(Request::capture(), $permission);
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
        }

        $this->assertSame($expectedStatus, $status, $label);
    }

    private function cleanup(): void
    {
        $this->loginAs($this->adminId);
        $pdo = Database::connection();

        foreach ($this->entryIds as $entryId) {
            $pdo->prepare('DELETE FROM cctv_log_contacts WHERE cctv_log_entry_id = :id')->execute(['id' => $entryId]);
            $pdo->prepare('DELETE FROM cctv_log_entries WHERE id = :id')->execute(['id' => $entryId]);
        }

        foreach ($this->shiftIds as $shiftId) {
            $pdo->prepare('DELETE FROM cctv_log_entries WHERE cctv_shift_id = :id')->execute(['id' => $shiftId]);
            $pdo->prepare('DELETE FROM cctv_shift_equipment_checks WHERE cctv_shift_id = :id')->execute(['id' => $shiftId]);
            $pdo->prepare('DELETE FROM cctv_shifts WHERE id = :id')->execute(['id' => $shiftId]);
        }

        foreach ($this->tempUserIds as $userId) {
            $this->closeOpenShiftFor($userId);
            $pdo->prepare(
                'DELETE FROM cctv_log_contacts WHERE cctv_log_entry_id IN (
                    SELECT id FROM cctv_log_entries WHERE created_by = :user_id
                 )'
            )->execute(['user_id' => $userId]);
            $pdo->prepare('DELETE FROM cctv_log_entries WHERE created_by = :user_id')->execute(['user_id' => $userId]);
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

exit((new CctvPermissionsFunctionalTests())->run());
