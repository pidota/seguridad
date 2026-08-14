<?php

declare(strict_types=1);

/**
 * Pruebas funcionales de incidentes CCTV.
 * Ejecutar: php tests/cctv_incidents_functional.php
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
use App\Services\Cctv\CatalogService;
use App\Services\Cctv\LogEntryService;
use App\Services\Cctv\ShiftService;
use App\Validators\Cctv\IncidentStoreValidator;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Permission;
use Core\Request;
use Core\Session;

final class CctvIncidentsFunctionalTests
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var list<string> */
    private array $failures = [];

    /** @var list<int> */
    private array $entryIds = [];

    /** @var list<int> */
    private array $shiftIds = [];

    private int $adminId = 0;

    /** @var array{shift_id: int, incident_type_id: int|null} */
    private array $context = [
        'shift_id' => 0,
        'incident_type_id' => null,
    ];

    public function run(): int
    {
        $this->boot();

        try {
            $this->testNormalIncidentSavesCorrectly();
            $this->testPoliceYesRequiresArrivalTime();
            $this->testPoliceNoStoresNullArrivalTime();
            $this->testPoliceNotApplicableStoresNullArrivalTime();
            $this->testCoordinationYesAllowsSingleContact();
            $this->testCoordinationYesAllowsMultipleContacts();
            $this->testCoordinationNoIgnoresFraudulentContacts();
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
            $shiftService->close((int) $open['id'], 'Limpieza previa a pruebas de incidentes');
        }

        $shiftId = $shiftService->open(['shift_date' => date('Y-m-d')], $this->adminId);
        $this->shiftIds[] = $shiftId;

        $incidentTypeId = (int) $pdo->query(
            "SELECT id FROM cctv_incident_types WHERE slug = 'rina_via_publica' AND deleted_at IS NULL LIMIT 1"
        )->fetchColumn();

        $this->context = [
            'shift_id' => $shiftId,
            'incident_type_id' => $incidentTypeId > 0 ? $incidentTypeId : null,
        ];
    }

    private function testNormalIncidentSavesCorrectly(): void
    {
        $service = new LogEntryService();
        $this->assertTrue($this->context['incident_type_id'] !== null, 'Existe tipo de incidente de prueba');

        $entryId = $service->createIncidentForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => '09:15',
            'incident_type_id' => $this->context['incident_type_id'],
            'observations' => 'Incidente normal registrado correctamente en bitácora.',
            'coordination_notified' => 0,
            'police_arrived' => LogEntry::POLICE_ARRIVED_NO,
            'status' => LogEntry::STATUS_REGISTERED,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $entry = $service->find($entryId);
        $row = $this->fetchEntryRow($entryId);

        $this->assertSame($this->context['shift_id'], (int) ($entry['shift_id'] ?? 0), 'Incidente normal asocia turno abierto');
        $this->assertSame('incidente', $entry['log_type_slug'] ?? null, 'Incidente normal usa tipo incidente');
        $this->assertSame($this->context['incident_type_id'], $entry['incident_type_id'] ?? null, 'Incidente normal guarda clasificación');
        $this->assertStringContainsString('Incidente normal', (string) ($entry['observations'] ?? ''), 'Incidente normal persiste observaciones');
        $this->assertSame(LogEntry::STATUS_REGISTERED, $entry['status'], 'Incidente normal guarda estado');
        $this->assertSame(LogEntry::POLICE_ARRIVED_NO, (int) ($row['police_arrived'] ?? -1), 'Incidente normal persiste respuesta Carabineros = No');
    }

    private function testPoliceYesRequiresArrivalTime(): void
    {
        $validator = $this->incidentValidator();
        $service = new LogEntryService();

        $errors = $validator->validate([
            'event_date' => date('Y-m-d'),
            'event_time' => '10:00',
            'incident_type_id' => $this->context['incident_type_id'],
            'observations' => 'Incidente sin hora de Carabineros.',
            'coordination_notified' => '0',
            'police_arrived' => (string) LogEntry::POLICE_ARRIVED_YES,
            'police_arrival_time' => '',
            'status' => LogEntry::STATUS_REGISTERED,
        ]);
        $this->assertTrue(isset($errors['police_arrival_time']), 'Validador exige hora cuando Carabineros = Sí');

        $blocked = false;
        try {
            $service->createIncidentForOpenShift([
                'event_date' => date('Y-m-d'),
                'event_time' => '10:05',
                'incident_type_id' => $this->context['incident_type_id'],
                'observations' => 'Intento de guardar sin hora policial.',
                'coordination_notified' => 0,
                'police_arrived' => LogEntry::POLICE_ARRIVED_YES,
                'status' => LogEntry::STATUS_REGISTERED,
            ], $this->adminId);
        } catch (HttpException $e) {
            $blocked = $e->getStatusCode() === 422;
        }

        $this->assertTrue($blocked, 'Servicio rechaza Carabineros = Sí sin hora de llegada');

        $entryId = $service->createIncidentForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => '10:10',
            'incident_type_id' => $this->context['incident_type_id'],
            'observations' => 'Incidente con llegada policial confirmada.',
            'coordination_notified' => 0,
            'police_arrived' => LogEntry::POLICE_ARRIVED_YES,
            'police_arrival_time' => '10:25',
            'status' => LogEntry::STATUS_REGISTERED,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $row = $this->fetchEntryRow($entryId);
        $entry = $service->find($entryId);

        $this->assertSame(LogEntry::POLICE_ARRIVED_YES, (int) ($row['police_arrived'] ?? -1), 'Carabineros = Sí persiste valor en BD');
        $this->assertSame('10:25:00', (string) ($row['police_arrival_time'] ?? ''), 'Carabineros = Sí persiste hora en BD');
        $this->assertSame('10:25', $entry['police_arrival_time_formatted'], 'Carabineros = Sí formatea hora para UI');
    }

    private function testPoliceNoStoresNullArrivalTime(): void
    {
        $validator = $this->incidentValidator();
        $service = new LogEntryService();

        $errors = $validator->validate([
            'event_date' => date('Y-m-d'),
            'event_time' => '11:00',
            'incident_type_id' => $this->context['incident_type_id'],
            'observations' => 'Incidente con hora indebida y Carabineros = No.',
            'coordination_notified' => '0',
            'police_arrived' => (string) LogEntry::POLICE_ARRIVED_NO,
            'police_arrival_time' => '11:30',
            'status' => LogEntry::STATUS_REGISTERED,
        ]);
        $this->assertTrue(isset($errors['police_arrival_time']), 'Validador rechaza hora cuando Carabineros = No');

        $entryId = $service->createIncidentForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => '11:05',
            'incident_type_id' => $this->context['incident_type_id'],
            'observations' => 'Incidente donde Carabineros no llegó.',
            'coordination_notified' => 0,
            'police_arrived' => LogEntry::POLICE_ARRIVED_NO,
            'police_arrival_time' => '11:30',
            'status' => LogEntry::STATUS_REGISTERED,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $row = $this->fetchEntryRow($entryId);
        $entry = $service->find($entryId);

        $this->assertSame(LogEntry::POLICE_ARRIVED_NO, (int) ($row['police_arrived'] ?? -1), 'Carabineros = No persiste valor en BD');
        $this->assertNull($row['police_arrival_time'], 'Carabineros = No deja hora NULL en BD aunque el navegador envíe hora');
        $this->assertSame('—', $entry['police_arrival_time_formatted'], 'Carabineros = No no muestra hora en UI');
        $this->assertSame('No', $entry['police_arrived_label'], 'Carabineros = No muestra etiqueta correcta');
    }

    private function testPoliceNotApplicableStoresNullArrivalTime(): void
    {
        $validator = $this->incidentValidator();
        $service = new LogEntryService();

        $errors = $validator->validate([
            'event_date' => date('Y-m-d'),
            'event_time' => '12:00',
            'incident_type_id' => $this->context['incident_type_id'],
            'observations' => 'Incidente con hora indebida y Carabineros = No aplica.',
            'coordination_notified' => '0',
            'police_arrived' => (string) LogEntry::POLICE_ARRIVED_NOT_APPLICABLE,
            'police_arrival_time' => '12:15',
            'status' => LogEntry::STATUS_REGISTERED,
        ]);
        $this->assertTrue(isset($errors['police_arrival_time']), 'Validador rechaza hora cuando Carabineros = No aplica');

        $entryId = $service->createIncidentForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => '12:05',
            'incident_type_id' => $this->context['incident_type_id'],
            'observations' => 'Incidente donde Carabineros no aplica.',
            'coordination_notified' => 0,
            'police_arrived' => LogEntry::POLICE_ARRIVED_NOT_APPLICABLE,
            'police_arrival_time' => '12:15',
            'status' => LogEntry::STATUS_REGISTERED,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $row = $this->fetchEntryRow($entryId);
        $entry = $service->find($entryId);

        $this->assertSame(LogEntry::POLICE_ARRIVED_NOT_APPLICABLE, (int) ($row['police_arrived'] ?? -1), 'Carabineros = No aplica persiste valor en BD');
        $this->assertNull($row['police_arrival_time'], 'Carabineros = No aplica deja hora NULL en BD');
        $this->assertSame('—', $entry['police_arrival_time_formatted'], 'Carabineros = No aplica no muestra hora en UI');
        $this->assertSame('No aplica', $entry['police_arrived_label'], 'Carabineros = No aplica muestra etiqueta correcta');
    }

    private function testCoordinationYesAllowsSingleContact(): void
    {
        $service = new LogEntryService();

        $entryId = $service->createIncidentForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => '13:00',
            'incident_type_id' => $this->context['incident_type_id'],
            'observations' => 'Incidente con un aviso a Carabineros.',
            'coordination_notified' => 1,
            'contacts' => [
                [
                    'contact_type' => 'carabineros',
                    'contact_name' => 'Central 133',
                    'contacted_at' => '13:05',
                ],
            ],
            'police_arrived' => LogEntry::POLICE_ARRIVED_NOT_APPLICABLE,
            'status' => LogEntry::STATUS_REGISTERED,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $entry = $service->find($entryId);
        $contacts = $this->fetchContacts($entryId);

        $this->assertSame('Sí', $entry['coordination_notified_label'], 'Coordinación = Sí persiste aviso');
        $this->assertSame(1, count($entry['contacts'] ?? []), 'Coordinación = Sí permite un contacto');
        $this->assertSame(1, count($contacts), 'Coordinación = Sí persiste un contacto en BD');
        $this->assertSame('carabineros', $contacts[0]['contact_type'] ?? null, 'Contacto único guarda tipo');
        $this->assertSame('Central 133', $contacts[0]['contact_name'] ?? null, 'Contacto único guarda nombre');
    }

    private function testCoordinationYesAllowsMultipleContacts(): void
    {
        $service = new LogEntryService();

        $entryId = $service->createIncidentForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => '14:00',
            'incident_type_id' => $this->context['incident_type_id'],
            'observations' => 'Incidente con múltiples coordinaciones.',
            'coordination_notified' => 1,
            'contacts' => [
                [
                    'contact_type' => 'carabineros',
                    'contacted_at' => '14:05',
                ],
                [
                    'contact_type' => 'guardias_municipales',
                    'contacted_at' => '14:10',
                ],
                [
                    'contact_type' => 'samu',
                    'contacted_at' => '14:12',
                ],
            ],
            'police_arrived' => LogEntry::POLICE_ARRIVED_NO,
            'status' => LogEntry::STATUS_IN_PROGRESS,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $entry = $service->find($entryId);
        $contacts = $this->fetchContacts($entryId);

        $this->assertSame(3, count($entry['contacts'] ?? []), 'Coordinación = Sí permite varios contactos');
        $this->assertSame(3, count($contacts), 'Coordinación = Sí persiste varios contactos en BD');
        $this->assertSame('Carabineros — 14:05', $entry['contacts'][0]['display'] ?? null, 'Primer contacto múltiple formateado');
        $this->assertSame('Guardias Municipales — 14:10', $entry['contacts'][1]['display'] ?? null, 'Segundo contacto múltiple formateado');
        $this->assertSame('SAMU — 14:12', $entry['contacts'][2]['display'] ?? null, 'Tercer contacto múltiple formateado');
    }

    private function testCoordinationNoIgnoresFraudulentContacts(): void
    {
        $validator = $this->incidentValidator();
        $service = new LogEntryService();

        $errors = $validator->validate([
            'event_date' => date('Y-m-d'),
            'event_time' => '15:00',
            'incident_type_id' => $this->context['incident_type_id'],
            'observations' => 'Incidente sin coordinación declarada.',
            'coordination_notified' => '0',
            'police_arrived' => (string) LogEntry::POLICE_ARRIVED_NO,
            'contacts' => [
                [
                    'contact_type' => 'carabineros',
                    'contacted_at' => '15:01',
                ],
            ],
            'status' => LogEntry::STATUS_REGISTERED,
        ]);
        $this->assertFalse(isset($errors['contacts']), 'Validador no exige contactos cuando coordinación = No');

        $entryId = $service->createIncidentForOpenShift([
            'event_date' => date('Y-m-d'),
            'event_time' => '15:05',
            'incident_type_id' => $this->context['incident_type_id'],
            'observations' => 'Intento de inyectar contactos con coordinación = No.',
            'coordination_notified' => 0,
            'police_arrived' => LogEntry::POLICE_ARRIVED_NO,
            'contacts' => [
                [
                    'contact_type' => 'carabineros',
                    'contact_name' => 'Contacto fraudulento',
                    'contacted_at' => '15:06',
                ],
                [
                    'contact_type' => 'samu',
                    'contacted_at' => '15:07',
                ],
            ],
            'status' => LogEntry::STATUS_REGISTERED,
        ], $this->adminId);
        $this->entryIds[] = $entryId;

        $entry = $service->find($entryId);
        $contacts = $this->fetchContacts($entryId);
        $row = $this->fetchEntryRow($entryId);

        $this->assertSame(0, (int) ($row['coordination_notified'] ?? 1), 'Coordinación = No persiste valor 0 en BD');
        $this->assertSame('No', $entry['coordination_notified_label'], 'Coordinación = No muestra etiqueta correcta');
        $this->assertSame([], $entry['contacts'] ?? null, 'Coordinación = No no expone contactos en consulta');
        $this->assertSame(0, count($contacts), 'Coordinación = No no guarda contactos enviados desde el navegador');
    }

    private function incidentValidator(): IncidentStoreValidator
    {
        $incidentTypes = array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'allows_other' => !empty($row['allows_other']),
        ], (new CatalogService())->incidentTypeOptions());

        return new IncidentStoreValidator($incidentTypes);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchEntryRow(int $entryId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT police_arrived, police_arrival_time, coordination_notified
             FROM cctv_log_entries
             WHERE id = :id'
        );
        $stmt->execute(['id' => $entryId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchContacts(int $entryId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT contact_type, contact_name, contacted_at, notes
             FROM cctv_log_contacts
             WHERE cctv_log_entry_id = :id
             ORDER BY id ASC'
        );
        $stmt->execute(['id' => $entryId]);

        return $stmt->fetchAll() ?: [];
    }

    private function cleanup(): void
    {
        $pdo = Database::connection();

        foreach ($this->entryIds as $entryId) {
            $pdo->prepare('DELETE FROM cctv_log_contacts WHERE cctv_log_entry_id = :id')->execute(['id' => $entryId]);
            $pdo->prepare('DELETE FROM cctv_log_entries WHERE id = :id')->execute(['id' => $entryId]);
        }

        foreach ($this->shiftIds as $shiftId) {
            $pdo->prepare('DELETE FROM cctv_log_entries WHERE cctv_shift_id = :id')->execute(['id' => $shiftId]);
            $pdo->prepare('DELETE FROM cctv_shifts WHERE id = :id')->execute(['id' => $shiftId]);
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

    private function assertNull(mixed $actual, string $label): void
    {
        if ($actual === null) {
            $this->pass($label);
            return;
        }

        $this->fail($label, 'se esperaba null / obtenido ' . var_export($actual, true));
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

exit((new CctvIncidentsFunctionalTests())->run());
