<?php

declare(strict_types=1);

namespace App\Repositories\Meetings;

use Core\Database;

final class NotificationRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO notifications (user_id, type, title, message, related_type, related_id)
             VALUES (:user_id, :type, :title, :message, :related_type, :related_id)'
        );
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function unreadCount(int $userId): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*) FROM notifications
             WHERE user_id = :user_id AND read_at IS NULL'
        );
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }
}
