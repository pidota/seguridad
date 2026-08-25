<?php

declare(strict_types=1);

/**
 * Pruebas funcionales del módulo Guardias.
 * Ejecutar: php tests/guards_shifts_functional.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse por consola.\n");
    exit(1);
}

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/guards';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/seguridad/public/index.php';

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Exceptions\Guards\OpenShiftAlreadyExistsException;
use App\Models\Guards\LogType;
use App\Models\Guards\Shift;
use App\Repositories\Guards\CctvLinkRepository;
use App\Services\Guards\CctvLinkService;
use App\Services\Guards\LogEntryService;
use App\Services\Guards\ShiftService;
use Core\Auth;
use Core\Database;
use Core\Permission;
use Core\Request;
use Core\Session;

final class GuardsShiftsFunctionalTests
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var list<string> */
    private array $failures = [];

    /** @var list<int> */
    private array $shiftIds = [];

    /** @var list<int> */
    private array $logEntryIds = [];

    public function run(): int
    {
        $this->boot();

        try {
            $this->testModelStatuses();
            $this->testOpenCloseShift();
            $this->testSingleOpenShiftPerGuard();
            $this->testCreateLogEntry();
            $this->testSearchHistory();
            $this->testIncomingCoordinationsQuery();
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
        $open = $service->findOpenForGuard($adminId);
        if ($open !== null) {
            $service->close((int) $open['id'], 'Limpieza previa a pruebas');
        }
    }

    private function testModelStatuses(): void
    {
        $this->assertTrue(Shift::isValidStatus(Shift::STATUS_OPEN), 'Estado open es válido');
        $this->assertTrue(Shift::isValidStatus(Shift::STATUS_CLOSED), 'Estado closed es válido');
        $this->assertTrue(Shift::isOpen(Shift::STATUS_OPEN), 'Helper isOpen reconoce open');
        $this->assertTrue(Shift::isClosed(Shift::STATUS_CLOSED), 'Helper isClosed reconoce closed');
    }

    private function testOpenCloseShift(): void
    {
        $service = new ShiftService();
        $guardId = (int) Auth::id();

        $id = $service->open([
            'shift_date' => date('Y-m-d'),
            'opening_notes' => 'Turno de prueba guardias',
        ], $guardId);
        $this->shiftIds[] = $id;
        $this->assertTrue($id > 0, 'Se crea turno de guardia');

        $open = $service->findOpenForGuard($guardId);
        $this->assertTrue($open !== null, 'Turno abierto queda disponible');
        $this->assertSame(Shift::STATUS_OPEN, $open['status'] ?? null, 'Estado del turno es open');

        $service->close($id, 'Cierre de prueba');
        $closed = $service->find($id);
        $this->assertTrue(Shift::isClosed((string) ($closed['status'] ?? '')), 'Turno queda cerrado');
    }

    private function testSingleOpenShiftPerGuard(): void
    {
        $service = new ShiftService();
        $guardId = (int) Auth::id();

        $firstId = $service->open(['shift_date' => date('Y-m-d')], $guardId);
        $this->shiftIds[] = $firstId;

        try {
            $service->open(['shift_date' => date('Y-m-d')], $guardId);
            $this->fail('turno duplicado', 'Debió rechazarse un segundo turno abierto');
        } catch (OpenShiftAlreadyExistsException) {
            $this->pass('Rechaza segundo turno abierto');
        }

        $service->close($firstId);
    }

    private function testCreateLogEntry(): void
    {
        $shiftService = new ShiftService();
        $logService = new LogEntryService();
        $guardId = (int) Auth::id();

        $shiftId = $shiftService->open(['shift_date' => date('Y-m-d')], $guardId);
        $this->shiftIds[] = $shiftId;

        $types = $logService->logTypeOptions();
        $this->assertTrue($types !== [], 'Existen tipos de novedad de guardias');

        $typeId = 0;
        foreach ($types as $type) {
            if (($type['slug'] ?? '') === LogType::SLUG_NOVEDAD) {
                $typeId = (int) ($type['id'] ?? 0);
                break;
            }
        }

        $this->assertTrue($typeId > 0, 'Existe tipo novedad en catálogo');

        $result = $logService->createForOpenShift([
            'log_type_id' => $typeId,
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'observations' => 'Prueba funcional de novedad en terreno',
            'status' => 'registrado',
        ], $guardId);

        $this->assertTrue(is_array($result), 'createForOpenShift retorna resultado');

        $detail = $shiftService->detailForView($shiftId, $guardId);
        $this->assertSame(1, (int) ($detail['stats']['total_entries'] ?? 0), 'El turno registra una novedad');

        if (($detail['log_entries'][0]['id'] ?? null) !== null) {
            $this->logEntryIds[] = (int) $detail['log_entries'][0]['id'];
        }

        $shiftService->close($shiftId);
    }

    private function testSearchHistory(): void
    {
        $service = new ShiftService();
        $result = $service->searchHistory(['status' => Shift::STATUS_CLOSED], 1, 5);
        $this->assertTrue(isset($result['data'], $result['total'], $result['page'], $result['pages']), 'searchHistory retorna paginación');
    }

    private function testIncomingCoordinationsQuery(): void
    {
        $repo = new CctvLinkRepository();
        $service = new CctvLinkService();
        $rows = $repo->listIncomingCoordinations(3);
        $this->assertTrue(is_array($rows), 'Consulta de coordinaciones CCTV entrantes');
        $this->assertTrue($service->incomingCount() >= 0, 'Contador de coordinaciones CCTV');
    }

    private function cleanup(): void
    {
        $pdo = Database::connection();

        foreach ($this->logEntryIds as $id) {
            $pdo->prepare('UPDATE guards_log_entries SET deleted_at = NOW() WHERE id = :id')->execute(['id' => $id]);
        }

        foreach ($this->shiftIds as $id) {
            $pdo->prepare('UPDATE guards_log_entries SET deleted_at = NOW() WHERE guards_shift_id = :id')->execute(['id' => $id]);
            $pdo->prepare('UPDATE guards_shifts SET deleted_at = NOW() WHERE id = :id')->execute(['id' => $id]);
        }
    }

    private function assertTrue(mixed $condition, string $label): void
    {
        if ($condition) {
            $this->pass($label);
        } else {
            $this->fail($label, 'Condición falsa');
        }
    }

    private function assertSame(mixed $expected, mixed $actual, string $label): void
    {
        if ($expected === $actual) {
            $this->pass($label);
        } else {
            $this->fail($label, 'Esperado ' . var_export($expected, true) . ', obtenido ' . var_export($actual, true));
        }
    }

    private function pass(string $label): void
    {
        $this->passed++;
        fwrite(STDOUT, "[OK] {$label}\n");
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

        if ($this->failures !== []) {
            fwrite(STDOUT, "Fallos:" . PHP_EOL);
            foreach ($this->failures as $failure) {
                fwrite(STDOUT, " - {$failure}" . PHP_EOL);
            }
        }
    }
}

exit((new GuardsShiftsFunctionalTests())->run());
