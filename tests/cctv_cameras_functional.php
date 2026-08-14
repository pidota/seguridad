<?php

declare(strict_types=1);

/**
 * Pruebas funcionales del inventario CCTV.
 * Ejecutar: php tests/cctv_cameras_functional.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse por consola.\n");
    exit(1);
}

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/cctv/cameras';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/seguridad/public/index.php';

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Middleware\PermissionMiddleware;
use App\Services\AuditService;
use App\Services\Cctv\CameraCatalog;
use App\Services\Cctv\CameraService;
use App\Validators\Cctv\CameraStoreValidator;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Permission;
use Core\Request;
use Core\Session;

final class CctvCamerasFunctionalTests
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var list<string> */
    private array $failures = [];

    /** @var list<int> */
    private array $cameraIds = [];

    /** @var list<int> */
    private array $sectorIds = [];

    public function run(): int
    {
        $this->boot();

        try {
            $this->testCatalogOptions();
            $this->testCrudAndAudit();
            $this->testOperatorSeesOnlyActive();
            $this->testManagePermissionRequired();
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

        Session::put('auth_user_id', $adminId);
        Auth::forgetCache();
        Permission::flush();
    }

    private function testCatalogOptions(): void
    {
        $this->assertTrue(CameraCatalog::isValidType(CameraCatalog::TYPE_PTZ), 'Tipo PTZ válido');
        $this->assertTrue(CameraCatalog::isValidStatus(CameraCatalog::STATUS_OPERATIONAL), 'Estado operativa válido');
        $this->assertSame('Fija', CameraCatalog::label(CameraCatalog::types(), CameraCatalog::TYPE_FIXED), 'Etiqueta tipo fija');

        $errors = (new CameraStoreValidator())->validate([]);
        $this->assertTrue(isset($errors['code'], $errors['name']), 'Validador exige código y nombre');
    }

    private function testCrudAndAudit(): void
    {
        $pdo = Database::connection();
        $slug = 'sector_cam_test_' . bin2hex(random_bytes(2));
        $pdo->prepare(
            'INSERT INTO sectors (slug, name, is_active) VALUES (:slug, :name, 1)'
        )->execute(['slug' => $slug, 'name' => 'Sector prueba cámaras']);
        $sectorId = (int) $pdo->lastInsertId();
        $this->sectorIds[] = $sectorId;

        $service = new CameraService();
        $code = 'CAM-T-' . strtoupper(bin2hex(random_bytes(2)));

        $id = $service->create([
            'code' => $code,
            'name' => 'Cámara de prueba',
            'sector_id' => (string) $sectorId,
            'location' => 'Plaza central',
            'camera_type' => CameraCatalog::TYPE_PTZ,
            'status' => CameraCatalog::STATUS_OPERATIONAL,
            'active' => '1',
        ]);
        $this->cameraIds[] = $id;

        $record = $service->find($id);
        $this->assertSame($code, $record['code'], 'Create normaliza y persiste código');
        $this->assertSame('PTZ', $record['camera_type_label'], 'Present incluye tipo');
        $this->assertSame('Operativa', $record['status_label'], 'Present incluye estado');

        $service->update($id, [
            'code' => $code,
            'name' => 'Cámara de prueba actualizada',
            'sector_id' => (string) $sectorId,
            'location' => 'Mercado municipal',
            'camera_type' => CameraCatalog::TYPE_FIXED,
            'status' => CameraCatalog::STATUS_ISSUES,
            'active' => '1',
        ]);

        $updated = $service->find($id);
        $this->assertSame('Con problemas', $updated['status_label'], 'Update cambia estado');

        $audit = $pdo->prepare(
            'SELECT action FROM audit_logs WHERE resource = :resource AND resource_id = :id ORDER BY id DESC LIMIT 1'
        );
        $audit->execute([
            'resource' => AuditService::RESOURCE_CCTV_CAMERA,
            'id' => (string) $id,
        ]);
        $this->assertSame('updated', $audit->fetchColumn(), 'Update registra auditoría');
    }

    private function testOperatorSeesOnlyActive(): void
    {
        $service = new CameraService();
        $activeCode = 'CAM-ACT-' . strtoupper(bin2hex(random_bytes(2)));
        $inactiveCode = 'CAM-INA-' . strtoupper(bin2hex(random_bytes(2)));

        $activeId = $service->create([
            'code' => $activeCode,
            'name' => 'Cámara activa',
            'camera_type' => CameraCatalog::TYPE_FIXED,
            'status' => CameraCatalog::STATUS_OPERATIONAL,
            'active' => '1',
        ]);
        $this->cameraIds[] = $activeId;

        $inactiveId = $service->create([
            'code' => $inactiveCode,
            'name' => 'Cámara inactiva',
            'camera_type' => CameraCatalog::TYPE_FIXED,
            'status' => CameraCatalog::STATUS_OPERATIONAL,
            'active' => '0',
        ]);
        $this->cameraIds[] = $inactiveId;

        $operatorView = $service->search([], 1, 50, true);
        $codes = array_column($operatorView['data'], 'code');

        $this->assertTrue(in_array($activeCode, $codes, true), 'Vista operador incluye cámara activa');
        $this->assertFalse(in_array($inactiveCode, $codes, true), 'Vista operador oculta cámara inactiva');

        $manageView = $service->search([], 1, 50, false);
        $manageCodes = array_column($manageView['data'], 'code');
        $this->assertTrue(in_array($inactiveCode, $manageCodes, true), 'Vista administración incluye inactivas');
    }

    private function testManagePermissionRequired(): void
    {
        $pdo = Database::connection();
        $roleId = (int) $pdo->query(
            "SELECT id FROM roles WHERE slug = 'operador_camaras' LIMIT 1"
        )->fetchColumn();

        $email = 'camera.view.test.' . bin2hex(random_bytes(4)) . '@municipalidad.local';
        $pdo->prepare(
            'INSERT INTO users (name, email, password, is_active, must_change_password)
             VALUES (:name, :email, :password, 1, 0)'
        )->execute([
            'name' => 'Operador cámaras prueba',
            'email' => $email,
            'password' => password_hash('TestCamera123!', PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
            ->execute(['user_id' => $userId, 'role_id' => $roleId]);

        Session::put('auth_user_id', $userId);
        Auth::forgetCache();
        Permission::flush();

        $this->assertTrue(Permission::has('cctv.cameras.view'), 'Operador puede ver cámaras');
        $this->assertFalse(Permission::has('cctv.cameras.manage'), 'Operador no administra cámaras');

        $status = null;
        try {
            (new PermissionMiddleware())->handle(Request::capture(), 'cctv.cameras.manage');
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
        }
        $this->assertSame(403, $status, 'Ruta manage exige permiso administrativo');

        $pdo->prepare('DELETE FROM user_roles WHERE user_id = :id')->execute(['id' => $userId]);
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
    }

    private function cleanup(): void
    {
        $pdo = Database::connection();

        foreach ($this->cameraIds as $id) {
            $pdo->prepare('DELETE FROM cctv_cameras WHERE id = :id')->execute(['id' => $id]);
        }

        foreach ($this->sectorIds as $id) {
            $pdo->prepare('DELETE FROM sectors WHERE id = :id')->execute(['id' => $id]);
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

exit((new CctvCamerasFunctionalTests())->run());
