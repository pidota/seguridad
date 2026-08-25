<?php

declare(strict_types=1);

namespace App\Repositories;

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
        $stmt->execute([
            'user_id' => $data['user_id'],
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'related_type' => $data['related_type'] ?? null,
            'related_id' => $data['related_id'] ?? null,
        ]);

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

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId, int $limit = 20, bool $unreadOnly = false): array
    {
        $limit = max(1, min($limit, 50));
        $sql = 'SELECT * FROM notifications
                WHERE user_id = :user_id';

        if ($unreadOnly) {
            $sql .= ' AND read_at IS NULL';
        }

        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function paginateForUser(int $userId, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 50));
        $offset = ($page - 1) * $perPage;

        $count = $this->db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id');
        $count->execute(['user_id' => $userId]);
        $total = (int) $count->fetchColumn();

        $stmt = $this->db()->prepare(
            'SELECT * FROM notifications
             WHERE user_id = :user_id
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $stmt->execute(['user_id' => $userId]);

        return ['data' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM notifications
             WHERE id = :id AND user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function markRead(int $id, int $userId): bool
    {
        $stmt = $this->db()->prepare(
            'UPDATE notifications
             SET read_at = NOW()
             WHERE id = :id AND user_id = :user_id AND read_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    public function markAllRead(int $userId): int
    {
        $stmt = $this->db()->prepare(
            'UPDATE notifications
             SET read_at = NOW()
             WHERE user_id = :user_id AND read_at IS NULL'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->rowCount();
    }

    public function hasUnreadDuplicate(int $userId, string $type, ?string $relatedType, ?int $relatedId): bool
    {
        $sql = 'SELECT COUNT(*) FROM notifications
                WHERE user_id = :user_id
                  AND type = :type
                  AND read_at IS NULL';
        $params = [
            'user_id' => $userId,
            'type' => $type,
        ];

        if ($relatedType !== null && $relatedId !== null && $relatedId > 0) {
            $sql .= ' AND related_type = :related_type AND related_id = :related_id';
            $params['related_type'] = $relatedType;
            $params['related_id'] = $relatedId;
        }

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }
}
