<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

use Core\Database;

final class EquipmentRepository
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
            'SELECT id, slug, name, sort_order
             FROM cctv_equipment
             WHERE is_active = 1
               AND deleted_at IS NULL
             ORDER BY sort_order ASC, name ASC'
        );

        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, slug, name, sort_order, is_active
             FROM cctv_equipment
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
