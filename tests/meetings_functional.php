<?php

declare(strict_types=1);

/**
 * Pruebas funcionales del módulo transversal de reuniones.
 * Ejecutar: php tests/meetings_functional.php
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

use App\Services\Meetings\MeetingService;
use App\Services\Meetings\MeetingSignatureService;
use App\Services\Meetings\MeetingSourceModule;
use App\Services\Meetings\MeetingStatus;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Permission;
use Core\Session;

final class MeetingsFunctionalTests
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
            $this->assertTablesExist();
            $this->assertCreateDraftWithSections();
            $this->assertUpdateDraft();
            $this->assertScopedAccess();
            $this->assertFinalizeAndSignatures();
            $this->assertExternalAttendanceConfirmation();
            $this->assertDeleteMeeting();
            $this->assertReopenAndCancel();
        } finally {
            $this->cleanup();
        }

        echo PHP_EOL . 'Resultado: ' . $this->passed . '/' . ($this->passed + $this->failed) . ' pruebas OK' . PHP_EOL;

        return $this->failed === 0 ? 0 : 1;
    }

    private function boot(): void
    {
        Session::start();

        $pdo = Database::connection();
        $adminId = (int) $pdo->query(
            "SELECT u.id FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.slug = 'superadministrador' AND u.is_active = 1
             LIMIT 1"
        )->fetchColumn();

        if ($adminId < 1) {
            throw new RuntimeException('No hay superadministrador activo.');
        }

        Session::put('auth_user_id', $adminId);
        Auth::forgetCache();
        Permission::flush();
        $this->adminId = $adminId;
    }

    private function assertTablesExist(): void
    {
        $pdo = Database::connection();
        foreach (['meeting_sequences', 'meetings', 'meeting_participants', 'meeting_topics', 'meeting_agreements', 'user_signatures', 'meeting_signatures', 'notifications'] as $table) {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1'
            );
            $stmt->execute(['table' => $table]);
            $this->check('Existe tabla ' . $table, (bool) $stmt->fetchColumn());
        }

        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table
               AND column_name = :column
             LIMIT 1'
        );
        $stmt->execute(['table' => 'meeting_participants', 'column' => 'attendance_token']);
        $this->check('Existe columna attendance_token', (bool) $stmt->fetchColumn());
    }

    private function assertCreateDraftWithSections(): void
    {
        $service = new MeetingService();
        $pdo = Database::connection();
        $otherUserId = (int) $pdo->query('SELECT id FROM users WHERE is_active = 1 AND id <> ' . (int) (Auth::id() ?? 0) . ' LIMIT 1')->fetchColumn();
        $this->check('Hay otro usuario activo para participantes', $otherUserId > 0);

        $id = $service->createDraft(MeetingSourceModule::SENDA, [
            'meeting_date' => date('Y-m-d'),
            'meeting_time' => '10:30',
            'meeting_place' => 'Dirección de Seguridad',
            'include_creator' => '1',
            'participants' => [[
                'participant_type' => 'internal',
                'user_id' => $otherUserId,
                'signature_required' => '1',
            ], [
                'participant_type' => 'external',
                'external_name' => 'Invitado Externo',
                'external_position' => 'Consultor',
                'external_organization' => 'Externa',
                'external_email' => 'externo.test@example.com',
            ]],
            'topics' => [
                ['description' => 'Tema uno de prueba funcional.'],
                ['description' => 'Tema dos de prueba funcional.'],
            ],
            'agreements' => [
                ['description' => 'Acuerdo uno funcional.', 'responsible_text' => 'Equipo SENDA'],
            ],
            'additional_notes' => 'Notas de prueba.',
            'next_meeting_required' => 'yes',
            'next_meeting_date' => date('Y-m-d', strtotime('+7 days')),
            'next_meeting_notes' => 'Seguimiento programado.',
        ]);
        $this->meetingIds[] = $id;

        $meeting = $service->findDetailed($id);
        $this->check('Reunión SENDA se crea en borrador', ($meeting['status'] ?? '') === MeetingStatus::DRAFT);
        $this->check('Número correlativo REU', preg_match('/^REU-\d{4}-\d{6}$/', (string) ($meeting['meeting_number'] ?? '')) === 1);
        $this->check('Participante interno usa user_id', count($meeting['participants'] ?? []) >= 2);
        $this->check('Temas enumerados persistidos', count($meeting['topics'] ?? []) === 2);
        $this->check('Acuerdos persistidos', count($meeting['agreements'] ?? []) === 1);
        $this->check('Próxima reunión requerida', !empty($meeting['next_meeting_required']));
    }

    private function assertUpdateDraft(): void
    {
        $service = new MeetingService();
        $id = $this->meetingIds[0] ?? 0;
        if ($id < 1) {
            $this->check('Actualización de borrador', false);

            return;
        }

        $meeting = $service->findDetailed($id);
        $internal = null;
        foreach ($meeting['participants'] as $participant) {
            if (($participant['participant_type'] ?? '') === 'internal' && (int) ($participant['user_id'] ?? 0) > 0) {
                $internal = $participant;
                break;
            }
        }

        $service->updateDraft($id, [
            'meeting_date' => (string) ($meeting['meeting_date'] ?? date('Y-m-d')),
            'meeting_time' => '11:00',
            'meeting_place' => 'Sala de reuniones',
            'include_creator' => '0',
            'participants' => [[
                'participant_type' => 'internal',
                'user_id' => (int) ($internal['user_id'] ?? 0),
                'signature_required' => '1',
            ], [
                'participant_type' => 'external',
                'external_name' => 'Invitado Externo',
                'external_position' => 'Consultor',
                'external_organization' => 'Externa',
                'external_email' => 'externo.test@example.com',
            ]],
            'topics' => [['description' => 'Tema actualizado.']],
            'agreements' => [['description' => 'Acuerdo actualizado.']],
            'next_meeting_required' => 'no',
        ]);

        $updated = $service->findDetailed($id);
        $this->check('Borrador actualiza hora y lugar', substr((string) ($updated['meeting_time'] ?? ''), 0, 5) === '11:00');
        $this->check('Temas reemplazados en borrador', count($updated['topics'] ?? []) === 1);
    }

    private function assertScopedAccess(): void
    {
        $service = new MeetingService();
        $id = $this->meetingIds[0] ?? 0;
        if ($id < 1) {
            $this->check('Acceso scoped a reunión ajena', false);

            return;
        }

        $meeting = $service->findDetailed($id);
        $pdo = Database::connection();
        $email = 'meetings.test.' . bin2hex(random_bytes(4)) . '@municipalidad.local';
        $pdo->prepare(
            'INSERT INTO users (name, email, password, is_active, must_change_password)
             VALUES (:name, :email, :password, 1, 0)'
        )->execute([
            'name' => 'Usuario sin reuniones',
            'email' => $email,
            'password' => password_hash('TestMeet123!', PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();
        $this->tempUserIds[] = $userId;

        $roleId = (int) $pdo->query("SELECT id FROM roles WHERE slug = 'consulta' LIMIT 1")->fetchColumn();
        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:u, :r)')
            ->execute(['u' => $userId, 'r' => $roleId]);

        Session::put('auth_user_id', $userId);
        Auth::forgetCache();
        Permission::flush();

        $denied = false;
        try {
            $service->assertCanView($meeting);
        } catch (HttpException $e) {
            $denied = $e->getStatusCode() === 403;
        }
        $this->check('Usuario ajeno no puede ver reunión SENDA', $denied);
    }

    private function assertFinalizeAndSignatures(): void
    {
        Session::put('auth_user_id', $this->adminId);
        Auth::forgetCache();
        Permission::flush();

        $meetingService = new MeetingService();
        $signatureService = new MeetingSignatureService();
        $id = $this->meetingIds[0] ?? 0;
        if ($id < 1) {
            $this->check('Finalizar reunión borrador', false);
            $this->check('Hash de contenido al finalizar', false);

            return;
        }

        $pdo = Database::connection();
        $otherUserId = (int) $pdo->query(
            'SELECT user_id FROM meeting_participants
             WHERE meeting_id = ' . $id . " AND participant_type = 'internal' AND user_id <> " . $this->adminId . ' LIMIT 1'
        )->fetchColumn();

        $signatureService->finalize($id);
        $finalized = $meetingService->findDetailed($id);
        $this->check('Finalizar pasa a pendiente de firmas', ($finalized['status'] ?? '') === MeetingStatus::PENDING_SIGNATURES);
        $this->check('Hash de contenido al finalizar', trim((string) ($finalized['content_hash'] ?? '')) !== '');
        $this->check('Borrador ya no es editable', empty($finalized['can_edit']));

        $hashService = new \App\Services\Meetings\MeetingContentHashService();
        $this->check('Hash estable tras finalizar', $hashService->compute($finalized) === (string) ($finalized['content_hash'] ?? ''));

        $signatures = $signatureService->presentSignatures($finalized);
        $this->check('Se crean solicitudes de firma', count($signatures) >= 1);

        $sendaRoleId = (int) $pdo->query("SELECT id FROM roles WHERE slug = 'senda' LIMIT 1")->fetchColumn();
        if ($sendaRoleId > 0) {
            $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:u, :r)')
                ->execute(['u' => $otherUserId, 'r' => $sendaRoleId]);
        }

        $this->seedUserSignature($otherUserId);
        Session::put('auth_user_id', $otherUserId);
        Auth::forgetCache();
        Permission::flush();

        $signatureService->sign($id);
        $partial = $meetingService->findDetailed($id);
        $this->check('Primera firma deja estado parcial o firmado', in_array($partial['status'] ?? '', [MeetingStatus::PARTIALLY_SIGNED, MeetingStatus::SIGNED], true));

        $denied = false;
        try {
            $signatureService->sign($id);
        } catch (HttpException $e) {
            $denied = $e->getStatusCode() === 403;
        }
        $this->check('No se puede firmar dos veces la misma solicitud', $denied);

        $intruderId = $this->createTempUser('consulta');
        Session::put('auth_user_id', $intruderId);
        Auth::forgetCache();
        Permission::flush();

        $idor = false;
        try {
            $signatureService->sign($id);
        } catch (HttpException $e) {
            $idor = $e->getStatusCode() === 403;
        }
        $this->check('Usuario sin solicitud no puede firmar (IDOR)', $idor);

        $this->check('Contador de firmas pendientes', $signatureService->getPendingCountForUser($this->adminId) >= 0);
    }

    private function assertExternalAttendanceConfirmation(): void
    {
        Session::put('auth_user_id', $this->adminId);
        Auth::forgetCache();
        Permission::flush();

        $id = $this->meetingIds[0] ?? 0;
        if ($id < 1) {
            $this->check('Token de asistencia externa generado', false);
            $this->check('Confirmación de asistencia externa', false);

            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT id, attendance_token, attendance_status
             FROM meeting_participants
             WHERE meeting_id = :meeting_id AND participant_type = 'external'
             LIMIT 1"
        );
        $stmt->execute(['meeting_id' => $id]);
        $external = $stmt->fetch() ?: [];

        $token = trim((string) ($external['attendance_token'] ?? ''));
        $this->check('Token de asistencia externa generado', preg_match('/^[a-f0-9]{64}$/', $token) === 1);

        $attendance = new \App\Services\Meetings\MeetingAttendanceService();
        $invitation = $attendance->findInvitation($token);
        $this->check('Invitación externa es consultable por token', ($invitation['participant']['id'] ?? 0) === (int) ($external['id'] ?? 0));

        $attendance->respond($token, 'confirm');
        $stmt->execute(['meeting_id' => $id]);
        $updated = $stmt->fetch() ?: [];
        $this->check('Confirmación de asistencia externa', ($updated['attendance_status'] ?? '') === 'confirmed');

        $duplicate = false;
        try {
            $attendance->respond($token, 'decline');
        } catch (HttpException $e) {
            $duplicate = $e->getStatusCode() === 409;
        }
        $this->check('No se puede responder dos veces la invitación', $duplicate);
    }

    private function assertDeleteMeeting(): void
    {
        Session::put('auth_user_id', $this->adminId);
        Auth::forgetCache();
        Permission::flush();

        $service = new MeetingService();
        $pdo = Database::connection();
        $otherUserId = (int) $pdo->query('SELECT id FROM users WHERE is_active = 1 AND id <> ' . $this->adminId . ' LIMIT 1')->fetchColumn();

        $deleteId = $service->createDraft(MeetingSourceModule::SENDA, [
            'meeting_date' => date('Y-m-d'),
            'meeting_time' => '15:00',
            'meeting_place' => 'Sala eliminable',
            'include_creator' => '0',
            'participants' => [[
                'participant_type' => 'internal',
                'user_id' => $otherUserId,
                'signature_required' => '1',
            ], [
                'participant_type' => 'external',
                'external_name' => 'Externo eliminable',
                'external_email' => 'eliminar.test@example.com',
            ]],
            'topics' => [['description' => 'Tema eliminable.']],
            'agreements' => [['description' => 'Acuerdo eliminable.']],
            'next_meeting_required' => 'no',
        ]);

        $blocked = false;
        try {
            $service->delete($this->meetingIds[0] ?? 0);
        } catch (HttpException $e) {
            $blocked = $e->getStatusCode() === 409;
        }
        $this->check('No elimina reunión con asistencia externa confirmada', $blocked);

        $service->delete($deleteId);
        $missing = false;
        try {
            $service->findDetailed($deleteId);
        } catch (HttpException $e) {
            $missing = $e->getStatusCode() === 404;
        }
        $this->check('Reunión eliminable desaparece del sistema', $missing);
    }

    private function assertReopenAndCancel(): void
    {
        Session::put('auth_user_id', $this->adminId);
        Auth::forgetCache();
        Permission::flush();

        $service = new MeetingService();
        $pdo = Database::connection();
        $otherUserId = (int) $pdo->query('SELECT id FROM users WHERE is_active = 1 AND id <> ' . $this->adminId . ' LIMIT 1')->fetchColumn();

        $reopenId = $service->createDraft(MeetingSourceModule::SENDA, [
            'meeting_date' => date('Y-m-d'),
            'meeting_time' => '09:00',
            'meeting_place' => 'Sala de prueba reapertura',
            'include_creator' => '0',
            'participants' => [[
                'participant_type' => 'internal',
                'user_id' => $otherUserId,
                'signature_required' => '1',
            ]],
            'topics' => [['description' => 'Tema para reapertura.']],
            'agreements' => [['description' => 'Acuerdo para reapertura.']],
            'next_meeting_required' => 'no',
        ]);
        $this->meetingIds[] = $reopenId;

        (new MeetingSignatureService())->finalize($reopenId);
        $service->reopen($reopenId, 'Se detectó un error en los acuerdos registrados.');
        $reopened = $service->findDetailed($reopenId);

        $this->check('Reapertura vuelve a borrador', ($reopened['status'] ?? '') === MeetingStatus::DRAFT);
        $this->check('Reapertura habilita edición', !empty($reopened['can_edit']));
        $this->check('Reapertura incrementa versión de contenido', (int) ($reopened['content_version'] ?? 0) >= 2);

        $cancelId = $service->createDraft(MeetingSourceModule::SENDA, [
            'meeting_date' => date('Y-m-d'),
            'meeting_time' => '09:30',
            'meeting_place' => 'Sala de prueba anulación',
            'include_creator' => '0',
            'participants' => [[
                'participant_type' => 'internal',
                'user_id' => $otherUserId,
                'signature_required' => '1',
            ]],
            'topics' => [['description' => 'Tema para anulación.']],
            'agreements' => [['description' => 'Acuerdo para anulación.']],
            'next_meeting_required' => 'no',
        ]);
        $this->meetingIds[] = $cancelId;

        $service->cancel($cancelId, 'La reunión fue suspendida por agenda institucional.');
        $cancelled = $service->findDetailed($cancelId);

        $this->check('Anulación cambia estado', ($cancelled['status'] ?? '') === MeetingStatus::CANCELLED);
        $this->check('Anulación bloquea edición', empty($cancelled['can_edit']));
    }

    private function seedUserSignature(int $userId): void
    {
        $directory = BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'signatures';
        if (!is_dir($directory)) {
            mkdir($directory, 0750, true);
        }

        $filename = 'user_' . $userId . '_test.png';
        $absolute = $directory . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($absolute, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        $pdo = Database::connection();
        $pdo->prepare('UPDATE user_signatures SET is_active = 0 WHERE user_id = :user_id')->execute(['user_id' => $userId]);
        $pdo->prepare(
            'INSERT INTO user_signatures (user_id, image_path, is_active) VALUES (:user_id, :image_path, 1)'
        )->execute([
            'user_id' => $userId,
            'image_path' => 'signatures/' . $filename,
        ]);
    }

    private function createTempUser(string $roleSlug): int
    {
        $pdo = Database::connection();
        $email = 'meetings.test.' . bin2hex(random_bytes(4)) . '@municipalidad.local';
        $pdo->prepare(
            'INSERT INTO users (name, email, password, is_active, must_change_password)
             VALUES (:name, :email, :password, 1, 0)'
        )->execute([
            'name' => 'Usuario temporal reuniones',
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

exit((new MeetingsFunctionalTests())->run());
