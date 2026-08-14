<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

use App\Services\Cctv\CameraCatalog;
use Core\Database;

final class CameraRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function findById(int $id, bool $withDeleted = false): ?array
    {
        $sql = $this->selectSql() . ' WHERE c.id = :id';

        if (!$withDeleted) {
            $sql .= ' AND c.deleted_at IS NULL';
        }

        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM cctv_cameras WHERE code = :code AND deleted_at IS NULL';
        $params = ['code' => mb_strtoupper(trim($code))];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage, bool $activeOnly): array
    {
        [$where, $params] = $this->filterSql($filters, $activeOnly);
        $from = $this->fromSql() . ' WHERE c.deleted_at IS NULL' . $where;

        $count = $this->db()->prepare('SELECT COUNT(*) ' . $from);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $sql = $this->selectSql() . ' WHERE c.deleted_at IS NULL' . $where . '
                ORDER BY c.code ASC, c.name ASC
                LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActive(): array
    {
        $stmt = $this->db()->prepare(
            $this->selectSql() . '
             WHERE c.deleted_at IS NULL
               AND c.active = 1
               AND c.status = :status
             ORDER BY c.code ASC, c.name ASC'
        );
        $stmt->execute(['status' => CameraCatalog::STATUS_OPERATIONAL]);

        return $stmt->fetchAll() ?: [];
    }

    public function countMonitoringIssues(): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*)
             FROM cctv_cameras
             WHERE deleted_at IS NULL
               AND active = 1
               AND status IN (:issues, :out_of_service)'
        );
        $stmt->execute([
            'issues' => CameraCatalog::STATUS_ISSUES,
            'out_of_service' => CameraCatalog::STATUS_OUT_OF_SERVICE,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForMonitoring(): array
    {
        $stmt = $this->db()->prepare(
            $this->selectSql() . '
             WHERE c.deleted_at IS NULL
               AND c.active = 1
             ORDER BY c.code ASC, c.name ASC'
        );
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO cctv_cameras (
                    code, name, sector_id, location, camera_type, status, active
                ) VALUES (
                    :code, :name, :sector_id, :location, :camera_type, :status, :active
                )';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sql = 'UPDATE cctv_cameras
                SET code = :code,
                    name = :name,
                    sector_id = :sector_id,
                    location = :location,
                    camera_type = :camera_type,
                    status = :status,
                    active = :active,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_merge($data, ['id' => $id]));
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE cctv_cameras
             SET active = 0, deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
    }

    private function selectSql(): string
    {
        return 'SELECT c.*,
                       s.name AS sector_name
                ' . $this->fromSql();
    }

    private function fromSql(): string
    {
        return 'FROM cctv_cameras c
                LEFT JOIN sectors s ON s.id = c.sector_id AND s.deleted_at IS NULL';
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filterSql(array $filters, bool $activeOnly): array
    {
        $where = '';
        $params = [];

        if ($activeOnly) {
            $where .= ' AND c.active = 1';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (c.code LIKE :q OR c.name LIKE :q2 OR c.location LIKE :q3)';
            $like = '%' . $q . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        $sectorId = $filters['sector_id'] ?? null;
        if ($sectorId !== null && $sectorId !== '') {
            $where .= ' AND c.sector_id = :sector_id';
            $params['sector_id'] = (int) $sectorId;
        }

        $cameraType = trim((string) ($filters['camera_type'] ?? ''));
        if (CameraCatalog::isValidType($cameraType)) {
            $where .= ' AND c.camera_type = :camera_type';
            $params['camera_type'] = $cameraType;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if (CameraCatalog::isValidStatus($status)) {
            $where .= ' AND c.status = :status';
            $params['status'] = $status;
        }

        $active = $filters['active'] ?? null;
        if (!$activeOnly && $active !== null && $active !== '') {
            $where .= ' AND c.active = :active';
            $params['active'] = (int) ((string) $active === '1');
        }

        return [$where, $params];
    }
}
