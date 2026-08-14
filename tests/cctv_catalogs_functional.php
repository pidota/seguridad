<?php

declare(strict_types=1);

/**
 * Pruebas funcionales de catálogos CCTV.
 * Ejecutar: php tests/cctv_catalogs_functional.php
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

use App\Models\Cctv\IncidentType;
use App\Models\Cctv\LogType;
use App\Services\Cctv\CatalogService;
use Core\Auth;
use Core\Database;
use Core\Permission;
use Core\Request;
use Core\Session;

final class CctvCatalogsFunctionalTests
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var list<string> */
    private array $failures = [];

    /** @var list<int> */
    private array $logTypeIds = [];

    /** @var list<int> */
    private array $incidentTypeIds = [];

    public function run(): int
    {
        $this->boot();

        try {
            $this->testInitialLogTypes();
            $this->testInitialIncidentTypes();
            $this->testCatalogOptionsShape();
            $this->testSaveAndDeactivateCatalogItem();
            $this->testLegacyValidatorUsesDatabaseCatalog();
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

    private function testInitialLogTypes(): void
    {
        $service = new CatalogService();
        $names = array_column($service->activeLogTypes(), 'name');

        foreach ([
            'Novedad',
            'Incidente',
            'Novedad Técnica',
            'Comunicación / Coordinación',
            'Recepción / Entrega',
            'Otro',
        ] as $name) {
            $this->assertTrue(in_array($name, $names, true), 'Catálogo incluye tipo de registro: ' . $name);
        }

        $incidente = $service->findLogTypeBySlug('incidente');
        $this->assertTrue(!empty($incidente['requires_incident']), 'Incidente requiere clasificación de incidente');
    }

    private function testInitialIncidentTypes(): void
    {
        $service = new CatalogService();
        $names = array_column($service->activeIncidentTypes(), 'name');

        foreach ([
            'Consumo de alcohol en vía pública',
            'Riña en vía pública',
            'Violencia',
            'Vehículo mal estacionado',
            'Situación sospechosa',
            'Daños',
            'Accidente',
            'Emergencia',
            'Otro',
        ] as $name) {
            $this->assertTrue(in_array($name, $names, true), 'Catálogo incluye tipo de incidente: ' . $name);
        }

        $this->assertTrue($service->incidentAllowsOther(IncidentType::SLUG_OTHER), 'Otro permite detalle adicional');
    }

    private function testCatalogOptionsShape(): void
    {
        $service = new CatalogService();
        $log = $service->logTypeOptions()[0] ?? [];
        $incident = $service->incidentTypeOptions()[0] ?? [];

        $this->assertTrue(isset($log['id'], $log['value'], $log['label']), 'Opciones de registro traen id/value/label');
        $this->assertTrue(isset($incident['id'], $incident['value'], $incident['label'], $incident['tone']), 'Opciones de incidente traen metadatos');
        $this->assertTrue($service->isValidIncidentTypeSlug((string) $incident['value']), 'Slug de incidente activo es válido');
    }

    private function testSaveAndDeactivateCatalogItem(): void
    {
        $service = new CatalogService();
        $slug = 'prueba_catalogo_' . bin2hex(random_bytes(3));

        $id = $service->saveLogType([
            'slug' => $slug,
            'name' => 'Tipo de prueba temporal',
            'tone' => 'other',
            'sort_order' => 999,
            'is_active' => 1,
        ]);
        $this->logTypeIds[] = $id;

        $this->assertTrue($service->isValidLogTypeSlug($slug), 'saveLogType deja el ítem disponible');
        $service->deactivateLogType($id);
        $this->assertFalse($service->isValidLogTypeSlug($slug), 'deactivateLogType retira el ítem del catálogo activo');
    }

    private function testLegacyValidatorUsesDatabaseCatalog(): void
    {
        $service = new CatalogService();
        $html = \Core\View::make('camera/events/form', [
            'record' => ['classification' => 'accidente'],
            'shifts' => [],
            'classifications' => $service->incidentTypeOptions(),
            'camerasNav' => [],
        ], null);

        $this->assertTrue(str_contains($html, 'Accidente'), 'La vista renderiza etiquetas desde catálogo');
        $this->assertFalse(str_contains($html, 'Incidente vial'), 'La vista no incluye valores hardcodeados obsoletos');
    }

    private function cleanup(): void
    {
        $pdo = Database::connection();

        foreach ($this->logTypeIds as $id) {
            $pdo->prepare('DELETE FROM cctv_log_types WHERE id = :id')->execute(['id' => $id]);
        }

        foreach ($this->incidentTypeIds as $id) {
            $pdo->prepare('DELETE FROM cctv_incident_types WHERE id = :id')->execute(['id' => $id]);
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

exit((new CctvCatalogsFunctionalTests())->run());
