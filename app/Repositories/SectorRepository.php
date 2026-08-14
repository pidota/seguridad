<?php

declare(strict_types=1);

namespace App\Repositories;

use Core\Database;

final class SectorRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    /**
     * @return list<array{id: int, slug: string, name: string}>
     */
    public function options(): array
    {
        $stmt = $this->db()->query(
            'SELECT id, slug, name
             FROM sectors
             WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY sort_order ASC, name ASC'
        );

        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM sectors
             WHERE id = :id AND is_active = 1 AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
