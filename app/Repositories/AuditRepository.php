<?php

declare(strict_types=1);

namespace App\Repositories;

use Core\Database;

final class AuditRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function insert(array $data): void
    {
        $sql = 'INSERT INTO audit_logs
                (user_id, user_name, action, module, resource, resource_id, old_values, new_values, ip_address, user_agent)
                VALUES
                (:user_id, :user_name, :action, :module, :resource, :resource_id, :old_values, :new_values, :ip_address, :user_agent)';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'user_id' => $data['user_id'],
            'user_name' => $data['user_name'],
            'action' => $data['action'],
            'module' => $data['module'],
            'resource' => $data['resource'],
            'resource_id' => $data['resource_id'],
            'old_values' => $data['old_values'],
            'new_values' => $data['new_values'],
            'ip_address' => $data['ip_address'],
            'user_agent' => $data['user_agent'],
        ]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM audit_logs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function paginate(int $page, int $perPage, array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['module'])) {
            $where[] = 'module = :module';
            $params['module'] = $filters['module'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = :action';
            $params['action'] = $filters['action'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(user_name LIKE :q OR resource LIKE :q2 OR resource_id LIKE :q3)';
            $term = '%' . trim((string) $filters['q']) . '%';
            $params['q'] = $term;
            $params['q2'] = $term;
            $params['q3'] = $term;
        }

        $sqlWhere = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $count = $this->db()->prepare("SELECT COUNT(*) FROM audit_logs {$sqlWhere}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->db()->prepare(
            "SELECT * FROM audit_logs {$sqlWhere} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll(), 'total' => $total];
    }

    public function modules(): array
    {
        $stmt = $this->db()->query('SELECT DISTINCT module FROM audit_logs ORDER BY module ASC');
        return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forResource(string $module, string $resource, int|string $resourceId, int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $stmt = $this->db()->prepare(
            'SELECT *
             FROM audit_logs
             WHERE module = :module
               AND resource = :resource
               AND resource_id = :resource_id
             ORDER BY id DESC
             LIMIT ' . $limit
        );
        $stmt->execute([
            'module' => $module,
            'resource' => $resource,
            'resource_id' => (string) $resourceId,
        ]);

        return $stmt->fetchAll() ?: [];
    }
}
