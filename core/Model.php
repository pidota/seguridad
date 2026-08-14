<?php

declare(strict_types=1);

namespace Core;

abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';

    protected function db(): \PDO
    {
        return Database::connection();
    }

    public function find(int|string $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function all(): array
    {
        $stmt = $this->db()->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll();
    }
}
