<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

use App\Models\Cctv\Shift;
use Core\Database;

final class ShiftRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function findById(int $id, bool $withDeleted = false): ?array
    {
        $sql = $this->selectSql() . ' WHERE s.id = :id';

        if (!$withDeleted) {
            $sql .= ' AND s.deleted_at IS NULL';
        }

        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findOpenByOperator(int $operatorId): ?array
    {
        $stmt = $this->db()->prepare(
            $this->selectSql() . '
             WHERE s.operator_id = :operator_id
               AND s.status = :status
               AND s.deleted_at IS NULL
             ORDER BY s.started_at DESC
             LIMIT 1'
        );
        $stmt->execute([
            'operator_id' => $operatorId,
            'status' => Shift::STATUS_OPEN,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findLatestOpen(): ?array
    {
        $stmt = $this->db()->prepare(
            $this->selectSql() . '
             WHERE s.status = :status
               AND s.deleted_at IS NULL
             ORDER BY s.started_at DESC, s.id DESC
             LIMIT 1'
        );
        $stmt->execute(['status' => Shift::STATUS_OPEN]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function countOpen(): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*)
             FROM cctv_shifts s
             WHERE s.status = :status
               AND s.deleted_at IS NULL'
        );
        $stmt->execute(['status' => Shift::STATUS_OPEN]);

        return (int) $stmt->fetchColumn();
    }

    public function findLastClosedByOperator(int $operatorId): ?array
    {
        $stmt = $this->db()->prepare(
            $this->selectSql() . '
             WHERE s.operator_id = :operator_id
               AND s.status = :status
               AND s.deleted_at IS NULL
             ORDER BY s.shift_date DESC, s.started_at DESC, s.id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'operator_id' => $operatorId,
            'status' => Shift::STATUS_CLOSED,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @param array{
     *     operator_id?: int|string|null,
     *     date_from?: string,
     *     date_to?: string,
     *     status?: string
     * } $filters
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->filterSql($filters);
        $from = $this->listFromSql() . ' WHERE 1 = 1' . $where;

        $count = $this->db()->prepare('SELECT COUNT(*) ' . $from);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $sql = $this->listSelectSql() . ' WHERE 1 = 1' . $where . '
                ORDER BY s.shift_date DESC, s.started_at DESC, s.id DESC
                LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function operatorOptions(): array
    {
        $stmt = $this->db()->query(
            'SELECT DISTINCT u.id, u.name
             FROM users u
             INNER JOIN cctv_shifts s ON s.operator_id = u.id AND s.deleted_at IS NULL
             ORDER BY u.name ASC'
        );

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function paginateByOperator(int $operatorId, int $page, int $perPage): array
    {
        $from = $this->fromSql() . '
                WHERE s.operator_id = :operator_id
                  AND s.deleted_at IS NULL';

        $count = $this->db()->prepare('SELECT COUNT(*) ' . $from);
        $count->execute(['operator_id' => $operatorId]);
        $total = (int) $count->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $sql = $this->selectSql() . '
                WHERE s.operator_id = :operator_id
                  AND s.deleted_at IS NULL
                ORDER BY s.shift_date DESC, s.started_at DESC, s.id DESC
                LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['operator_id' => $operatorId]);

        return ['data' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByOperator(int $operatorId, int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));
        $stmt = $this->db()->prepare(
            $this->selectSql() . '
             WHERE s.operator_id = :operator_id
               AND s.deleted_at IS NULL
             ORDER BY s.shift_date DESC, s.started_at DESC, s.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute(['operator_id' => $operatorId]);

        return $stmt->fetchAll() ?: [];
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO cctv_shifts (
                    operator_id, shift_date, status, started_at, ended_at,
                    opening_notes, closing_notes
                ) VALUES (
                    :operator_id, :shift_date, :status, :started_at, :ended_at,
                    :opening_notes, :closing_notes
                )';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'operator_id' => $data['operator_id'],
            'shift_date' => $data['shift_date'],
            'status' => $data['status'],
            'started_at' => $data['started_at'],
            'ended_at' => $data['ended_at'] ?? null,
            'opening_notes' => $data['opening_notes'] ?? null,
            'closing_notes' => $data['closing_notes'] ?? null,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sql = 'UPDATE cctv_shifts
                SET shift_date = :shift_date,
                    status = :status,
                    started_at = :started_at,
                    ended_at = :ended_at,
                    opening_notes = :opening_notes,
                    closing_notes = :closing_notes,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_merge($data, ['id' => $id]));
    }

    public function close(int $id, string $endedAt, ?string $closingNotes): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE cctv_shifts
             SET status = :status,
                 ended_at = :ended_at,
                 closing_notes = :closing_notes,
                 updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'id' => $id,
            'status' => Shift::STATUS_CLOSED,
            'ended_at' => $endedAt,
            'closing_notes' => $closingNotes,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentWithEntryCounts(int $limit = 6): array
    {
        $limit = max(1, min($limit, 20));
        $stmt = $this->db()->prepare(
            $this->listSelectSql() . '
             WHERE s.deleted_at IS NULL
             ORDER BY s.started_at DESC, s.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    private function selectSql(): string
    {
        return 'SELECT s.*,
                       operator.name AS operator_name
                ' . $this->fromSql();
    }

    private function fromSql(): string
    {
        return 'FROM cctv_shifts s
                INNER JOIN users operator ON operator.id = s.operator_id';
    }

    private function listSelectSql(): string
    {
        return 'SELECT s.id,
                       s.operator_id,
                       s.shift_date,
                       s.status,
                       s.started_at,
                       s.ended_at,
                       s.opening_notes,
                       s.closing_notes,
                       s.created_at,
                       s.updated_at,
                       s.deleted_at,
                       operator.name AS operator_name,
                       (
                           SELECT COUNT(*)
                           FROM cctv_log_entries e
                           WHERE e.cctv_shift_id = s.id
                             AND e.deleted_at IS NULL
                       ) AS total_entries,
                       (
                           SELECT COUNT(*)
                           FROM cctv_log_entries e
                           INNER JOIN cctv_log_types lt ON lt.id = e.cctv_log_type_id
                           WHERE e.cctv_shift_id = s.id
                             AND e.deleted_at IS NULL
                             AND lt.slug = \'incidente\'
                       ) AS incidents
                ' . $this->fromSql();
    }

    private function listFromSql(): string
    {
        return $this->fromSql();
    }

    /**
     * @param array{
     *     operator_id?: int|string|null,
     *     date_from?: string,
     *     date_to?: string,
     *     status?: string
     * } $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filterSql(array $filters): array
    {
        $where = ' AND s.deleted_at IS NULL';
        $params = [];

        $operatorId = (int) ($filters['operator_id'] ?? 0);
        if ($operatorId > 0) {
            $where .= ' AND s.operator_id = :operator_id';
            $params['operator_id'] = $operatorId;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $where .= ' AND s.shift_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $where .= ' AND s.shift_date <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && Shift::isValidStatus($status)) {
            $where .= ' AND s.status = :status';
            $params['status'] = $status;
        }

        return [$where, $params];
    }
}
