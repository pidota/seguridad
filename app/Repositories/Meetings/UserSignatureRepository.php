<?php

declare(strict_types=1);

namespace App\Repositories\Meetings;

use Core\Database;

final class UserSignatureRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function findActiveByUserId(int $userId): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM user_signatures
             WHERE user_id = :user_id AND is_active = 1
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function deactivateForUser(int $userId): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE user_signatures SET is_active = 0, updated_at = NOW()
             WHERE user_id = :user_id AND is_active = 1'
        );
        $stmt->execute(['user_id' => $userId]);
    }

    public function create(array $data): int
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO user_signatures (user_id, image_path, is_active)
             VALUES (:user_id, :image_path, 1)'
        );
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }
}
