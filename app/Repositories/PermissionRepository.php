<?php

declare(strict_types=1);

namespace App\Repositories;

use Core\Database;

final class PermissionRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function all(): array
    {
        $stmt = $this->db()->query('SELECT * FROM permissions ORDER BY module ASC, name ASC');
        return $stmt->fetchAll();
    }

    public function groupedByModule(): array
    {
        $grouped = [];

        foreach ($this->all() as $permission) {
            $grouped[$permission['module']][] = $permission;
        }

        return $grouped;
    }

    public function slugsForUser(int $userId): array
    {
        $sql = 'SELECT DISTINCT p.slug
                FROM permissions p
                INNER JOIN role_permissions rp ON rp.permission_id = p.id
                INNER JOIN user_roles ur ON ur.role_id = rp.role_id
                WHERE ur.user_id = :user_id
                ORDER BY p.slug ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM permissions WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function validIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db()->prepare("SELECT id FROM permissions WHERE id IN ({$placeholders})");
        $stmt->execute($ids);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }
}
