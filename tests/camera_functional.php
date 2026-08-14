<?php

declare(strict_types=1);

/**
 * Pruebas funcionales del módulo Central de Cámaras (Etapa 1 — Bitácora).
 * Ejecutar: php tests/camera_functional.php
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
use App\Services\AuditService;
use App\Services\Camera\EventCatalog;
use App\Services\Cctv\CatalogService;
use App\Services\Camera\EventService;
use App\Validators\Camera\EventStoreValidator;
use App\Validators\Camera\EventUpdateValidator;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Permission;
use Core\Request;
use Core\Session;
use Core\View;

final class CameraFunctionalTests
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var list<string> */
    private array $failures = [];

    /** @var list<int> */
    private array $eventIds = [];

    public function run(): int
    {
        $this->boot();

        try {
            $this->testStoreValidatorRequiresFields();
            $this->testStoreValidatorRequiresOtherClassification();
            $this->testCatalogOptions();
            $this->testCreateAndFind();
            $this->testUpdateEvent();
            $this->testSearchFilters();
            $this->testAuditTrail();
            $this->testFormViewMarkup();
            $this->testAccessWithoutPermission();
            $this->testOperadorCamarasPermissions();
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
    }

    private function testStoreValidatorRequiresFields(): void
    {
        $errors = (new EventStoreValidator())->validate([]);
        $this->assertTrue($errors !== [], 'El validador rechaza payload vacío');
        $this->assertTrue(isset($errors['event_date']), 'El validador exige fecha');
        $this->assertTrue(isset($errors['shift']), 'El validador exige turno');
        $this->assertTrue(isset($errors['classification']), 'El validador exige clasificación');
        $this->assertTrue(isset($errors['location']), 'El validador exige ubicación');
        $this->assertTrue(isset($errors['description']), 'El validador exige descripción');
    }

    private function testStoreValidatorRequiresOtherClassification(): void
    {
        $errors = (new EventStoreValidator())->validate([
            'event_date' => date('Y-m-d'),
            'shift' => 'manana',
            'classification' => 'otro',
            'location' => 'Plaza central',
            'description' => 'Evento de prueba funcional CCTV',
        ]);

        $this->assertTrue(isset($errors['classification_other']), 'Clasificación "otro" exige detalle');

        $ok = (new EventStoreValidator())->validate([
            'event_date' => date('Y-m-d'),
            'shift' => 'manana',
            'classification' => 'otro',
            'classification_other' => 'Prueba funcional',
            'location' => 'Plaza central',
            'description' => 'Evento de prueba funcional CCTV',
        ]);

        $this->assertSame([], $ok, 'Payload válido con clasificación "otro"');
        $this->assertSame([], (new EventUpdateValidator())->validate([
            'event_date' => date('Y-m-d'),
            'shift' => 'tarde',
            'classification' => 'accidente',
            'location' => 'Plaza central',
            'description' => 'Evento actualizado en prueba',
        ]), 'EventUpdateValidator acepta payload válido');
    }

    private function testCatalogOptions(): void
    {
        $catalog = new CatalogService();
        $this->assertTrue(EventCatalog::isValidShift('manana'), 'Turno mañana es válido');
        $this->assertTrue($catalog->isValidIncidentTypeSlug('accidente'), 'Clasificación accidente es válida');
        $this->assertSame('Accidente', $catalog->incidentTypeLabel('accidente'), 'Etiqueta de clasificación desde BD');
        $this->assertSame('traffic', $catalog->incidentTypeTone('accidente'), 'Tono de clasificación desde BD');
    }

    private function testCreateAndFind(): void
    {
        $service = new EventService();
        $id = $service->create([
            'event_date' => date('Y-m-d'),
            'event_time' => '10:30',
            'shift' => 'manana',
            'classification' => 'accidente',
            'location' => 'Av. Principal / Cámara 12',
            'description' => 'Registro de prueba funcional del módulo CCTV.',
            'actions_taken' => 'Se informó a guardias municipales.',
        ]);
        $this->eventIds[] = $id;
        $this->assertTrue($id > 0, 'Create devuelve un ID válido');

        $record = $service->find($id);
        $this->assertSame('accidente', $record['classification'], 'Create persiste clasificación');
        $this->assertSame('Accidente', $record['classification_label'], 'Present incluye etiqueta');
        $this->assertSame('10:30', $record['event_time'], 'Present normaliza hora');
        $this->assertSame('traffic', $record['classification_tone'], 'Present incluye tono visual');

        $missing = false;
        try {
            $service->find(999999999);
        } catch (HttpException $e) {
            $missing = $e->getStatusCode() === 404;
        }
        $this->assertTrue($missing, 'Find inexistente responde 404');
    }

    private function testUpdateEvent(): void
    {
        $service = new EventService();
        $id = $service->create([
            'event_date' => date('Y-m-d'),
            'event_time' => '08:00',
            'shift' => 'manana',
            'classification' => 'accidente',
            'location' => 'Sector norte',
            'description' => 'Monitoreo inicial de prueba.',
        ]);
        $this->eventIds[] = $id;

        $service->update($id, [
            'event_date' => date('Y-m-d'),
            'event_time' => '09:15',
            'shift' => 'tarde',
            'classification' => 'otro',
            'classification_other' => 'Evento especial de prueba',
            'location' => 'Sector sur',
            'description' => 'Descripción actualizada en prueba funcional.',
            'actions_taken' => 'Se registró seguimiento.',
        ]);

        $record = $service->find($id);
        $this->assertSame('tarde', $record['shift'], 'Update cambia turno');
        $this->assertSame('Sector sur', $record['location'], 'Update cambia ubicación');
        $this->assertTrue(str_contains((string) $record['classification_label'], 'Evento especial de prueba'), 'Update conserva detalle de "otro"');
    }

    private function testSearchFilters(): void
    {
        $service = new EventService();
        $unique = 'CAMTEST-' . bin2hex(random_bytes(3));
        $id = $service->create([
            'event_date' => date('Y-m-d'),
            'shift' => 'noche',
            'classification' => 'situacion_sospechosa',
            'location' => $unique,
            'description' => 'Búsqueda funcional del módulo CCTV.',
        ]);
        $this->eventIds[] = $id;

        $result = $service->search(['q' => $unique], 1, 15);
        $this->assertTrue($result['total'] >= 1, 'Búsqueda por texto encuentra el evento');
        $this->assertSame($unique, $result['data'][0]['location'] ?? '', 'Búsqueda devuelve el registro correcto');

        $filtered = $service->search([
            'shift' => 'noche',
            'classification' => 'situacion_sospechosa',
            'date_from' => date('Y-m-d'),
            'date_to' => date('Y-m-d'),
        ], 1, 15);
        $this->assertTrue($filtered['total'] >= 1, 'Filtros combinados devuelven resultados');

        $defaults = $service->defaults();
        $this->assertTrue(EventCatalog::isValidShift((string) ($defaults['shift'] ?? '')), 'Defaults incluye turno válido');
        $this->assertSame(date('Y-m-d'), $defaults['event_date'], 'Defaults incluye fecha actual');
    }

    private function testAuditTrail(): void
    {
        $service = new EventService();
        $id = $service->create([
            'event_date' => date('Y-m-d'),
            'shift' => 'manana',
            'classification' => 'emergencia',
            'location' => 'Auditoría CCTV',
            'description' => 'Evento para verificar auditoría.',
        ]);
        $this->eventIds[] = $id;

        $pdo = Database::connection();
        $created = $pdo->prepare(
            'SELECT action, module, resource FROM audit_logs
             WHERE resource = :resource AND resource_id = :id
             ORDER BY id DESC LIMIT 1'
        );
        $created->execute([
            'resource' => AuditService::RESOURCE_CAMERA_EVENT,
            'id' => (string) $id,
        ]);
        $row = $created->fetch();

        $this->assertSame('created', $row['action'] ?? null, 'Create registra auditoría');
        $this->assertSame(AuditService::MODULE_CCTV, $row['module'] ?? null, 'Auditoría usa módulo cctv');

        $service->update($id, [
            'event_date' => date('Y-m-d'),
            'shift' => 'manana',
            'classification' => 'emergencia',
            'location' => 'Auditoría CCTV actualizada',
            'description' => 'Evento para verificar auditoría actualizada.',
        ]);

        $updated = $pdo->prepare(
            'SELECT action FROM audit_logs
             WHERE resource = :resource AND resource_id = :id AND action = :action
             ORDER BY id DESC LIMIT 1'
        );
        $updated->execute([
            'resource' => AuditService::RESOURCE_CAMERA_EVENT,
            'id' => (string) $id,
            'action' => 'updated',
        ]);

        $this->assertSame('updated', $updated->fetchColumn(), 'Update registra auditoría');
    }

    private function testFormViewMarkup(): void
    {
        $html = View::make('camera/events/form', [
            'record' => (new EventService())->defaults(),
            'shifts' => EventCatalog::shifts(),
            'classifications' => (new CatalogService())->incidentTypeOptions(),
            'camerasNav' => [],
        ], null);

        $this->assertTrue(str_contains($html, 'data-camera-event-form'), 'Formulario incluye hook JS');
        $this->assertTrue(str_contains($html, 'data-camera-other-toggle'), 'Formulario incluye toggle de "otro"');
        $this->assertTrue(str_contains($html, 'name="_token"'), 'Formulario incluye CSRF');

        $js = file_get_contents(BASE_PATH . '/resources/js/modules/cctv/log.js') ?: '';
        $this->assertTrue(str_contains($js, 'data-camera-other-panel'), 'JS maneja panel de clasificación "otro"');
    }

    private function testAccessWithoutPermission(): void
    {
        $pdo = Database::connection();
        $roleId = (int) $pdo->query(
            "SELECT id FROM roles WHERE slug = 'senda' LIMIT 1"
        )->fetchColumn();

        if ($roleId < 1) {
            $this->fail('Acceso 403', 'No existe el rol senda.');
            return;
        }

        $email = 'camera.func.test.' . bin2hex(random_bytes(4)) . '@municipalidad.local';
        $insert = $pdo->prepare(
            'INSERT INTO users (name, email, password, is_active, must_change_password)
             VALUES (:name, :email, :password, 1, 0)'
        );
        $insert->execute([
            'name' => 'Prueba funcional CCTV',
            'email' => $email,
            'password' => password_hash('TestCamera123!', PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
            ->execute(['user_id' => $userId, 'role_id' => $roleId]);

        Session::put('auth_user_id', $userId);
        Auth::forgetCache();
        Permission::flush();

        $this->assertFalse(Permission::has('cctv.access'), 'Usuario SENDA no tiene cctv.access');

        $status = null;
        try {
            (new PermissionMiddleware())->handle(Request::capture(), 'cctv.access');
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
        }

        $this->assertSame(403, $status, 'Usuario sin cctv.access recibe 403');

        $pdo->prepare('DELETE FROM user_roles WHERE user_id = :id')->execute(['id' => $userId]);
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
    }

    private function testOperadorCamarasPermissions(): void
    {
        $pdo = Database::connection();
        $roleId = (int) $pdo->query(
            "SELECT id FROM roles WHERE slug = 'operador_camaras' LIMIT 1"
        )->fetchColumn();

        if ($roleId < 1) {
            $this->fail('Permisos operador', 'No existe el rol operador_camaras.');
            return;
        }

        $email = 'camera.operador.test.' . bin2hex(random_bytes(4)) . '@municipalidad.local';
        $insert = $pdo->prepare(
            'INSERT INTO users (name, email, password, is_active, must_change_password)
             VALUES (:name, :email, :password, 1, 0)'
        );
        $insert->execute([
            'name' => 'Operador CCTV prueba',
            'email' => $email,
            'password' => password_hash('TestCamera123!', PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
            ->execute(['user_id' => $userId, 'role_id' => $roleId]);

        Session::put('auth_user_id', $userId);
        Auth::forgetCache();
        Permission::flush();

        $expected = [
            'cctv.access',
            'cctv.dashboard.view',
            'cctv.shifts.view',
            'cctv.shifts.create',
            'cctv.shifts.close',
            'cctv.log.view',
            'cctv.log.create',
            'cctv.log.edit',
            'cctv.cameras.view',
        ];

        foreach ($expected as $permission) {
            $this->assertTrue(Permission::has($permission), 'Operador tiene ' . $permission);
        }

        foreach ([
            'cctv.log.delete',
            'cctv.log.view_all',
            'cctv.log.edit_closed',
            'cctv.shifts.view_all',
            'cctv.shifts.edit_closed',
            'cctv.cameras.manage',
            'cctv.reports.export',
        ] as $permission) {
            $this->assertFalse(Permission::has($permission), 'Operador no tiene ' . $permission);
        }

        $pdo->prepare('DELETE FROM user_roles WHERE user_id = :id')->execute(['id' => $userId]);
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);

        $adminId = (int) $pdo->query(
            "SELECT u.id FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.slug = 'superadministrador' AND u.is_active = 1
             LIMIT 1"
        )->fetchColumn();
        Session::put('auth_user_id', $adminId);
        Auth::forgetCache();
        Permission::flush();
    }

    private function cleanup(): void
    {
        $pdo = Database::connection();

        foreach (array_reverse($this->eventIds) as $id) {
            $pdo->prepare('DELETE FROM camera_events WHERE id = :id')->execute(['id' => $id]);
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

exit((new CameraFunctionalTests())->run());
