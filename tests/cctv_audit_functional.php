<?php

declare(strict_types=1);

/**
 * Pruebas de auditoría del módulo CCTV.
 * Ejecutar: php tests/cctv_audit_functional.php
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

use App\Services\AuditService;
use App\Services\Cctv\CameraCatalog;
use App\Services\Cctv\CameraService;
use App\Services\Cctv\CctvAuditService;
use App\Services\Cctv\LogEntryService;
use App\Services\Cctv\ShiftService;
use Core\Auth;
use Core\Database;
use Core\Permission;
use Core\Request;
use Core\Session;

final class CctvAuditFunctionalTests
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var list<string> */
    private array $failures = [];

    /** @var list<int> */
    private array $shiftIds = [];

    /** @var list<int> */
    private array $entryIds = [];

    private int $adminId = 0;

    /** @var array<string, int|null> */
    private array $catalog = [];

    public function run(): int
    {
        $this->boot();

        try {
            $this->testShiftOpenAudit();
            $this->testShiftCloseAudit();
            $this->testLogEntryCreateSanitizesObservations();
            $this->testIncidentCreateAudit();
            $this->testLogEntryUpdateAudit();
            $this->testLogEntryCancelAudit();
            $this->testCoordinationRegisteredAudit();
            $this->testCameraStatusChangeAudit();
            $this->testClosedShiftUpdateAudit();
        } catch (\Throwable $e) {
            $this->fail('ejecución', $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        }

        try {
            $this->cleanup();
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Aviso: no se pudo limpiar todo el residuo de prueba: ' . $e->getMessage() . PHP_EOL);
        }

        $this->summary();

        return $this->failed > 0 ? 1 : 0;
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

        $pdo = Database::connection();
        $this->catalog['log_type_id'] = (int) ($pdo->query(
            "SELECT id FROM cctv_log_types WHERE slug = 'otro' AND deleted_at IS NULL LIMIT 1"
        )->fetchColumn() ?: 0);
        $this->catalog['incident_log_type_id'] = (int) ($pdo->query(
            "SELECT id FROM cctv_log_types WHERE slug = 'incidente' AND deleted_at IS NULL LIMIT 1"
        )->fetchColumn() ?: 0);
        $this->catalog['incident_type_id'] = (int) ($pdo->query(
            "SELECT id FROM cctv_incident_types WHERE slug = 'rina_via_publica' AND deleted_at IS NULL LIMIT 1"
        )->fetchColumn() ?: 0);
        $this->catalog['camera_id'] = (int) ($pdo->query(
            'SELECT id FROM cctv_cameras WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1'
        )->fetchColumn() ?: 0);
        $this->catalog['sector_id'] = (int) ($pdo->query(
            'SELECT id FROM sectors WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1'
        )->fetchColumn() ?: 0);
        $this->catalog['technical_issue_type_id'] = (int) ($pdo->query(
            "SELECT id FROM cctv_technical_issue_types WHERE slug = 'sin_senal' AND deleted_at IS NULL LIMIT 1"
        )->fetchColumn() ?: 0);
        $this->catalog['equipment_id'] = (int) ($pdo->query(
            "SELECT id FROM cctv_equipment WHERE slug = 'monitores' AND deleted_at IS NULL LIMIT 1"
        )->fetchColumn() ?: 0);
    }

    private function testShiftOpenAudit(): void
    {
        $service = new ShiftService();
        $longNotes = str_repeat('Nota sensible de apertura. ', 20);

        $id = $service->open([
            'shift_date' => date('Y-m-d'),
            'opening_notes' => $longNotes,
        ], $this->adminId);
        $this->shiftIds[] = $id;

        $payload = $this->latestAuditPayload(
            AuditService::RESOURCE_CCTV_SHIFT,
            (string) $id,
            AuditService::ACTION_CREATED
        );

        $this->assertSame(CctvAuditService::EVENT_SHIFT_OPENED, $payload['cctv_event'] ?? null, 'Apertura registra evento shift_opened');
        $this->assertTrue(isset($payload['opening_notes_excerpt']), 'Apertura guarda extracto de notas');
        $this->assertFalse(isset($payload['opening_notes']), 'Apertura no guarda notas completas');
        $this->assertTrue(mb_strlen((string) ($payload['opening_notes_excerpt'] ?? '')) <= 120, 'Extracto respeta límite');

        $service->close($id);
    }

    private function testShiftCloseAudit(): void
    {
        $service = new ShiftService();
        $id = $service->open(['shift_date' => date('Y-m-d')], $this->adminId);
        $this->shiftIds[] = $id;

        $service->close($id, str_repeat('Cierre confidencial. ', 15));

        $rows = $this->latestAuditRows(
            AuditService::RESOURCE_CCTV_SHIFT,
            (string) $id,
            AuditService::ACTION_UPDATED,
            1
        );
        $newValues = json_decode((string) ($rows[0]['new_values'] ?? ''), true);

        $this->assertSame(CctvAuditService::EVENT_SHIFT_CLOSED, $newValues['cctv_event'] ?? null, 'Cierre registra evento shift_closed');
        $this->assertTrue(isset($newValues['closing_notes_excerpt']), 'Cierre guarda extracto de notas');
        $this->assertFalse(isset($newValues['closing_notes']), 'Cierre no guarda notas completas');
    }

    private function testLogEntryCreateSanitizesObservations(): void
    {
        $context = $this->openShift();
        $service = new LogEntryService();
        $longText = str_repeat('Observación operativa sensible. ', 25);

        $entryId = $service->create([
            'shift_id' => $context['shift_id'],
            'log_type_id' => $this->catalog['log_type_id'],
            'event_date' => date('Y-m-d'),
            'event_time' => '10:00',
            'observations' => $longText,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $payload = $this->latestAuditPayload(
            AuditService::RESOURCE_CCTV_LOG_ENTRY,
            (string) $entryId,
            AuditService::ACTION_CREATED
        );

        $this->assertSame(CctvAuditService::EVENT_LOG_ENTRY_CREATED, $payload['cctv_event'] ?? null, 'Creación registra log_entry_created');
        $this->assertTrue(isset($payload['observations_excerpt']), 'Creación guarda extracto de observaciones');
        $this->assertFalse(isset($payload['observations']), 'Creación no guarda observaciones completas');
    }

    private function testIncidentCreateAudit(): void
    {
        $context = $this->openShift();
        $service = new LogEntryService();

        $entryId = $service->createIncidentForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => '11:00',
            'incident_type_id' => $this->catalog['incident_type_id'],
            'sector_id' => $this->catalog['sector_id'],
            'camera_id' => $this->catalog['camera_id'],
            'observations' => 'Incidente auditado.',
            'coordination_notified' => 0,
            'police_arrived' => 0,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $rows = $this->latestAuditRows(
            AuditService::RESOURCE_CCTV_LOG_ENTRY,
            (string) $entryId,
            AuditService::ACTION_CREATED,
            5
        );

        $payload = [];
        foreach ($rows as $row) {
            $candidate = json_decode((string) ($row['new_values'] ?? ''), true);
            if (is_array($candidate) && ($candidate['cctv_event'] ?? '') === CctvAuditService::EVENT_INCIDENT_CREATED) {
                $payload = $candidate;
                break;
            }
        }

        $this->assertSame(CctvAuditService::EVENT_INCIDENT_CREATED, $payload['cctv_event'] ?? null, 'Incidente registra incident_created');
    }

    private function testLogEntryUpdateAudit(): void
    {
        $context = $this->openShift();
        $service = new LogEntryService();

        $entryId = $service->create([
            'shift_id' => $context['shift_id'],
            'log_type_id' => $this->catalog['log_type_id'],
            'event_date' => date('Y-m-d'),
            'event_time' => '12:00',
            'observations' => 'Original.',
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $service->update($entryId, [
            'event_date' => date('Y-m-d'),
            'event_time' => '12:30',
            'log_type_id' => $this->catalog['log_type_id'],
            'observations' => 'Editado.',
        ]);

        $rows = $this->latestAuditRows(
            AuditService::RESOURCE_CCTV_LOG_ENTRY,
            (string) $entryId,
            AuditService::ACTION_UPDATED,
            1
        );
        $newValues = json_decode((string) ($rows[0]['new_values'] ?? ''), true);

        $this->assertSame(CctvAuditService::EVENT_LOG_ENTRY_UPDATED, $newValues['cctv_event'] ?? null, 'Edición registra log_entry_updated');
    }

    private function testLogEntryCancelAudit(): void
    {
        $context = $this->openShift();
        $service = new LogEntryService();

        $entryId = $service->create([
            'shift_id' => $context['shift_id'],
            'log_type_id' => $this->catalog['log_type_id'],
            'event_date' => date('Y-m-d'),
            'event_time' => '13:00',
            'observations' => 'Será anulada.',
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $service->cancel($entryId);

        $pdo = Database::connection();
        $row = $pdo->prepare('SELECT deleted_at, cancelled_by FROM cctv_log_entries WHERE id = :id');
        $row->execute(['id' => $entryId]);
        $cancelled = $row->fetch();

        $this->assertTrue(!empty($cancelled['deleted_at']), 'Anulación aplica soft delete');
        $this->assertSame($this->adminId, (int) ($cancelled['cancelled_by'] ?? 0), 'Anulación registra quién anuló');

        $payload = $this->latestAuditPayload(
            AuditService::RESOURCE_CCTV_LOG_ENTRY,
            (string) $entryId,
            AuditService::ACTION_CANCELLED
        );

        $this->assertSame(CctvAuditService::EVENT_LOG_ENTRY_CANCELLED, $payload['cctv_event'] ?? null, 'Anulación registra log_entry_cancelled');
    }

    private function testCoordinationRegisteredAudit(): void
    {
        $context = $this->openShift();
        $service = new LogEntryService();

        $entryId = $service->createIncidentForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => '14:00',
            'incident_type_id' => $this->catalog['incident_type_id'],
            'sector_id' => $this->catalog['sector_id'],
            'camera_id' => $this->catalog['camera_id'],
            'observations' => 'Con coordinación.',
            'coordination_notified' => 1,
            'police_arrived' => 0,
            'contacts' => [[
                'contact_type' => 'carabineros',
                'contacted_at' => '15:00',
                'contact_name' => 'Teniente Prueba',
                'notes' => str_repeat('Detalle confidencial de coordinación. ', 10),
            ]],
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $rows = $this->latestAuditRows(
            AuditService::RESOURCE_CCTV_LOG_ENTRY,
            (string) $entryId,
            AuditService::ACTION_CREATED,
            2
        );

        $coordination = null;
        foreach ($rows as $row) {
            $payload = json_decode((string) ($row['new_values'] ?? ''), true);
            if (($payload['cctv_event'] ?? '') === CctvAuditService::EVENT_COORDINATION_REGISTERED) {
                $coordination = $payload;
                break;
            }
        }

        $this->assertTrue(is_array($coordination), 'Coordinación genera auditoría dedicada');
        $this->assertSame(1, $coordination['contacts_count'] ?? 0, 'Coordinación registra cantidad de contactos');
        $contact = $coordination['contacts'][0] ?? [];
        $this->assertTrue(isset($contact['notes_excerpt']), 'Contacto guarda extracto de notas');
        $this->assertFalse(isset($contact['notes']), 'Contacto no guarda notas completas');
    }

    private function testCameraStatusChangeAudit(): void
    {
        $cameraId = $this->catalog['camera_id'];
        if ($cameraId < 1) {
            $this->fail('cámara', 'No hay cámara disponible para prueba');

            return;
        }

        $context = $this->openShift();
        $service = new LogEntryService();
        $cameraService = new CameraService();
        $current = $cameraService->find($cameraId);
        $targetStatus = ($current['status'] ?? CameraCatalog::STATUS_OPERATIONAL) === CameraCatalog::STATUS_OUT_OF_SERVICE
            ? CameraCatalog::STATUS_OPERATIONAL
            : CameraCatalog::STATUS_OUT_OF_SERVICE;

        $entryId = $service->createTechnicalForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => '15:00',
            'technical_issue_type_id' => $this->catalog['technical_issue_type_id'],
            'equipment_id' => $this->catalog['equipment_id'],
            'camera_id' => $cameraId,
            'camera_status' => $targetStatus,
            'observations' => 'Cambio de estado por novedad técnica.',
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $rows = $this->latestAuditRows(
            AuditService::RESOURCE_CCTV_CAMERA,
            (string) $cameraId,
            AuditService::ACTION_UPDATED,
            1
        );
        $newValues = json_decode((string) ($rows[0]['new_values'] ?? ''), true);

        $this->assertSame(CctvAuditService::EVENT_CAMERA_STATUS_CHANGED, $newValues['cctv_event'] ?? null, 'Cambio de estado registra camera_status_changed');
        $this->assertSame($entryId, (int) ($newValues['source_log_entry_id'] ?? 0), 'Cambio de estado referencia entrada origen');
    }

    private function testClosedShiftUpdateAudit(): void
    {
        $shiftService = new ShiftService();
        $logService = new LogEntryService();
        $context = $this->openShift();

        $entryId = $logService->create([
            'shift_id' => $context['shift_id'],
            'log_type_id' => $this->catalog['log_type_id'],
            'event_date' => date('Y-m-d'),
            'event_time' => '16:00',
            'observations' => 'Antes del cierre.',
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $shiftService->close($context['shift_id']);

        Session::put('auth_user_id', $this->adminId);
        Auth::forgetCache();
        Permission::flush();

        $logService->update($entryId, [
            'event_date' => date('Y-m-d'),
            'event_time' => '16:30',
            'log_type_id' => $this->catalog['log_type_id'],
            'observations' => 'Corrección en turno cerrado.',
        ]);

        $rows = $this->latestAuditRows(
            AuditService::RESOURCE_CCTV_LOG_ENTRY,
            (string) $entryId,
            AuditService::ACTION_UPDATED_COMPLETED,
            1
        );
        $newValues = json_decode((string) ($rows[0]['new_values'] ?? ''), true);

        $this->assertSame(CctvAuditService::EVENT_LOG_ENTRY_UPDATED, $newValues['cctv_event'] ?? null, 'Edición en turno cerrado conserva evento de entrada');
        $this->assertTrue($newValues['closed_shift'] ?? false, 'Edición en turno cerrado marca closed_shift');
    }

    /**
     * @return array{shift_id: int}
     */
    private function openShift(): array
    {
        $shiftService = new ShiftService();
        $open = $shiftService->findOpenForOperator($this->adminId);
        if ($open !== null) {
            return ['shift_id' => (int) $open['id']];
        }

        $shiftId = $shiftService->open(['shift_date' => date('Y-m-d')], $this->adminId);
        $this->shiftIds[] = $shiftId;

        return ['shift_id' => $shiftId];
    }

    /**
     * @return array<string, mixed>
     */
    private function latestAuditPayload(string $resource, string $resourceId, string $action): array
    {
        $rows = $this->latestAuditRows($resource, $resourceId, $action, 1);
        $payload = json_decode((string) ($rows[0]['new_values'] ?? $rows[0]['old_values'] ?? ''), true);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function latestAuditRows(string $resource, string $resourceId, string $action, int $limit): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT action, old_values, new_values FROM audit_logs
             WHERE resource = :resource AND resource_id = :id AND action = :action
             ORDER BY id DESC LIMIT ' . max(1, $limit)
        );
        $stmt->execute([
            'resource' => $resource,
            'id' => $resourceId,
            'action' => $action,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    private function cleanup(): void
    {
        $pdo = Database::connection();

        foreach ($this->entryIds as $id) {
            $pdo->prepare('DELETE FROM cctv_log_contacts WHERE cctv_log_entry_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM cctv_log_entries WHERE id = :id')->execute(['id' => $id]);
        }

        foreach (array_reverse($this->shiftIds) as $id) {
            $pdo->prepare('DELETE FROM cctv_shift_equipment_checks WHERE cctv_shift_id = :id')->execute(['id' => $id]);
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

    private function pass(string $label): void
    {
        $this->passed++;
        echo '  PASS  ' . $label . PHP_EOL;
    }

    private function fail(string $label, string $detail): void
    {
        $this->failed++;
        $this->failures[] = $label . ': ' . $detail;
        echo '  FAIL  ' . $label . ' (' . $detail . ')' . PHP_EOL;
    }

    private function summary(): void
    {
        echo PHP_EOL . 'Resultado: ' . $this->passed . ' OK, ' . $this->failed . ' FAIL' . PHP_EOL;

        if ($this->failures !== []) {
            echo PHP_EOL . 'Detalle de fallos:' . PHP_EOL;
            foreach ($this->failures as $failure) {
                echo '  - ' . $failure . PHP_EOL;
            }
        }
    }
}

exit((new CctvAuditFunctionalTests())->run());
