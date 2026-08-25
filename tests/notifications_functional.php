<?php

declare(strict_types=1);

/**
 * Pruebas del centro de notificaciones.
 * Ejecutar: php tests/notifications_functional.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse por consola.\n");
    exit(1);
}

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/index.php';

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Repositories\NotificationRepository;
use App\Repositories\PermissionRepository;
use App\Services\NotificationCatalog;
use App\Services\NotificationService;
use Core\Auth;
use Core\Database;
use Core\Permission;
use Core\Session;

final class NotificationsFunctionalTests
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var list<string> */
    private array $failures = [];

    private int $adminId = 0;

    /** @var list<int> */
    private array $createdIds = [];

    public function run(): int
    {
        $this->boot();

        try {
            $this->testCreateAndUnreadCount();
            $this->testPresentMetadata();
            $this->testMarkRead();
            $this->testMarkAllRead();
            $this->testDedupe();
            $this->testPermissionLookup();
            $this->testHelpers();
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

        $this->adminId = (int) Database::connection()->query(
            "SELECT u.id FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.slug = 'superadministrador' LIMIT 1"
        )->fetchColumn();

        Session::put('auth_user_id', $this->adminId);
        Auth::forgetCache();
        Permission::flush();
    }

    private function testCreateAndUnreadCount(): void
    {
        $repo = new NotificationRepository();
        $id = $repo->create([
            'user_id' => $this->adminId,
            'type' => NotificationCatalog::TYPE_MEETING_SIGNATURE_PENDING,
            'title' => 'Prueba firma',
            'message' => 'Mensaje de prueba para firma pendiente.',
            'related_type' => 'meeting',
            'related_id' => 999001,
        ]);
        $this->createdIds[] = $id;

        $service = new NotificationService();
        $count = $service->unreadCount($this->adminId);

        $this->assert($id > 0, 'create devuelve id válido');
        $this->assert($count >= 1, 'unreadCount incluye la notificación creada');
    }

    private function testPresentMetadata(): void
    {
        $service = new NotificationService();
        $rows = $service->recentForNavbar(5, $this->adminId);
        $this->assert($rows !== [], 'recentForNavbar devuelve filas');

        $first = $rows[0];
        $this->assert(isset($first['icon'], $first['tone'], $first['module_label']), 'present agrega metadatos');
        $this->assert(isset($first['time_ago'], $first['excerpt']), 'present agrega tiempo y extracto');
    }

    private function testMarkRead(): void
    {
        $repo = new NotificationRepository();
        $id = $repo->create([
            'user_id' => $this->adminId,
            'type' => NotificationCatalog::TYPE_SENDA_REFERRAL_CREATED,
            'title' => 'Prueba referencia',
            'message' => 'Ficha de referencia de prueba.',
            'related_type' => 'senda_referral',
            'related_id' => 999002,
        ]);
        $this->createdIds[] = $id;

        $service = new NotificationService();
        $before = $service->unreadCount($this->adminId);
        $ok = $service->markRead($id, $this->adminId);
        $after = $service->unreadCount($this->adminId);

        $this->assert($ok === true, 'markRead devuelve true');
        $this->assert($after < $before, 'markRead reduce el contador');
    }

    private function testMarkAllRead(): void
    {
        $repo = new NotificationRepository();
        $id = $repo->create([
            'user_id' => $this->adminId,
            'type' => NotificationCatalog::TYPE_WOMEN_REFERRAL_CREATED,
            'title' => 'Prueba derivación',
            'message' => 'Derivación de prueba.',
            'related_type' => 'women_case',
            'related_id' => 999003,
        ]);
        $this->createdIds[] = $id;

        $service = new NotificationService();
        $marked = $service->markAllRead($this->adminId);

        $this->assert($marked >= 1, 'markAllRead marca al menos una notificación');
        $this->assert($repo->unreadCount($this->adminId) === 0, 'markAllRead deja contador en cero');
    }

    private function testDedupe(): void
    {
        $service = new NotificationService();
        $service->notify(
            $this->adminId,
            NotificationCatalog::TYPE_SENDA_FOLLOWUP_DUE,
            'Seguimiento duplicado',
            'Primera alerta',
            'senda_follow_up',
            999004
        );
        $service->notify(
            $this->adminId,
            NotificationCatalog::TYPE_SENDA_FOLLOWUP_DUE,
            'Seguimiento duplicado',
            'Segunda alerta',
            'senda_follow_up',
            999004
        );

        $repo = new NotificationRepository();
        $rows = array_filter(
            $repo->listForUser($this->adminId, 20),
            static fn (array $row): bool => (int) ($row['related_id'] ?? 0) === 999004
                && (string) ($row['type'] ?? '') === NotificationCatalog::TYPE_SENDA_FOLLOWUP_DUE
        );

        foreach ($rows as $row) {
            $this->createdIds[] = (int) $row['id'];
        }

        $this->assert(count($rows) === 1, 'hasUnreadDuplicate evita duplicados sin leer');
    }

    private function testPermissionLookup(): void
    {
        $repo = new PermissionRepository();
        $ids = $repo->userIdsWithPermission('dashboard.access');

        $this->assert($ids !== [], 'userIdsWithPermission devuelve usuarios');
        $this->assert(in_array($this->adminId, $ids, true), 'superadmin está incluido');
    }

    private function testHelpers(): void
    {
        $repo = new NotificationRepository();
        $id = $repo->create([
            'user_id' => $this->adminId,
            'type' => NotificationCatalog::TYPE_MEETING_ATTENDANCE_CONFIRMED,
            'title' => 'Asistencia confirmada',
            'message' => 'Participante confirmó asistencia.',
            'related_type' => 'meeting',
            'related_id' => 999005,
        ]);
        $this->createdIds[] = $id;

        $this->assert(function_exists('notifications_unread_count'), 'helper notifications_unread_count existe');
        $this->assert(function_exists('notifications_recent'), 'helper notifications_recent existe');
        $this->assert(notifications_unread_count() >= 1, 'notifications_unread_count funciona');
        $this->assert(notifications_recent(3) !== [], 'notifications_recent funciona');
    }

    private function cleanup(): void
    {
        if ($this->createdIds === []) {
            return;
        }

        $pdo = Database::connection();
        $placeholders = implode(',', array_fill(0, count($this->createdIds), '?'));
        $stmt = $pdo->prepare('DELETE FROM notifications WHERE id IN (' . $placeholders . ')');
        $stmt->execute($this->createdIds);
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

        if ($this->failures !== []) {
            echo "Detalle:\n";
            foreach ($this->failures as $failure) {
                echo " - {$failure}\n";
            }
        }
    }
}

exit((new NotificationsFunctionalTests())->run());
