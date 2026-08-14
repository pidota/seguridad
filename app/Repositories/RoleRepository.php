<?php

declare(strict_types=1);

namespace App\Repositories;

use Core\Database;

final class RoleRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function all(): array
    {
        $stmt = $this->db()->query('SELECT * FROM roles ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $row['permission_ids'] = $this->permissionIds($id);

        return $row;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM roles WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM roles WHERE slug = :slug';
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
            'INSERT INTO roles (slug, name, description, is_system)
             VALUES (:slug, :name, :description, 0)'
        );
        $stmt->execute([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE roles SET name = :name, description = :description, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare('DELETE FROM roles WHERE id = :id AND is_system = 0');
        $stmt->execute(['id' => $id]);
    }

    public function permissionIds(int $roleId): array
    {
        $stmt = $this->db()->prepare('SELECT permission_id FROM role_permissions WHERE role_id = :id');
        $stmt->execute(['id' => $roleId]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $this->db()->prepare('DELETE FROM role_permissions WHERE role_id = :id')->execute(['id' => $roleId]);

        $insert = $this->db()->prepare(
            'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
        );

        foreach ($permissionIds as $permissionId) {
            $insert->execute([
                'role_id' => $roleId,
                'permission_id' => (int) $permissionId,
            ]);
        }
    }

    public function countUsers(int $roleId): int
    {
        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM user_roles WHERE role_id = :id');
        $stmt->execute(['id' => $roleId]);

        return (int) $stmt->fetchColumn();
    }

    public function idsBySlugs(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($slugs), '?'));
        $stmt = $this->db()->prepare("SELECT id FROM roles WHERE slug IN ({$placeholders})");
        $stmt->execute(array_values($slugs));

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }
}
