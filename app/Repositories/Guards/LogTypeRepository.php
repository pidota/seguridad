<?php

declare(strict_types=1);

namespace App\Repositories\Guards;

use Core\Database;

final class LogTypeRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActive(): array
    {
        $stmt = $this->db()->query(
            'SELECT id, slug, name, description, tone, sort_order
             FROM guards_log_types
             WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY sort_order ASC, name ASC'
        );

        return $stmt->fetchAll() ?: [];
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM guards_log_types
             WHERE slug = :slug AND is_active = 1 AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM guards_log_types WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
