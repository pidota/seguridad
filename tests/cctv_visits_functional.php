<?php

declare(strict_types=1);

/**
 * Pruebas funcionales de visitas y solicitudes CCTV.
 * Ejecutar: php tests/cctv_visits_functional.php
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

use App\Repositories\Cctv\EquipmentRepository;
use App\Models\Cctv\ShiftEquipmentCheck;
use App\Services\Cctv\OfficeVisitService;
use App\Services\Cctv\RecordingRequestService;
use App\Services\Cctv\RecordingRequestStatusCatalog;
use App\Services\Cctv\ShiftService;
use App\Services\Cctv\VisitorTypeCatalog;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Permission;
use Core\Session;

final class CctvVisitsFunctionalTests
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var list<string> */
    private array $failures = [];

    /** @var list<int> */
    private array $visitIds = [];

    /** @var list<int> */
    private array $requestIds = [];

    /** @var list<int> */
    private array $shiftIds = [];

    private int $adminId = 0;

    public function run(): int
    {
        $this->boot();

        try {
            $this->testGeneralVisit();
            $this->testRecordingWithoutComplaint();
            $this->testRecordingWithComplaint();
            $this->testManipulatedDeliveryRejected();
            $this->testApproveRequiresPermission();
            $this->testDeliveryFlow();
        } catch (\Throwable $e) {
            $this->fail('ejecución', $e->getMessage());
        }

        try {
            $this->cleanup();
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Aviso cleanup: ' . $e->getMessage() . PHP_EOL);
        }

        $this->printSummary();

        return $this->failed === 0 ? 0 : 1;
    }

    private function boot(): void
    {
        Session::start();

        $pdo = Database::connection();
        $this->adminId = (int) $pdo->query(
            "SELECT u.id FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.slug = 'superadministrador' AND u.is_active = 1
             LIMIT 1"
        )->fetchColumn();

        Session::put('auth_user_id', $this->adminId);
        Auth::forgetCache();
        Permission::flush();

        $pdo->exec('DELETE FROM cctv_shift_equipment_checks WHERE cctv_shift_id IN (SELECT id FROM cctv_shifts WHERE operator_id = ' . $this->adminId . " AND status = 'open')");
        $pdo->exec("UPDATE cctv_shifts SET status = 'closed', ended_at = NOW() WHERE operator_id = {$this->adminId} AND status = 'open'");
    }

    private function openShift(): int
    {
        $service = new ShiftService();
        $open = $service->findOpenForOperator($this->adminId);
        if ($open !== null) {
            return (int) $open['id'];
        }

        $equipment = [];
        foreach ((new EquipmentRepository())->listActive() as $item) {
            $equipment[(int) $item['id']] = [
                'status' => ShiftEquipmentCheck::STATUS_OPERATIONAL,
                'observations' => '',
            ];
        }

        $shiftId = $service->openWithReception([
            'opening_notes' => 'Turno prueba visitas',
            'equipment' => $equipment,
        ], $this->adminId);

        $this->shiftIds[] = $shiftId;

        return $shiftId;
    }

    private function testGeneralVisit(): void
    {
        $shiftId = $this->openShift();
        $service = new OfficeVisitService();
        $visitId = $service->createGeneralVisit([
            'visit_date' => date('Y-m-d'),
            'arrival_time' => '10:15',
            'requester_name' => 'Visitante Prueba',
            'reason' => 'Consulta operativa en oficina CCTV.',
        ], $shiftId, $this->adminId);

        $this->visitIds[] = $visitId;
        $this->assertTrue($visitId > 0, 'Visita general se registra');
    }

    private function testRecordingWithoutComplaint(): void
    {
        $shiftId = $this->openShift();
        $service = new RecordingRequestService();
        $result = $service->createWithVisit([
            'visit_date' => date('Y-m-d'),
            'arrival_time' => '11:00',
            'requester_name' => 'Solicitante Sin Denuncia',
            'requester_rut' => '12.345.678-5',
            'reason' => 'Solicita revisión de grabación.',
            'incident_date' => date('Y-m-d'),
            'time_from' => '18:20',
            'time_to' => '18:45',
            'incident_description' => 'Accidente vehicular.',
            'has_complaint' => '0',
        ], $shiftId, $this->adminId);

        $this->visitIds[] = (int) $result['visit_id'];
        $this->requestIds[] = (int) $result['request_id'];
        $this->assertSame(RecordingRequestStatusCatalog::PENDING_COMPLAINT, $result['status'], 'Sin denuncia queda pendiente de denuncia');

        try {
            $service->deliver((int) $result['request_id'], [
                'receiver_name' => 'Solicitante Sin Denuncia',
                'receiver_rut' => '12.345.678-5',
                'receiver_relationship' => 'solicitante',
                'delivery_medium' => 'pendrive',
            ], $this->adminId);
            $this->fail('entrega sin denuncia', 'Debió rechazar la entrega');
        } catch (HttpException $e) {
            $this->assertTrue($e->getStatusCode() === 422 || $e->getStatusCode() === 403, 'Entrega sin denuncia es rechazada');
        }
    }

    private function testRecordingWithComplaint(): void
    {
        $shiftId = $this->openShift();
        $service = new RecordingRequestService();
        $result = $service->createWithVisit([
            'visit_date' => date('Y-m-d'),
            'arrival_time' => '12:00',
            'requester_name' => 'Solicitante Con Denuncia',
            'requester_rut' => '12.345.678-5',
            'reason' => 'Solicita entrega de grabación.',
            'incident_date' => date('Y-m-d', strtotime('-1 day')),
            'time_from' => '09:00',
            'time_to' => '09:30',
            'incident_description' => 'Colisión en intersección.',
            'has_complaint' => '1',
            'complaint_institution' => 'carabineros',
            'complaint_number' => 'PARTE-12345',
            'complaint_date' => date('Y-m-d', strtotime('-1 day')),
        ], $shiftId, $this->adminId);

        $this->visitIds[] = (int) $result['visit_id'];
        $this->requestIds[] = (int) $result['request_id'];
        $this->assertSame(RecordingRequestStatusCatalog::INCOMPLETE_DOCUMENTATION, $result['status'], 'Con denuncia queda en documentación pendiente de verificación');
    }

    private function testManipulatedDeliveryRejected(): void
    {
        $shiftId = $this->openShift();
        $service = new RecordingRequestService();
        $result = $service->createWithVisit([
            'visit_date' => date('Y-m-d'),
            'arrival_time' => '13:00',
            'requester_name' => 'Manipulación Test',
            'requester_rut' => '12.345.678-5',
            'reason' => 'Prueba manipulación.',
            'incident_date' => date('Y-m-d'),
            'time_from' => '14:00',
            'time_to' => '14:30',
            'incident_description' => 'Hecho de prueba.',
            'has_complaint' => '0',
        ], $shiftId, $this->adminId);

        $this->requestIds[] = (int) $result['request_id'];

        try {
            $service->transitionStatus((int) $result['request_id'], RecordingRequestStatusCatalog::DELIVERED, $this->adminId);
            $this->fail('status delivered manipulado', 'Debió rechazar cambio directo a entregada');
        } catch (HttpException $e) {
            $this->assertTrue(true, 'Cambio directo a entregada bloqueado');
        }
    }

    private function testApproveRequiresPermission(): void
    {
        $pdo = Database::connection();
        $operatorId = (int) $pdo->query(
            "SELECT u.id FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.slug = 'operador_camaras' AND u.is_active = 1
             LIMIT 1"
        )->fetchColumn();

        if ($operatorId < 1) {
            $this->assertTrue(true, 'Sin operador de prueba, se omite permiso approve');

            return;
        }

        $shiftId = $this->openShift();
        $service = new RecordingRequestService();
        $result = $service->createWithVisit([
            'visit_date' => date('Y-m-d'),
            'arrival_time' => '15:00',
            'requester_name' => 'Permiso Approve',
            'requester_rut' => '12.345.678-5',
            'reason' => 'Prueba permisos.',
            'incident_date' => date('Y-m-d'),
            'time_from' => '16:00',
            'time_to' => '16:30',
            'incident_description' => 'Hecho.',
            'has_complaint' => '1',
            'complaint_institution' => 'fiscalia',
            'complaint_number' => 'CAUSA-999',
            'complaint_date' => date('Y-m-d'),
        ], $shiftId, $this->adminId);

        $requestId = (int) $result['request_id'];
        $service->verifyComplaint($requestId, $this->adminId);
        $service->transitionStatus($requestId, RecordingRequestStatusCatalog::UNDER_REVIEW, $this->adminId);
        $service->transitionStatus($requestId, RecordingRequestStatusCatalog::RECORDING_FOUND, $this->adminId);

        Session::put('auth_user_id', $operatorId);
        Auth::forgetCache();
        Permission::flush();

        try {
            $service->transitionStatus($requestId, RecordingRequestStatusCatalog::APPROVED, $operatorId);
            $this->fail('approve sin permiso', 'Operador no debería aprobar entrega');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode(), 'Aprobación sin permiso rechazada');
        }

        Session::put('auth_user_id', $this->adminId);
        Auth::forgetCache();
        Permission::flush();
    }

    private function testDeliveryFlow(): void
    {
        $shiftId = $this->openShift();
        $service = new RecordingRequestService();
        $result = $service->createWithVisit([
            'visit_date' => date('Y-m-d'),
            'arrival_time' => '16:30',
            'requester_name' => 'Entrega Final',
            'requester_rut' => '12.345.678-5',
            'reason' => 'Flujo de entrega.',
            'incident_date' => date('Y-m-d'),
            'time_from' => '17:00',
            'time_to' => '17:20',
            'incident_description' => 'Hecho final.',
            'has_complaint' => '1',
            'complaint_institution' => 'carabineros',
            'complaint_number' => 'PARTE-777',
            'complaint_date' => date('Y-m-d'),
        ], $shiftId, $this->adminId);

        $requestId = (int) $result['request_id'];
        $service->verifyComplaint($requestId, $this->adminId);
        $service->transitionStatus($requestId, RecordingRequestStatusCatalog::UNDER_REVIEW, $this->adminId);
        $service->transitionStatus($requestId, RecordingRequestStatusCatalog::RECORDING_FOUND, $this->adminId);
        $service->transitionStatus($requestId, RecordingRequestStatusCatalog::APPROVED, $this->adminId);
        $service->deliver($requestId, [
            'receiver_name' => 'Entrega Final',
            'receiver_rut' => '12.345.678-5',
            'receiver_relationship' => 'solicitante',
            'delivery_medium' => 'pendrive',
            'delivery_notes' => 'Entrega verificada.',
        ], $this->adminId);

        $detail = $service->detail($requestId);
        $this->assertSame(RecordingRequestStatusCatalog::DELIVERED, $detail['status'], 'Entrega registrada correctamente');
        $this->assertNotNull($detail['delivery'], 'Detalle incluye datos de entrega');
    }

    private function cleanup(): void
    {
        $pdo = Database::connection();
        foreach ($this->requestIds as $id) {
            $pdo->prepare('DELETE FROM cctv_recording_request_cameras WHERE recording_request_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM cctv_recording_deliveries WHERE recording_request_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM cctv_recording_request_history WHERE recording_request_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM cctv_recording_requests WHERE id = :id')->execute(['id' => $id]);
        }
        foreach ($this->visitIds as $id) {
            $pdo->prepare('DELETE FROM cctv_office_visits WHERE id = :id')->execute(['id' => $id]);
        }
        foreach ($this->shiftIds as $id) {
            $pdo->prepare('DELETE FROM cctv_shift_equipment_checks WHERE cctv_shift_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM cctv_shifts WHERE id = :id')->execute(['id' => $id]);
        }
    }

    private function assertTrue(bool $condition, string $label): void
    {
        if ($condition) {
            ++$this->passed;
        } else {
            $this->fail($label, 'Condición falsa');
        }
    }

    private function assertSame(mixed $expected, mixed $actual, string $label): void
    {
        if ($expected === $actual) {
            ++$this->passed;
        } else {
            $this->fail($label, 'Esperado ' . var_export($expected, true) . ' obtuvo ' . var_export($actual, true));
        }
    }

    private function assertNotNull(mixed $value, string $label): void
    {
        if ($value !== null) {
            ++$this->passed;
        } else {
            $this->fail($label, 'Valor nulo');
        }
    }

    private function fail(string $label, string $message): void
    {
        ++$this->failed;
        $this->failures[] = $label . ': ' . $message;
    }

    private function printSummary(): void
    {
        echo "CCTV Visitas - passed: {$this->passed}, failed: {$this->failed}" . PHP_EOL;
        foreach ($this->failures as $failure) {
            echo ' - ' . $failure . PHP_EOL;
        }
    }
}

exit((new CctvVisitsFunctionalTests())->run());
