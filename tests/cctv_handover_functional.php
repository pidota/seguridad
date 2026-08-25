<?php

declare(strict_types=1);

/**
 * Pruebas de traspaso de incidentes/novedades entre turnos CCTV.
 * Ejecutar: php tests/cctv_handover_functional.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse por consola.\n");
    exit(1);
}

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/cctv';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/index.php';

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Models\Cctv\LogEntry;
use App\Models\Cctv\LogHandover;
use App\Models\Cctv\ShiftEquipmentCheck;
use App\Repositories\Cctv\EquipmentRepository;
use App\Repositories\Cctv\LogHandoverRepository;
use App\Services\Cctv\LogHandoverService;
use App\Services\Cctv\LogEntryService;
use App\Services\Cctv\ShiftService;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Permission;
use Core\Session;

final class CctvHandoverFunctionalTests
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
    private array $handoverIds = [];

    private int $operatorA = 0;
    private int $operatorB = 0;

    public function run(): int
    {
        $this->boot();

        try {
            $this->testCloseWithoutPending();
            $this->testCloseWithPendingCreatesHandovers();
            $this->testIncomingAcceptsContinuity();
            $this->testIncomingDeclineRequiresJustification();
            $this->testCannotEditWithoutReview();
            $this->testDeclineRejectsMissingReason();
            $this->testWrongOperatorForbidden();
        } catch (\Throwable $e) {
            $this->fail('ejecución', $e->getMessage());
        } finally {
            $this->cleanup();
        }

        $this->printSummary();

        return $this->failed === 0 ? 0 : 1;
    }

    private function boot(): void
    {
        Session::start();

        $pdo = Database::connection();
        $this->operatorA = (int) $pdo->query(
            "SELECT u.id FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.slug = 'superadministrador' LIMIT 1"
        )->fetchColumn();

        $this->operatorB = (int) $pdo->query(
            "SELECT u.id FROM users u WHERE u.id <> {$this->operatorA} AND u.is_active = 1 ORDER BY u.id ASC LIMIT 1"
        )->fetchColumn();

        if ($this->operatorB < 1) {
            throw new \RuntimeException('Se requiere un segundo usuario activo para las pruebas.');
        }

        $roleId = (int) $pdo->query("SELECT id FROM roles WHERE slug = 'operador_camaras' LIMIT 1")->fetchColumn();
        if ($roleId > 0) {
            $pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id')->execute(['user_id' => $this->operatorB]);
            $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')->execute([
                'user_id' => $this->operatorB,
                'role_id' => $roleId,
            ]);
        }

        Session::put('auth_user_id', $this->operatorA);
        Auth::forgetCache();
        Permission::flush();
    }

    private function testCloseWithoutPending(): void
    {
        $shiftService = new ShiftService();
        $shiftId = $this->openShift($this->operatorA);
        $shiftService->closeWithDelivery($shiftId, $this->closingPayload());

        $repo = new LogHandoverRepository();
        $this->assert($repo->listPendingForShift($shiftId, $this->operatorA) === [], 'cierre sin pendientes no crea handovers');
    }

    private function testCloseWithPendingCreatesHandovers(): void
    {
        $shiftService = new ShiftService();
        $logService = new LogEntryService();
        $shiftId = $this->openShift($this->operatorA);

        Session::put('auth_user_id', $this->operatorA);
        Auth::forgetCache();

        $entryId = $this->createIncidentInProgress($logService, $shiftId);
        $shiftService->closeWithDelivery($shiftId, $this->closingPayload([
            'handover_notes' => [$entryId => 'Patrulla aún no confirma llegada.'],
        ]));

        $repo = new LogHandoverRepository();
        $rows = $pdo = Database::connection()->query(
            "SELECT * FROM cctv_log_handovers WHERE log_entry_id = {$entryId} ORDER BY id DESC LIMIT 1"
        )->fetch();

        $this->assert($rows !== false, 'cierre con pendiente crea handover');
        $this->assert(($rows['handover_status'] ?? '') === LogHandover::STATUS_PENDING, 'handover queda pending');
        $this->assert(($rows['handover_notes'] ?? '') === 'Patrulla aún no confirma llegada.', 'guarda nota de entrega');
        $this->handoverIds[] = (int) ($rows['id'] ?? 0);
    }

    private function handoverIdForEntry(array $pending, int $entryId): int
    {
        foreach ($pending as $row) {
            if ((int) ($row['log_entry_id'] ?? 0) === $entryId) {
                return (int) ($row['id'] ?? 0);
            }
        }

        return 0;
    }

    private function testIncomingAcceptsContinuity(): void
    {
        $handoverService = new LogHandoverService();
        $shiftService = new ShiftService();
        $logService = new LogEntryService();

        $shiftA = $this->openShift($this->operatorA);
        $entryId = $this->createIncidentInProgress($logService, $shiftA);
        $shiftService->closeWithDelivery($shiftA, $this->closingPayload());

        Session::put('auth_user_id', $this->operatorB);
        Auth::forgetCache();
        Permission::flush();

        $shiftB = $this->openShift($this->operatorB);
        $pending = $handoverService->listPendingForShift($shiftB, $this->operatorB);
        $this->assert($pending !== [], 'operador entrante recibe pendientes');

        $handoverId = $this->handoverIdForEntry($pending, $entryId);
        $this->assert($handoverId > 0, 'encuentra handover del incidente creado');
        $handoverService->accept($handoverId, $this->operatorB, $shiftB);

        $entry = (new \App\Repositories\Cctv\LogEntryRepository())->findById($entryId);
        $raw = Database::connection()->query('SELECT current_operator_id, cctv_shift_id, status FROM cctv_log_entries WHERE id = ' . (int) $entryId)->fetch();
        $currentOperator = (int) ($raw['current_operator_id'] ?? 0);
        $entryShiftId = (int) ($raw['cctv_shift_id'] ?? 0);
        $this->assert($currentOperator === (int) $this->operatorB, 'aceptar asigna operador actual');
        $this->assert($entryShiftId === $shiftB, 'aceptar mueve el registro al turno entrante');
        $this->assert(($entry['status'] ?? '') === LogEntry::STATUS_IN_PROGRESS, 'incidente sigue en desarrollo');

        $shiftEntries = (new \App\Repositories\Cctv\LogEntryRepository())->listByShift($shiftB, 50);
        $foundInBitacora = false;
        foreach ($shiftEntries as $row) {
            if ((int) ($row['id'] ?? 0) === $entryId) {
                $foundInBitacora = true;
                break;
            }
        }
        $this->assert($foundInBitacora, 'aceptado aparece en bitácora del turno actual');

        $detail = $logService->detailForView($entryId, $this->operatorB);
        $this->assert((int) ($detail['id'] ?? 0) === $entryId, 'operador entrante puede consultar detalle');
        $this->assert(cctv_can_edit_log_entry($detail), 'operador entrante puede editar procedimiento aceptado');
        $this->handoverIds[] = $handoverId;
    }

    private function testIncomingDeclineRequiresJustification(): void
    {
        $handoverService = new LogHandoverService();
        $shiftService = new ShiftService();
        $logService = new LogEntryService();

        $shiftA = $this->openShift($this->operatorA);
        $entryId = $this->createIncidentInProgress($logService, $shiftA);
        $shiftService->closeWithDelivery($shiftA, $this->closingPayload());

        Session::put('auth_user_id', $this->operatorB);
        Auth::forgetCache();
        Permission::flush();

        $shiftB = $this->openShift($this->operatorB);
        $handoverId = $this->handoverIdForEntry(
            $handoverService->listPendingForShift($shiftB, $this->operatorB),
            $entryId
        );
        $this->assert($handoverId > 0, 'encuentra handover para declinar');

        $handoverService->decline($handoverId, [
            'decision_reason' => 'finished_during_handover',
            'decision_details' => 'Carabineros llegó durante el cambio de turno y finalizó el procedimiento.',
            'approx_completion_time' => '16:05',
        ], $this->operatorB, $shiftB);

        $entry = (new \App\Repositories\Cctv\LogEntryRepository())->findById($entryId);
        $raw = Database::connection()->query('SELECT status FROM cctv_log_entries WHERE id = ' . (int) $entryId)->fetch();
        $this->assert(($raw['status'] ?? '') === LogEntry::STATUS_FINISHED, 'declinar finaliza el registro');

        $operatorBName = (string) Database::connection()->query('SELECT name FROM users WHERE id = ' . (int) $this->operatorB)->fetchColumn();
        $detail = $logService->detailForView($entryId, $this->operatorB);
        $closure = $detail['handover_closure'] ?? [];
        $this->assert(is_array($closure), 'detalle incluye resumen de traspaso');
        $this->assert(($closure['finalized_by_label'] ?? '') === $operatorBName, 'detalle indica operador que finalizó el pendiente');
        $this->handoverIds[] = $handoverId;
    }

    private function testCannotEditWithoutReview(): void
    {
        $handoverService = new LogHandoverService();
        $shiftService = new ShiftService();
        $logService = new LogEntryService();

        $shiftA = $this->openShift($this->operatorA);
        $entryId = $this->createIncidentInProgress($logService, $shiftA);
        $shiftService->closeWithDelivery($shiftA, $this->closingPayload());

        Session::put('auth_user_id', $this->operatorB);
        Auth::forgetCache();
        Permission::flush();

        $this->openShift($this->operatorB);

        try {
            $handoverService->assertEntryEditable($entryId);
            $this->fail('editar sin revisión', 'debió lanzar excepción');
        } catch (HttpException $e) {
            $this->assert($e->getStatusCode() === 422, 'impide editar heredado sin decisión');
        }
    }

    private function testDeclineRejectsMissingReason(): void
    {
        $handoverService = new LogHandoverService();
        $shiftService = new ShiftService();
        $logService = new LogEntryService();

        $shiftA = $this->openShift($this->operatorA);
        $entryId = $this->createIncidentInProgress($logService, $shiftA);
        $shiftService->closeWithDelivery($shiftA, $this->closingPayload());

        Session::put('auth_user_id', $this->operatorB);
        Auth::forgetCache();
        Permission::flush();

        $shiftB = $this->openShift($this->operatorB);
        $handoverId = $this->handoverIdForEntry(
            $handoverService->listPendingForShift($shiftB, $this->operatorB),
            $entryId
        );

        try {
            $handoverService->decline($handoverId, [
                'decision_reason' => '',
                'decision_details' => '',
            ], $this->operatorB, $shiftB);
            $this->fail('declinar sin motivo', 'debió rechazar');
        } catch (HttpException $e) {
            $this->assert($e->getStatusCode() === 422, 'rechaza decision_reason vacío');
        }
    }

    private function testWrongOperatorForbidden(): void
    {
        $handoverService = new LogHandoverService();
        $shiftService = new ShiftService();
        $logService = new LogEntryService();

        $shiftA = $this->openShift($this->operatorA);
        $entryId = $this->createIncidentInProgress($logService, $shiftA);
        $shiftService->closeWithDelivery($shiftA, $this->closingPayload());

        Session::put('auth_user_id', $this->operatorB);
        Auth::forgetCache();
        Permission::flush();

        $shiftB = $this->openShift($this->operatorB);
        $handoverId = $this->handoverIdForEntry(
            $handoverService->listPendingForShift($shiftB, $this->operatorB),
            $entryId
        );

        Session::put('auth_user_id', $this->operatorA);
        Auth::forgetCache();

        try {
            $handoverService->accept($handoverId, $this->operatorA, $shiftA);
            $this->fail('aceptar ajeno', 'debió responder 403');
        } catch (HttpException $e) {
            $this->assert($e->getStatusCode() === 403, 'operador ajeno no puede aceptar handover');
        }
    }

    private function openShift(int $operatorId): int
    {
        Session::put('auth_user_id', $operatorId);
        Auth::forgetCache();
        Permission::flush();

        $shiftService = new ShiftService();
        $existing = $shiftService->findOpenForOperator($operatorId);
        if ($existing !== null) {
            $shiftService->closeWithDelivery((int) $existing['id'], $this->closingPayload());
        }

        $id = $shiftService->openWithReception([
            'equipment' => $this->equipmentPayload(),
            'opening_notes' => 'Prueba handover',
        ], $operatorId);
        $this->shiftIds[] = $id;

        return $id;
    }

    private function createIncidentInProgress(LogEntryService $logService, int $shiftId): int
    {
        $catalog = (new \App\Services\Cctv\CatalogService())->findLogTypeBySlug('incidente');
        $incidentType = Database::connection()->query('SELECT id FROM cctv_incident_types ORDER BY id ASC LIMIT 1')->fetchColumn();
        $sectorId = Database::connection()->query('SELECT id FROM sectors ORDER BY id ASC LIMIT 1')->fetchColumn();

        $id = $logService->create([
            'shift_id' => $shiftId,
            'log_type_id' => (int) ($catalog['id'] ?? 0),
            'incident_type_id' => (int) $incidentType,
            'sector_id' => (int) $sectorId,
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'observations' => 'Incidente de prueba en desarrollo para handover.',
            'status' => LogEntry::STATUS_IN_PROGRESS,
            'coordination_notified' => '0',
            'police_arrived' => '',
        ], $this->operatorA);

        Database::connection()->prepare(
            'UPDATE cctv_log_entries SET status = :status, current_operator_id = :operator WHERE id = :id'
        )->execute([
            'status' => LogEntry::STATUS_IN_PROGRESS,
            'operator' => $this->operatorA,
            'id' => $id,
        ]);

        $this->entryIds[] = $id;

        return $id;
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function closingPayload(array $extra = []): array
    {
        return array_merge([
            'equipment' => $this->equipmentPayload(),
            'closing_notes' => 'Cierre prueba handover',
        ], $extra);
    }

    /**
     * @return array<int|string, array{status: string, observations: string}>
     */
    private function equipmentPayload(): array
    {
        $payload = [];
        foreach ((new EquipmentRepository())->listActive() as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id > 0) {
                $payload[$id] = ['status' => ShiftEquipmentCheck::STATUS_OPERATIONAL, 'observations' => ''];
            }
        }

        return $payload;
    }

    private function cleanup(): void
    {
        $pdo = Database::connection();

        if ($this->handoverIds !== []) {
            $ph = implode(',', array_fill(0, count($this->handoverIds), '?'));
            $pdo->prepare('DELETE FROM cctv_log_entry_history WHERE log_entry_id IN (SELECT log_entry_id FROM cctv_log_handovers WHERE id IN (' . $ph . '))')->execute($this->handoverIds);
            $pdo->prepare('DELETE FROM cctv_log_handovers WHERE id IN (' . $ph . ')')->execute($this->handoverIds);
        }

        if ($this->entryIds !== []) {
            $ph = implode(',', array_fill(0, count($this->entryIds), '?'));
            $pdo->prepare('DELETE FROM cctv_log_handovers WHERE log_entry_id IN (' . $ph . ')')->execute($this->entryIds);
            $pdo->prepare('DELETE FROM cctv_log_entry_history WHERE log_entry_id IN (' . $ph . ')')->execute($this->entryIds);
            $pdo->prepare('DELETE FROM cctv_log_contacts WHERE cctv_log_entry_id IN (' . $ph . ')')->execute($this->entryIds);
            $pdo->prepare('DELETE FROM cctv_log_entries WHERE id IN (' . $ph . ')')->execute($this->entryIds);
        }

        if ($this->shiftIds !== []) {
            $ph = implode(',', array_fill(0, count($this->shiftIds), '?'));
            $pdo->prepare('DELETE FROM cctv_log_handovers WHERE from_shift_id IN (' . $ph . ') OR to_shift_id IN (' . $ph . ')')->execute(array_merge($this->shiftIds, $this->shiftIds));
            $pdo->prepare('DELETE FROM cctv_shift_equipment_checks WHERE cctv_shift_id IN (' . $ph . ')')->execute($this->shiftIds);
            $pdo->prepare('DELETE FROM cctv_shifts WHERE id IN (' . $ph . ')')->execute($this->shiftIds);
        }
    }

    private function assert(bool $condition, string $label): void
    {
        if ($condition) {
            $this->passed++;
            echo "[OK] {$label}\n";
            return;
        }

        $this->failed++;
        $this->failures[] = $label;
        echo "[FAIL] {$label}\n";
    }

    private function fail(string $label, string $message): void
    {
        $this->failed++;
        $this->failures[] = "{$label}: {$message}";
        echo "[FAIL] {$label}: {$message}\n";
    }

    private function printSummary(): void
    {
        echo str_repeat('-', 48) . "\n";
        echo "Pasaron: {$this->passed} | Fallaron: {$this->failed}\n";
        foreach ($this->failures as $failure) {
            echo " - {$failure}\n";
        }
    }
}

exit((new CctvHandoverFunctionalTests())->run());
