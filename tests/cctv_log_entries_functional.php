<?php

declare(strict_types=1);

/**
 * Pruebas funcionales de entradas de bitácora CCTV.
 * Ejecutar: php tests/cctv_log_entries_functional.php
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
use App\Services\AuditService;
use App\Services\Cctv\CameraCatalog;
use App\Services\Cctv\LogEntryService;
use App\Services\Cctv\ShiftService;
use App\Services\Cctv\TechnicalEntryCatalog;
use Core\Auth;
use Core\Database;
use Core\Permission;
use Core\Request;
use Core\Session;

final class CctvLogEntriesFunctionalTests
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var list<string> */
    private array $failures = [];

    /** @var list<int> */
    private array $entryIds = [];

    /** @var list<int> */
    private array $shiftIds = [];

    private ?array $sharedContext = null;

    private int $adminId = 0;

    public function run(): int
    {
        $this->boot();

        try {
            $this->testSchemaUsesOccurredAt();
            $this->testBuildOccurredAt();
            $this->testCreateWithRelations();
            $this->testPresentSplitsDateAndTime();
            $this->testListByShift();
            $this->testSearchHistory();
            $this->testDetailForView();
            $this->testCreateForOpenShift();
            $this->testCreateIncidentForOpenShift();
            $this->testIncidentContactsValidation();
            $this->testPoliceArrivalValidation();
            $this->testCreateTechnicalForOpenShift();
            $this->testShiftTimeline();
            $this->testClosedShiftProtection();
            $this->testLogEntryOwnershipProtection();
            $this->testCancelLogEntry();
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

        $shiftService = new ShiftService();
        $open = $shiftService->findOpenForOperator($this->adminId);
        if ($open !== null) {
            $shiftService->close((int) $open['id'], 'Limpieza previa a pruebas');
        }
    }

    private function testSchemaUsesOccurredAt(): void
    {
        $pdo = Database::connection();
        $columns = $pdo->query("SHOW COLUMNS FROM cctv_log_entries")->fetchAll();
        $map = [];
        foreach ($columns as $column) {
            $map[(string) $column['Field']] = (string) $column['Type'];
        }

        $this->assertTrue(isset($map['occurred_at']), 'Existe columna occurred_at');
        $this->assertFalse(isset($map['event_at']), 'No queda columna legacy event_at');
        $this->assertTrue(isset($map['observations']), 'Existe columna observations');
        $this->assertTrue(isset($map['police_arrived']), 'Existe columna police_arrived');
        $this->assertTrue(isset($map['police_arrival_time']), 'Existe columna police_arrival_time');
        $this->assertTrue(isset($map['status']), 'Existe columna status');
        $this->assertStringContainsString('datetime', strtolower($map['occurred_at']), 'occurred_at es DATETIME');

        $contactColumns = $pdo->query("SHOW COLUMNS FROM cctv_log_contacts")->fetchAll();
        $contactMap = [];
        foreach ($contactColumns as $column) {
            $contactMap[(string) $column['Field']] = true;
        }

        $this->assertTrue(isset($contactMap['contact_type']), 'Existe columna contact_type en contactos');
        $this->assertTrue(isset($contactMap['contact_name']), 'Existe columna contact_name en contactos');
        $this->assertTrue(isset($contactMap['contacted_at']), 'Existe columna contacted_at en contactos');
        $this->assertFalse(isset($contactMap['contact_kind']), 'No queda columna legacy contact_kind');

        $technicalColumns = $pdo->query("SHOW COLUMNS FROM cctv_log_entries")->fetchAll();
        $entryMap = [];
        foreach ($technicalColumns as $column) {
            $entryMap[(string) $column['Field']] = true;
        }

        $this->assertTrue(isset($entryMap['cctv_technical_issue_type_id']), 'Existe columna cctv_technical_issue_type_id');
        $this->assertTrue(isset($entryMap['cctv_equipment_id']), 'Existe columna cctv_equipment_id');
        $this->assertTrue(isset($entryMap['camera_status_applied']), 'Existe columna camera_status_applied');

        $issueTypes = $pdo->query('SELECT COUNT(*) FROM cctv_technical_issue_types WHERE deleted_at IS NULL')->fetchColumn();
        $this->assertTrue((int) $issueTypes >= 1, 'Existen tipos de problema técnico');
    }

    private function testBuildOccurredAt(): void
    {
        $service = new LogEntryService();

        $this->assertSame('2026-08-14 14:30:00', $service->buildOccurredAt('2026-08-14', '14:30'), 'Combina fecha y hora');
        $this->assertSame('2026-08-14 00:00:00', $service->buildOccurredAt('2026-08-14'), 'Fecha sin hora usa medianoche');
    }

    private function testCreateWithRelations(): void
    {
        $context = $this->createShift();
        $service = new LogEntryService();

        $entryId = $service->create([
            'shift_id' => $context['shift_id'],
            'log_type_id' => $context['log_type_id'],
            'incident_type_id' => $context['incident_type_id'],
            'camera_id' => $context['camera_id'],
            'sector_id' => $context['sector_id'],
            'event_date' => '2026-08-14',
            'event_time' => '15:45',
            'observations' => 'Operador detecta riña en sector norte.',
            'police_arrived' => 1,
            'police_arrival_time' => '16:05',
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $entry = $service->find($entryId);
        $this->assertSame($context['shift_id'], $entry['shift_id'], 'Relaciona turno');
        $this->assertSame($context['log_type_id'], $entry['log_type_id'], 'Relaciona tipo de registro');
        $this->assertSame($context['incident_type_id'], $entry['incident_type_id'] ?? null, 'Relaciona tipo de incidente');
        $this->assertSame($context['camera_id'], $entry['camera_id'], 'Relaciona cámara opcional');
        $this->assertSame($context['sector_id'], $entry['sector_id'], 'Relaciona sector opcional');
        $this->assertSame($this->adminId, (int) ($entry['created_by'] ?? 0), 'Relaciona usuario creador');
        $this->assertSame('2026-08-14 15:45:00', $entry['occurred_at'], 'Persiste occurred_at');
        $this->assertSame(LogEntry::STATUS_RECORDED, $entry['status'], 'Estado inicial registrado');
        $this->assertSame('Sí', $entry['police_arrived_label'], 'Etiqueta de llegada policial');
        $this->assertSame('16:05', $entry['police_arrival_time_formatted'], 'Formatea hora policial');
        $this->assertSame('Operador detecta riña en sector norte.', $entry['observations'], 'Persiste observaciones');
        $this->assertNotSame('', (string) ($entry['log_type_name'] ?? ''), 'Present incluye nombre del tipo');
        $this->assertNotSame('', (string) ($entry['created_by_name'] ?? ''), 'Present incluye nombre del creador');
    }

    private function testPresentSplitsDateAndTime(): void
    {
        $service = new LogEntryService();
        $presented = $service->present([
            'occurred_at' => '2026-08-14 09:20:00',
            'cctv_shift_id' => 1,
            'cctv_log_type_id' => 1,
            'status' => LogEntry::STATUS_RECORDED,
        ]);

        $this->assertSame('2026-08-14', $presented['event_date'], 'UI recibe event_date');
        $this->assertSame('09:20', $presented['event_time'], 'UI recibe event_time');
        $this->assertSame('14-08-2026', $presented['event_date_formatted'], 'UI recibe fecha formateada');
        $this->assertSame('09:20', $presented['event_time_formatted'], 'UI recibe hora formateada');
    }

    private function testListByShift(): void
    {
        $context = $this->createShift();
        $service = new LogEntryService();

        $first = $service->create([
            'shift_id' => $context['shift_id'],
            'log_type_id' => $context['log_type_id'],
            'event_date' => '2026-08-14',
            'event_time' => '10:00',
            'observations' => 'Primera novedad del turno.',
        ], $this->adminId);
        $this->entryIds[] = $first;

        $second = $service->create([
            'shift_id' => $context['shift_id'],
            'log_type_id' => $context['log_type_id'],
            'event_date' => '2026-08-14',
            'event_time' => '11:00',
            'observations' => 'Segunda novedad del turno.',
        ], $this->adminId);
        $this->entryIds[] = $second;

        $entries = $service->listByShift($context['shift_id']);
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $entries);

        $this->assertTrue(in_array($first, $ids, true), 'Listado incluye primera entrada');
        $this->assertTrue(in_array($second, $ids, true), 'Listado incluye segunda entrada');

        $paged = $service->paginateByShift($context['shift_id'], 1, 10);
        $this->assertTrue($paged['total'] >= 2, 'Paginación cuenta entradas del turno');
    }

    private function testSearchHistory(): void
    {
        $shiftService = new ShiftService();
        $open = $shiftService->findOpenForOperator($this->adminId);
        if ($open !== null) {
            $shiftService->close((int) $open['id'], 'Cierre previo a historial');
        }

        $this->sharedContext = null;
        $context = $this->createShift();
        $service = new LogEntryService();

        $entryId = $service->createIncidentForOpenShift([
            'event_date' => '2026-08-14',
            'event_time' => '15:30',
            'incident_type_id' => $context['incident_type_id'],
            'sector_id' => $context['sector_id'],
            'camera_id' => $context['camera_id'],
            'observations' => 'Incidente histórico de prueba para bitácora general.',
            'coordination_notified' => 1,
            'contacts' => [
                ['contact_type' => 'carabineros', 'contacted_at' => '15:31'],
            ],
            'police_arrived' => LogEntry::POLICE_ARRIVED_YES,
            'police_arrival_time' => '15:45',
            'status' => LogEntry::STATUS_REGISTERED,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $result = $service->searchHistory([
            'created_by' => (string) $this->adminId,
            'date_from' => '2026-08-14',
            'date_to' => '2026-08-14',
            'log_type' => 'incidente',
            'sector_id' => (string) $context['sector_id'],
            'camera_id' => (string) $context['camera_id'],
            'contact_type' => 'carabineros',
            'status' => LogEntry::STATUS_REGISTERED,
            'police_arrived' => (string) LogEntry::POLICE_ARRIVED_YES,
            'q' => 'histórico de prueba',
        ], 1, 10);

        $this->assertTrue($result['total'] >= 1, 'Historial general cuenta registros filtrados');
        $this->assertTrue($result['pages'] >= 1, 'Historial general calcula páginas');

        $row = null;
        foreach ($result['data'] as $item) {
            if ((int) ($item['id'] ?? 0) === $entryId) {
                $row = $item;
                break;
            }
        }

        $this->assertTrue($row !== null, 'Historial general incluye el registro creado');
        $this->assertSame('14-08-2026', $row['event_date_formatted'] ?? null, 'Historial incluye fecha');
        $this->assertSame('15:30', $row['event_time_formatted'] ?? null, 'Historial incluye hora');
        $this->assertSame('Sí', $row['coordination_label'] ?? null, 'Historial incluye coordinaciones');
        $this->assertStringContainsString('Sí', (string) ($row['police_label'] ?? ''), 'Historial incluye llegada de Carabineros');
        $this->assertTrue(($row['incident_label'] ?? '—') !== '—', 'Historial incluye tipo de incidente');
        $this->assertTrue(($row['camera_label'] ?? '—') !== '—', 'Historial incluye cámara');

        $operators = $service->operatorOptions();
        $this->assertTrue($operators !== [], 'Historial expone operadores con registros');

        $byContact = $service->searchHistory(['contact_type' => 'carabineros'], 1, 50);
        $contactIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $byContact['data']);
        $this->assertTrue(in_array($entryId, $contactIds, true), 'Filtro por institución contactada funciona');

        $ignoredShortQuery = $service->searchHistory(['q' => 'a'], 1, 50);
        $this->assertTrue($ignoredShortQuery['total'] >= $result['total'], 'Búsqueda corta no restringe indebidamente');
    }

    private function testDetailForView(): void
    {
        $shiftService = new ShiftService();
        $open = $shiftService->findOpenForOperator($this->adminId);
        if ($open !== null) {
            $shiftService->close((int) $open['id'], 'Cierre previo a detalle');
        }

        $this->sharedContext = null;
        $context = $this->createShift();
        $service = new LogEntryService();

        $entryId = $service->createIncidentForOpenShift([
            'event_date' => '2026-08-14',
            'event_time' => '16:10',
            'incident_type_id' => $context['incident_type_id'],
            'sector_id' => $context['sector_id'],
            'camera_id' => $context['camera_id'],
            'observations' => 'Detalle completo de registro para prueba funcional.',
            'coordination_notified' => 1,
            'contacts' => [
                [
                    'contact_type' => 'carabineros',
                    'contact_name' => 'Teniente Pérez',
                    'contacted_at' => '16:12',
                    'notes' => 'Se informa situación en sector.',
                ],
            ],
            'police_arrived' => LogEntry::POLICE_ARRIVED_YES,
            'police_arrival_time' => '16:25',
            'status' => LogEntry::STATUS_IN_PROGRESS,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $detail = $service->detailForView($entryId, $this->adminId);

        $this->assertSame($entryId, (int) ($detail['id'] ?? 0), 'Detalle expone el registro consultado');
        $this->assertSame('16:10', $detail['event_time_formatted'] ?? null, 'Detalle incluye hora del evento');
        $this->assertTrue(($detail['shift_label'] ?? '—') !== '—', 'Detalle incluye turno');
        $this->assertSame('Detalle completo de registro para prueba funcional.', $detail['observations'] ?? null, 'Detalle conserva observaciones completas');
        $this->assertTrue(!empty($detail['show_coordinations']), 'Detalle marca sección de coordinaciones');
        $this->assertSame(1, count($detail['contacts'] ?? []), 'Detalle incluye contactos');
        $this->assertSame('Teniente Pérez', $detail['contacts'][0]['contact_person_label'] ?? null, 'Detalle incluye persona de contacto');
        $this->assertSame('Sí', $detail['police_arrived_label'] ?? null, 'Detalle incluye llegada de Carabineros');
        $this->assertTrue(($detail['created_at_formatted'] ?? '—') !== '—', 'Detalle incluye fecha de creación');
    }

    private function testAuditTrail(): void
    {
        $shiftService = new ShiftService();
        $open = $shiftService->findOpenForOperator($this->adminId);
        if ($open !== null) {
            $shiftService->close((int) $open['id'], 'Cierre previo a auditoría');
        }

        $this->sharedContext = null;
        $context = $this->createShift();
        $service = new LogEntryService();

        $entryId = $service->create([
            'shift_id' => $context['shift_id'],
            'log_type_id' => $context['log_type_id'],
            'event_date' => '2026-08-14',
            'event_time' => '12:00',
            'observations' => 'Entrada auditada.',
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT action, module, resource FROM audit_logs
             WHERE resource = :resource AND resource_id = :id
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([
            'resource' => AuditService::RESOURCE_CCTV_LOG_ENTRY,
            'id' => (string) $entryId,
        ]);
        $row = $stmt->fetch();

        $this->assertSame('created', $row['action'] ?? null, 'Create registra auditoría');
        $this->assertSame(AuditService::MODULE_CCTV, $row['module'] ?? null, 'Auditoría usa módulo cctv');
    }

    private function testCreateForOpenShift(): void
    {
        $context = $this->sharedContext ?? $this->createShift();
        $service = new LogEntryService();
        $shiftService = new ShiftService();

        $entryId = $service->createForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'log_type_id' => $context['log_type_id'],
            'observations' => 'Novedad simple registrada durante turno abierto.',
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $entry = $service->find($entryId);
        $this->assertSame($context['shift_id'], $entry['shift_id'], 'createForOpenShift asocia turno abierto');

        $shiftService->close($context['shift_id']);

        $blocked = false;
        try {
            $service->createForOpenShift([
                'event_date' => date('Y-m-d'),
                'event_time' => date('H:i'),
                'log_type_id' => $context['log_type_id'],
                'observations' => 'Intento con turno cerrado.',
            ], $this->adminId);
        } catch (\Core\Exceptions\HttpException $e) {
            $blocked = $e->getStatusCode() === 422;
        }

        $this->assertTrue($blocked, 'No permite registrar si el turno ya no está abierto');
    }

    private function testCreateIncidentForOpenShift(): void
    {
        $shiftService = new ShiftService();
        $open = $shiftService->findOpenForOperator($this->adminId);
        if ($open !== null) {
            $shiftService->close((int) $open['id'], 'Cierre previo a incidente');
        }

        $this->sharedContext = null;
        $context = $this->createShift();
        $service = new LogEntryService();

        $entryId = $service->createIncidentForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => '10:35',
            'incident_type_id' => $context['incident_type_id'],
            'sector_id' => $context['sector_id'],
            'camera_id' => $context['camera_id'],
            'observations' => 'Sujetos consumiendo alcohol en vía pública.',
            'coordination_notified' => 1,
            'contacts' => [
                [
                    'contact_type' => 'carabineros',
                    'contacted_at' => '10:38',
                ],
                [
                    'contact_type' => 'guardias_municipales',
                    'contacted_at' => '10:41',
                ],
            ],
            'police_arrived' => 1,
            'police_arrival_time' => '11:05',
            'status' => LogEntry::STATUS_IN_PROGRESS,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $entry = $service->find($entryId);
        $this->assertSame($context['shift_id'], $entry['shift_id'], 'Incidente asocia turno abierto');
        $this->assertSame($context['incident_type_id'], $entry['incident_type_id'], 'Incidente guarda tipo');
        $this->assertSame(LogEntry::STATUS_IN_PROGRESS, $entry['status'], 'Incidente guarda estado');
        $this->assertSame('Sí', $entry['coordination_notified_label'], 'Incidente guarda aviso/coordinación');
        $this->assertSame(2, count($entry['contacts'] ?? []), 'Incidente guarda contactos múltiples');
        $this->assertSame('Carabineros — 10:38', $entry['contacts'][0]['display'] ?? null, 'Primer contacto formateado');
        $this->assertSame('Guardias Municipales — 10:41', $entry['contacts'][1]['display'] ?? null, 'Segundo contacto formateado');
        $this->assertStringContainsString('Carabineros — 10:38', $entry['contacts_summary'] ?? '', 'Resumen de contactos');
        $this->assertSame('Sí', $entry['police_arrived_label'], 'Incidente guarda llegada de Carabineros');
        $this->assertSame('11:05', $entry['police_arrival_time_formatted'], 'Incidente guarda hora de Carabineros');
        $this->assertSame('incidente', $entry['log_type_slug'] ?? null, 'Incidente usa tipo de registro incidente');
    }

    private function testIncidentContactsValidation(): void
    {
        $context = $this->sharedContext ?? $this->createShift();
        $catalogs = new \App\Services\Cctv\CatalogService();
        $incidentTypes = array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'allows_other' => !empty($row['allows_other']),
        ], $catalogs->incidentTypeOptions());
        $validator = new \App\Validators\Cctv\IncidentStoreValidator($incidentTypes);

        $errors = $validator->validate([
            'event_date' => date('Y-m-d'),
            'event_time' => '10:35',
            'incident_type_id' => $context['incident_type_id'],
            'observations' => 'Incidente con aviso sin contactos.',
            'coordination_notified' => '1',
            'police_arrived' => '2',
            'contacts' => [],
            'status' => LogEntry::STATUS_REGISTERED,
        ]);

        $this->assertTrue(isset($errors['contacts']), 'Exige contactos cuando hay aviso/coordinación');

        $errors = $validator->validate([
            'event_date' => date('Y-m-d'),
            'event_time' => '10:35',
            'incident_type_id' => $context['incident_type_id'],
            'observations' => 'Incidente sin aviso ni contactos.',
            'coordination_notified' => '0',
            'police_arrived' => '0',
            'contacts' => [],
            'status' => LogEntry::STATUS_REGISTERED,
        ]);

        $this->assertFalse(isset($errors['contacts']), 'No exige contactos cuando no hubo aviso');
    }

    private function testPoliceArrivalValidation(): void
    {
        $context = $this->sharedContext ?? $this->createShift();
        $catalogs = new \App\Services\Cctv\CatalogService();
        $incidentTypes = array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'allows_other' => !empty($row['allows_other']),
        ], $catalogs->incidentTypeOptions());
        $validator = new \App\Validators\Cctv\IncidentStoreValidator($incidentTypes);
        $service = new LogEntryService();

        $errors = $validator->validate([
            'event_date' => date('Y-m-d'),
            'event_time' => '10:35',
            'incident_type_id' => $context['incident_type_id'],
            'observations' => 'Incidente sin respuesta de Carabineros.',
            'coordination_notified' => '0',
            'police_arrived' => '1',
            'status' => LogEntry::STATUS_REGISTERED,
        ]);
        $this->assertTrue(isset($errors['police_arrival_time']), 'Exige hora cuando Carabineros llegó');

        $errors = $validator->validate([
            'event_date' => date('Y-m-d'),
            'event_time' => '10:35',
            'incident_type_id' => $context['incident_type_id'],
            'observations' => 'Incidente con hora indebida.',
            'coordination_notified' => '0',
            'police_arrived' => '2',
            'police_arrival_time' => '11:00',
            'status' => LogEntry::STATUS_REGISTERED,
        ]);
        $this->assertTrue(isset($errors['police_arrival_time']), 'Rechaza hora cuando no aplica');

        $entryId = $service->createIncidentForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => '10:35',
            'incident_type_id' => $context['incident_type_id'],
            'observations' => 'Incidente donde no aplica Carabineros.',
            'coordination_notified' => 0,
            'police_arrived' => LogEntry::POLICE_ARRIVED_NOT_APPLICABLE,
            'police_arrival_time' => '11:00',
            'status' => LogEntry::STATUS_REGISTERED,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $entry = $service->find($entryId);
        $this->assertSame('No aplica', $entry['police_arrived_label'], 'Guarda etiqueta No aplica');
        $this->assertSame('—', $entry['police_arrival_time_formatted'], 'No guarda hora cuando no aplica');

        $entryIdNo = $service->createIncidentForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => '10:40',
            'incident_type_id' => $context['incident_type_id'],
            'observations' => 'Incidente donde no llegó Carabineros.',
            'coordination_notified' => 0,
            'police_arrived' => LogEntry::POLICE_ARRIVED_NO,
            'police_arrival_time' => '11:15',
            'status' => LogEntry::STATUS_REGISTERED,
        ], $this->adminId);
        $this->entryIds[] = $entryIdNo;

        $entryNo = $service->find($entryIdNo);
        $this->assertSame('No', $entryNo['police_arrived_label'], 'Guarda etiqueta No');
        $this->assertSame('—', $entryNo['police_arrival_time_formatted'], 'No guarda hora cuando respondió No');
    }

    private function testCreateTechnicalForOpenShift(): void
    {
        $context = $this->sharedContext ?? $this->createShift();
        $pdo = Database::connection();
        $service = new LogEntryService();

        $issueTypeId = (int) $pdo->query(
            "SELECT id FROM cctv_technical_issue_types WHERE slug = 'sin_senal' AND deleted_at IS NULL LIMIT 1"
        )->fetchColumn();
        $equipmentId = (int) $pdo->query(
            "SELECT id FROM cctv_equipment WHERE slug = 'monitores' AND deleted_at IS NULL LIMIT 1"
        )->fetchColumn();

        $this->assertTrue($issueTypeId > 0, 'Hay tipo de problema técnico de prueba');
        $this->assertTrue($equipmentId > 0, 'Hay equipo de prueba');

        $cameraId = (int) ($context['camera_id'] ?? 0);
        if ($cameraId < 1) {
            $pdo->prepare(
                'INSERT INTO cctv_cameras (code, name, camera_type, status, active)
                 VALUES (:code, :name, :camera_type, :status, 1)'
            )->execute([
                'code' => 'TST-CAM-01',
                'name' => 'Cámara de prueba',
                'camera_type' => 'fija',
                'status' => CameraCatalog::STATUS_OPERATIONAL,
            ]);
            $cameraId = (int) $pdo->lastInsertId();
        }

        $entryId = $service->createTechnicalForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => '09:15',
            'target_type' => 'camera',
            'camera_id' => $cameraId,
            'technical_issue_type_id' => $issueTypeId,
            'observations' => 'Cámara norte sin señal desde las 09:10.',
            'status' => TechnicalEntryCatalog::STATUS_DETECTED,
            'camera_status' => CameraCatalog::STATUS_ISSUES,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $entry = $service->find($entryId);
        $this->assertSame('novedad_tecnica', $entry['log_type_slug'] ?? null, 'Usa tipo de registro novedad técnica');
        $this->assertSame('Sin señal', $entry['technical_issue_display'] ?? null, 'Guarda tipo de problema');
        $this->assertSame('Detectado', $entry['status_label'], 'Guarda estado técnico');
        $this->assertSame('Con problemas', $entry['camera_status_applied_label'], 'Registra estado de cámara aplicado');

        $cameraStatus = (string) $pdo->query('SELECT status FROM cctv_cameras WHERE id = ' . $cameraId)->fetchColumn();
        $this->assertSame(CameraCatalog::STATUS_ISSUES, $cameraStatus, 'Actualiza estado de la cámara');

        $equipmentEntryId = $service->createTechnicalForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => '09:20',
            'target_type' => 'equipment',
            'equipment_id' => $equipmentId,
            'technical_issue_type_id' => $issueTypeId,
            'observations' => 'Monitor principal con imagen intermitente.',
            'status' => TechnicalEntryCatalog::STATUS_PENDING,
        ], $this->adminId);
        $this->entryIds[] = $equipmentEntryId;

        $equipmentEntry = $service->find($equipmentEntryId);
        $this->assertSame($equipmentId, $equipmentEntry['equipment_id'], 'Novedad técnica asocia equipo');
        $this->assertSame('Pendiente', $equipmentEntry['status_label'], 'Guarda estado pendiente en equipo');
    }

    private function testShiftTimeline(): void
    {
        $service = new LogEntryService();
        $context = $this->sharedContext ?? $this->createShift();
        $shiftService = new ShiftService();

        $entryId = $service->create([
            'shift_id' => $context['shift_id'],
            'log_type_id' => $context['log_type_id'],
            'event_date' => '2026-08-14',
            'event_time' => '10:35',
            'observations' => 'Sujetos consumiendo alcohol en vía pública.',
            'camera_id' => $context['camera_id'],
            'sector_id' => $context['sector_id'],
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $openShift = $shiftService->find($context['shift_id']);
        $timelineDesc = $service->shiftTimeline($openShift, [], 'desc');
        $timelineAsc = $service->shiftTimeline($openShift, [], 'asc');

        $this->assertTrue(($timelineDesc['total'] ?? 0) >= 2, 'Timeline incluye inicio de turno y registros');
        $this->assertSame('desc', $timelineDesc['order'], 'Timeline respeta orden descendente');
        $this->assertSame('asc', $timelineAsc['order'], 'Timeline respeta orden ascendente');

        $descTimes = array_map(static fn (array $row): string => (string) ($row['time_label'] ?? ''), $timelineDesc['items']);
        $ascTimes = array_map(static fn (array $row): string => (string) ($row['time_label'] ?? ''), $timelineAsc['items']);
        $this->assertNotSame($descTimes, $ascTimes, 'El orden cambia la secuencia mostrada');

        $opening = array_values(array_filter(
            $timelineAsc['items'],
            static fn (array $row): bool => ($row['kind'] ?? '') === 'shift_opening'
        ));
        $this->assertSame('INICIO DE TURNO', $opening[0]['type_label'] ?? null, 'Timeline incluye inicio de turno');
    }

    private function testClosedShiftProtection(): void
    {
        $pdo = Database::connection();
        $shiftService = new ShiftService();
        $service = new LogEntryService();
        $context = $this->createShift();

        $entryId = $service->createForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'log_type_id' => $context['log_type_id'],
            'observations' => 'Entrada para prueba de turno cerrado.',
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $shiftService->close($context['shift_id'], 'Cierre para prueba de protección');
        $this->sharedContext = null;

        $operatorId = $this->loginAsOperadorCamaras();
        $blocked = false;

        try {
            $service->update($entryId, [
                'event_date' => date('Y-m-d'),
                'event_time' => date('H:i'),
                'log_type_id' => $context['log_type_id'],
                'observations' => 'Intento de edición sin permiso.',
            ]);
        } catch (\Core\Exceptions\HttpException $e) {
            $blocked = $e->getStatusCode() === 403;
        }

        $this->assertTrue($blocked, 'Operador normal no edita registros de turno cerrado');

        Session::put('auth_user_id', $this->adminId);
        Auth::forgetCache();
        Permission::flush();

        $service->update($entryId, [
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'log_type_id' => $context['log_type_id'],
            'observations' => 'Corrección autorizada en turno cerrado.',
        ]);

        $updated = $service->find($entryId);
        $this->assertStringContainsString(
            'Corrección autorizada',
            (string) ($updated['observations'] ?? ''),
            'Superadmin puede editar con permiso edit_closed'
        );

        $stmt = $pdo->prepare(
            'SELECT action, old_values, new_values FROM audit_logs
             WHERE resource = :resource AND resource_id = :id AND action = :action
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([
            'resource' => AuditService::RESOURCE_CCTV_LOG_ENTRY,
            'id' => (string) $entryId,
            'action' => AuditService::ACTION_UPDATED_COMPLETED,
        ]);
        $audit = $stmt->fetch();

        $this->assertSame(AuditService::ACTION_UPDATED_COMPLETED, $audit['action'] ?? null, 'Auditoría registra modificación posterior');
        $this->assertTrue(is_string($audit['old_values'] ?? null) && $audit['old_values'] !== '', 'Auditoría guarda valores anteriores');
        $this->assertTrue(is_string($audit['new_values'] ?? null) && $audit['new_values'] !== '', 'Auditoría guarda valores nuevos');

        $pdo->prepare('DELETE FROM user_roles WHERE user_id = :id')->execute(['id' => $operatorId]);
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $operatorId]);
    }

    private function testLogEntryOwnershipProtection(): void
    {
        $service = new LogEntryService();
        $context = $this->createShift();

        $entryId = $service->createForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'log_type_id' => $context['log_type_id'],
            'observations' => 'Entrada de otro operador para prueba IDOR.',
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $operatorId = $this->loginAsOperadorCamaras();
        $blockedUpdate = false;

        try {
            $service->update($entryId, [
                'event_date' => date('Y-m-d'),
                'event_time' => date('H:i'),
                'log_type_id' => $context['log_type_id'],
                'observations' => 'Intento IDOR en edición.',
            ]);
        } catch (\Core\Exceptions\HttpException $e) {
            $blockedUpdate = $e->getStatusCode() === 403;
        }

        $this->assertTrue($blockedUpdate, 'Operador no edita registros ajenos en turno abierto');

        Session::put('auth_user_id', $this->adminId);
        Auth::forgetCache();
        Permission::flush();

        $service->update($entryId, [
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'log_type_id' => $context['log_type_id'],
            'observations' => 'Corrección del autor con view_all.',
        ]);

        $updated = $service->find($entryId);
        $this->assertStringContainsString(
            'Corrección del autor',
            (string) ($updated['observations'] ?? ''),
            'Supervisor con view_all puede editar registros ajenos'
        );

        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM user_roles WHERE user_id = :id')->execute(['id' => $operatorId]);
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $operatorId]);
    }

    private function testCancelLogEntry(): void
    {
        $pdo = Database::connection();
        $service = new LogEntryService();
        $context = $this->createShift();

        $entryId = $service->createForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'log_type_id' => $context['log_type_id'],
            'observations' => 'Entrada para anulación.',
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $operatorId = $this->loginAsOperadorCamaras();
        $blocked = false;

        try {
            $service->cancel($entryId);
        } catch (\Core\Exceptions\HttpException $e) {
            $blocked = $e->getStatusCode() === 403;
        }

        $this->assertTrue($blocked, 'Operador sin permiso delete no puede anular');

        Session::put('auth_user_id', $this->adminId);
        Auth::forgetCache();
        Permission::flush();

        $service->cancel($entryId);

        $row = $pdo->prepare('SELECT deleted_at, cancelled_by FROM cctv_log_entries WHERE id = :id');
        $row->execute(['id' => $entryId]);
        $cancelled = $row->fetch();

        $this->assertTrue(!empty($cancelled['deleted_at']), 'Anulación usa deleted_at');
        $this->assertSame($this->adminId, (int) ($cancelled['cancelled_by'] ?? 0), 'Anulación registra cancelled_by');

        $blockedView = false;
        try {
            $service->find($entryId);
        } catch (\Core\Exceptions\HttpException $e) {
            $blockedView = $e->getStatusCode() === 404;
        }

        $this->assertTrue($blockedView, 'Registro anulado no aparece en consulta operativa');

        $openShift = (new ShiftService())->find($context['shift_id']);
        $timeline = $service->shiftTimeline($openShift);
        $ids = array_map(
            static fn (array $item): int => (int) ($item['id'] ?? 0),
            array_filter(
                $timeline['items'] ?? [],
                static fn (array $item): bool => ($item['kind'] ?? '') === 'log_entry'
            )
        );
        $this->assertFalse(in_array($entryId, $ids, true), 'Registro anulado no aparece en bitácora del turno');

        $pdo->prepare('DELETE FROM user_roles WHERE user_id = :id')->execute(['id' => $operatorId]);
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $operatorId]);
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

        $email = 'cctv.closed.guard.' . bin2hex(random_bytes(4)) . '@municipalidad.local';
        $pdo->prepare(
            'INSERT INTO users (name, email, password, is_active, must_change_password)
             VALUES (:name, :email, :password, 1, 0)'
        )->execute([
            'name' => 'Operador protección CCTV',
            'email' => $email,
            'password' => password_hash('TestCamera123!', PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
            ->execute(['user_id' => $userId, 'role_id' => $roleId]);

        Session::put('auth_user_id', $userId);
        Auth::forgetCache();
        Permission::flush();

        return $userId;
    }

    /**
     * @return array{
     *     shift_id: int,
     *     log_type_id: int,
     *     incident_type_id: int,
     *     camera_id: int|null,
     *     sector_id: int|null
     * }
     */
    private function createShift(): array
    {
        if ($this->sharedContext !== null) {
            return $this->sharedContext;
        }

        $pdo = Database::connection();
        $shiftService = new ShiftService();

        $shiftId = $shiftService->open(['shift_date' => date('Y-m-d')], $this->adminId);
        $this->shiftIds[] = $shiftId;

        $logTypeId = (int) $pdo->query(
            "SELECT id FROM cctv_log_types WHERE slug = 'incidente' AND deleted_at IS NULL LIMIT 1"
        )->fetchColumn();

        $incidentTypeId = (int) $pdo->query(
            "SELECT id FROM cctv_incident_types WHERE slug = 'rina_via_publica' AND deleted_at IS NULL LIMIT 1"
        )->fetchColumn();

        $cameraId = $pdo->query(
            'SELECT id FROM cctv_cameras WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1'
        )->fetchColumn();
        $cameraId = $cameraId !== false ? (int) $cameraId : null;

        $sectorId = $pdo->query(
            'SELECT id FROM sectors WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1'
        )->fetchColumn();
        $sectorId = $sectorId !== false ? (int) $sectorId : null;

        $this->sharedContext = [
            'shift_id' => $shiftId,
            'log_type_id' => $logTypeId,
            'incident_type_id' => $incidentTypeId > 0 ? $incidentTypeId : null,
            'camera_id' => $cameraId,
            'sector_id' => $sectorId,
        ];

        return $this->sharedContext;
    }

    private function cleanup(): void
    {
        $pdo = Database::connection();

        foreach (array_reverse($this->entryIds) as $id) {
            $pdo->prepare('DELETE FROM cctv_log_contacts WHERE cctv_log_entry_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM cctv_log_entries WHERE id = :id')->execute(['id' => $id]);
        }

        $shiftService = new ShiftService();
        foreach (array_reverse($this->shiftIds) as $id) {
            $open = $shiftService->find($id);
            if (($open['status'] ?? '') === 'open') {
                $shiftService->close($id, 'Limpieza de prueba');
            }
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

exit((new CctvLogEntriesFunctionalTests())->run());
