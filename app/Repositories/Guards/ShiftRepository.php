<?php

declare(strict_types=1);

namespace App\Repositories\Guards;

use App\Models\Guards\Shift;
use Core\Database;

final class ShiftRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare($this->selectSql() . ' WHERE s.id = :id AND s.deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findOpenByGuard(int $guardId): ?array
    {
        $stmt = $this->db()->prepare(
            $this->selectSql() . '
             WHERE s.guard_id = :guard_id
               AND s.status = :status
               AND s.deleted_at IS NULL
             ORDER BY s.started_at DESC
             LIMIT 1'
        );
        $stmt->execute([
            'guard_id' => $guardId,
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
            'SELECT COUNT(*) FROM guards_shifts s WHERE s.status = :status AND s.deleted_at IS NULL'
        );
        $stmt->execute(['status' => Shift::STATUS_OPEN]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->filterSql($filters);
        $from = $this->fromSql() . ' WHERE 1 = 1' . $where;

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
    public function guardOptions(): array
    {
        $stmt = $this->db()->query(
            'SELECT DISTINCT u.id, u.name
             FROM users u
             INNER JOIN guards_shifts s ON s.guard_id = u.id AND s.deleted_at IS NULL
             ORDER BY u.name ASC'
        );

        return $stmt->fetchAll() ?: [];
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO guards_shifts (
                    guard_id, shift_date, status, started_at, ended_at,
                    opening_notes, closing_notes
                ) VALUES (
                    :guard_id, :shift_date, :status, :started_at, :ended_at,
                    :opening_notes, :closing_notes
                )';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'guard_id' => $data['guard_id'],
            'shift_date' => $data['shift_date'],
            'status' => $data['status'],
            'started_at' => $data['started_at'],
            'ended_at' => $data['ended_at'] ?? null,
            'opening_notes' => $data['opening_notes'] ?? null,
            'closing_notes' => $data['closing_notes'] ?? null,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function close(int $id, string $endedAt, ?string $closingNotes): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE guards_shifts
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

    private function selectSql(): string
    {
        return 'SELECT s.*, guard.name AS guard_name ' . $this->fromSql();
    }

    private function fromSql(): string
    {
        return 'FROM guards_shifts s INNER JOIN users guard ON guard.id = s.guard_id';
    }

    private function listSelectSql(): string
    {
        return 'SELECT s.id, s.guard_id, s.shift_date, s.status, s.started_at, s.ended_at,
                       s.opening_notes, s.closing_notes, s.created_at, s.updated_at, s.deleted_at,
                       guard.name AS guard_name,
                       (
                           SELECT COUNT(*)
                           FROM guards_log_entries e
                           WHERE e.guards_shift_id = s.id AND e.deleted_at IS NULL
                       ) AS total_entries,
                       (
                           SELECT COUNT(*)
                           FROM guards_log_entries e
                           INNER JOIN guards_log_types lt ON lt.id = e.guards_log_type_id
                           WHERE e.guards_shift_id = s.id
                             AND e.deleted_at IS NULL
                             AND lt.slug = \'incidencia\'
                       ) AS incidents
                ' . $this->fromSql();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filterSql(array $filters): array
    {
        $where = ' AND s.deleted_at IS NULL';
        $params = [];

        $guardId = (int) ($filters['guard_id'] ?? 0);
        if ($guardId > 0) {
            $where .= ' AND s.guard_id = :guard_id';
            $params['guard_id'] = $guardId;
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
