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

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->db()->query(
            'SELECT * FROM sectors
             WHERE deleted_at IS NULL
             ORDER BY sort_order ASC, name ASC'
        );

        return $stmt->fetchAll() ?: [];
    }

    public function findForAdmin(int $id): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM sectors
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM sectors WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }

    public function create(array $data): int
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO sectors (slug, name, description, sort_order, is_active)
             VALUES (:slug, :name, :description, :sort_order, :is_active)'
        );
        $stmt->execute([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (int) ($data['is_active'] ?? 1),
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE sectors
             SET name = :name,
                 description = :description,
                 sort_order = :sort_order,
                 is_active = :is_active,
                 updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (int) ($data['is_active'] ?? 1),
        ]);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE sectors
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
    }

    public function usageCount(int $id): int
    {
        $stmt = $this->db()->prepare(
            'SELECT
                (SELECT COUNT(*) FROM cctv_cameras WHERE sector_id = :id) +
                (SELECT COUNT(*) FROM cctv_log_entries WHERE sector_id = :id) AS total'
        );
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn();
    }
}
