<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

use Core\Database;

abstract class CatalogRepository
{
    abstract protected function table(): string;

    private function db(): \PDO
    {
        return Database::connection();
    }

    public function findById(int $id, bool $includeInactive = false): ?array
    {
        $sql = 'SELECT * FROM ' . $this->table() . ' WHERE id = :id';

        if (!$includeInactive) {
            $sql .= ' AND is_active = 1 AND deleted_at IS NULL';
        }

        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findBySlug(string $slug, bool $includeInactive = false): ?array
    {
        $sql = 'SELECT * FROM ' . $this->table() . ' WHERE slug = :slug';

        if (!$includeInactive) {
            $sql .= ' AND is_active = 1 AND deleted_at IS NULL';
        }

        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute(['slug' => trim($slug)]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActive(): array
    {
        $stmt = $this->db()->query(
            'SELECT * FROM ' . $this->table() . '
             WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY sort_order ASC, name ASC'
        );

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAll(bool $includeInactive = true): array
    {
        $sql = 'SELECT * FROM ' . $this->table();

        if (!$includeInactive) {
            $sql .= ' WHERE is_active = 1 AND deleted_at IS NULL';
        } else {
            $sql .= ' WHERE deleted_at IS NULL';
        }

        $sql .= ' ORDER BY sort_order ASC, name ASC';

        return $this->db()->query($sql)->fetchAll() ?: [];
    }

    /**
     * @return list<string>
     */
    public function activeSlugs(): array
    {
        $stmt = $this->db()->query(
            'SELECT slug FROM ' . $this->table() . '
             WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY sort_order ASC, name ASC'
        );

        return array_column($stmt->fetchAll() ?: [], 'slug');
    }

    public function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table(),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = $column . ' = :' . $column;
        }
        $sets[] = 'updated_at = NOW()';

        $sql = 'UPDATE ' . $this->table() . ' SET ' . implode(', ', $sets) . ' WHERE id = :id AND deleted_at IS NULL';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_merge($data, ['id' => $id]));
    }

    public function deactivate(int $id): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE ' . $this->table() . '
             SET is_active = 0, deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
    }
}
