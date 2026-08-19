<?php

declare(strict_types=1);

/**
 * Pruebas de auditoría e IDOR del módulo transversal de reuniones.
 * Ejecutar: php tests/meetings_audit_functional.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse por consola.\n");
    exit(1);
}

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/meetings';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/seguridad/public/index.php';

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Repositories\AuditRepository;
use App\Services\AuditService;
use App\Services\Meetings\MeetingService;
use App\Services\Meetings\MeetingSignatureService;
use App\Services\Meetings\MeetingSourceModule;
use App\Services\Meetings\MeetingStatus;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Permission;
use Core\Session;

final class MeetingsAuditFunctionalTests
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var list<int> */
    private array $meetingIds = [];

    /** @var list<int> */
    private array $tempUserIds = [];

    private int $adminId = 0;

    public function run(): int
    {
        $this->boot();

        try {
            $this->testCreateAudit();
            $this->testFinalizeAuditStoresHashOnly();
            $this->testViewMeetingAudit();
            $this->testCancelAuditSanitizesReason();
            $this->testScopedSearchIgnoresCreatedByFilter();
            $this->testIntruderCannotCancelOthersMeeting();
        } finally {
            $this->cleanup();
        }

        echo PHP_EOL . 'Resultado auditoría Reuniones: ' . $this->passed . '/' . ($this->passed + $this->failed) . ' pruebas OK' . PHP_EOL;

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

        if ($this->adminId < 1) {
            throw new RuntimeException('No hay superadministrador activo.');
        }

        Session::put('auth_user_id', $this->adminId);
        Auth::forgetCache();
        Permission::flush();
    }

    private function testCreateAudit(): void
    {
        $meetingId = $this->createMeeting([
            'additional_notes' => str_repeat('Nota confidencial de prueba. ', 20),
        ]);

        $payload = $this->latestAuditPayload(
            (string) $meetingId,
            AuditService::ACTION_CREATED
        );

        $this->check('Creación de reunión queda auditada', ($payload['meeting_number'] ?? '') !== '');
        $this->check('Auditoría de creación no guarda notas completas', !isset($payload['additional_notes']));
        $this->check('Auditoría de creación usa extracto de notas', isset($payload['additional_notes_excerpt']));
    }

    private function testFinalizeAuditStoresHashOnly(): void
    {
        $meetingId = $this->createMeeting();
        (new MeetingSignatureService())->finalize($meetingId);

        $payload = $this->latestAuditPayload(
            (string) $meetingId,
            AuditService::ACTION_FINALIZED
        );

        $this->check('Finalización auditada incluye hash', trim((string) ($payload['content_hash'] ?? '')) !== '');
        $this->check('Finalización no guarda temas completos', !isset($payload['topics']));
    }

    private function testViewMeetingAudit(): void
    {
        $service = new MeetingService();
        $meetingId = $this->createMeeting();
        $meeting = $service->findDetailed($meetingId);

        $service->auditView($meeting);

        $row = $this->latestAuditRow((string) $meetingId, AuditService::ACTION_VIEW_MEETING);
        $payload = json_decode((string) ($row['new_values'] ?? ''), true) ?: [];

        $this->check('Consulta de reunión queda auditada', ($row['action'] ?? '') === AuditService::ACTION_VIEW_MEETING);
        $this->check('Consulta auditada incluye número de reunión', ($payload['meeting_number'] ?? '') !== '');
    }

    private function testCancelAuditSanitizesReason(): void
    {
        $service = new MeetingService();
        $meetingId = $this->createMeeting();
        $longReason = str_repeat('Motivo extenso de anulación institucional. ', 10);

        $service->cancel($meetingId, $longReason);

        $payload = $this->latestAuditPayload(
            (string) $meetingId,
            AuditService::ACTION_CANCELLED
        );

        $this->check('Anulación auditada usa extracto del motivo', isset($payload['cancellation_reason_excerpt']));
        $this->check('Anulación no guarda motivo completo', !isset($payload['cancellation_reason']));
        $this->check('Extracto respeta límite', mb_strlen((string) ($payload['cancellation_reason_excerpt'] ?? '')) <= 120);
    }

    private function testScopedSearchIgnoresCreatedByFilter(): void
    {
        $service = new MeetingService();
        $meetingId = $this->createMeeting();
        $intruderId = $this->createTempUser('senda');

        Session::put('auth_user_id', $intruderId);
        Auth::forgetCache();
        Permission::flush();

        $result = $service->search([
            'created_by' => $this->adminId,
            'source_module' => MeetingSourceModule::SENDA,
        ], 1, 50);

        $ids = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $result['data']);
        $this->check('Filtro created_by no expande alcance sin view_all', !in_array($meetingId, $ids, true));
    }

    private function testIntruderCannotCancelOthersMeeting(): void
    {
        $service = new MeetingService();
        $meetingId = $this->createMeeting();
        $intruderId = $this->createTempUser('senda');

        Session::put('auth_user_id', $intruderId);
        Auth::forgetCache();
        Permission::flush();

        $denied = false;
        try {
            $service->cancel($meetingId, 'Intento de anulación no autorizada sobre reunión ajena.');
        } catch (HttpException $e) {
            $denied = in_array($e->getStatusCode(), [403, 422], true);
        }

        $this->check('Usuario sin permiso de anulación no puede cancelar reunión ajena', $denied);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function createMeeting(array $extra = []): int
    {
        $pdo = Database::connection();
        $otherUserId = (int) $pdo->query(
            'SELECT id FROM users WHERE is_active = 1 AND id <> ' . $this->adminId . ' LIMIT 1'
        )->fetchColumn();

        $service = new MeetingService();
        $payload = array_merge([
            'meeting_date' => date('Y-m-d'),
            'meeting_time' => '10:00',
            'meeting_place' => 'Sala de auditoría funcional',
            'include_creator' => '1',
            'participants' => [[
                'participant_type' => 'internal',
                'user_id' => $otherUserId,
                'signature_required' => '1',
            ]],
            'topics' => [['description' => 'Tema confidencial de prueba para auditoría.']],
            'agreements' => [['description' => 'Acuerdo funcional de prueba.']],
            'next_meeting_required' => 'no',
        ], $extra);

        $id = $service->createDraft(MeetingSourceModule::SENDA, $payload);
        $this->meetingIds[] = $id;

        return $id;
    }

    private function createTempUser(string $roleSlug): int
    {
        $pdo = Database::connection();
        $email = 'meetings.audit.' . bin2hex(random_bytes(4)) . '@municipalidad.local';
        $pdo->prepare(
            'INSERT INTO users (name, email, password, is_active, must_change_password)
             VALUES (:name, :email, :password, 1, 0)'
        )->execute([
            'name' => 'Usuario temporal auditoría reuniones',
            'email' => $email,
            'password' => password_hash('TestMeet123!', PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();
        $this->tempUserIds[] = $userId;

        $roleId = (int) $pdo->query("SELECT id FROM roles WHERE slug = '" . $roleSlug . "' LIMIT 1")->fetchColumn();
        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:u, :r)')
            ->execute(['u' => $userId, 'r' => $roleId]);

        return $userId;
    }

    /**
     * @return array<string, mixed>
     */
    private function latestAuditPayload(string $resourceId, string $action): array
    {
        $row = $this->latestAuditRow($resourceId, $action);

        return json_decode((string) ($row['new_values'] ?? ''), true) ?: [];
    }

    /**
     * @return array<string, mixed>
     */
    private function latestAuditRow(string $resourceId, string $action): array
    {
        $rows = (new AuditRepository())->forResource(
            AuditService::MODULE_MEETINGS,
            AuditService::RESOURCE_MEETING,
            $resourceId,
            30
        );

        foreach ($rows as $row) {
            if (($row['action'] ?? '') === $action) {
                return $row;
            }
        }

        return [];
    }

    private function check(string $label, bool $ok): void
    {
        if ($ok) {
            $this->passed++;
            echo '  PASS  ' . $label . PHP_EOL;

            return;
        }

        $this->failed++;
        echo '  FAIL  ' . $label . PHP_EOL;
    }

    private function cleanup(): void
    {
        Session::put('auth_user_id', $this->adminId);
        Auth::forgetCache();
        Permission::flush();

        $pdo = Database::connection();
        foreach ($this->meetingIds as $id) {
            $pdo->prepare('DELETE FROM notifications WHERE related_type = :type AND related_id = :id')
                ->execute(['type' => 'meeting', 'id' => $id]);
            $pdo->prepare('DELETE FROM meeting_signatures WHERE meeting_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM meeting_participants WHERE meeting_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM meeting_topics WHERE meeting_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM meeting_agreements WHERE meeting_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM meetings WHERE id = :id')->execute(['id' => $id]);
        }

        foreach ($this->tempUserIds as $userId) {
            $pdo->prepare('DELETE FROM user_roles WHERE user_id = :id')->execute(['id' => $userId]);
            $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
        }
    }
}

exit((new MeetingsAuditFunctionalTests())->run());
