<?php

declare(strict_types=1);

namespace App\Repositories\Guards;

use App\Models\Guards\LogEntry;
use App\Models\Guards\LogType;
use Core\Database;

final class LogEntryRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare($this->selectSql() . ' WHERE e.id = :id AND e.deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByShift(int $shiftId): array
    {
        $stmt = $this->db()->prepare(
            $this->selectSql() . '
             WHERE e.guards_shift_id = :shift_id AND e.deleted_at IS NULL
             ORDER BY e.occurred_at ASC, e.id ASC'
        );
        $stmt->execute(['shift_id' => $shiftId]);

        return $stmt->fetchAll() ?: [];
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

        $sql = $this->selectSql() . ' WHERE 1 = 1' . $where . '
                ORDER BY e.occurred_at DESC, e.id DESC
                LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    /**
     * @return array<string, int>
     */
    public function shiftStats(int $shiftId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT
                COUNT(*) AS total_entries,
                SUM(CASE WHEN lt.slug = :incidente THEN 1 ELSE 0 END) AS incidents,
                SUM(CASE WHEN lt.slug = :ronda THEN 1 ELSE 0 END) AS rounds,
                SUM(CASE WHEN e.cctv_log_entry_id IS NOT NULL THEN 1 ELSE 0 END) AS cctv_linked
             FROM guards_log_entries e
             INNER JOIN guards_log_types lt ON lt.id = e.guards_log_type_id
             WHERE e.guards_shift_id = :shift_id AND e.deleted_at IS NULL'
        );
        $stmt->execute([
            'shift_id' => $shiftId,
            'incidente' => LogType::SLUG_INCIDENTE,
            'ronda' => LogType::SLUG_RONDA,
        ]);
        $row = $stmt->fetch() ?: [];

        return [
            'total_entries' => (int) ($row['total_entries'] ?? 0),
            'incidents' => (int) ($row['incidents'] ?? 0),
            'rounds' => (int) ($row['rounds'] ?? 0),
            'cctv_linked' => (int) ($row['cctv_linked'] ?? 0),
        ];
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO guards_log_entries (
                    guards_shift_id, guards_log_type_id, sector_id, occurred_at,
                    observations, status, cctv_log_entry_id, created_by
                ) VALUES (
                    :guards_shift_id, :guards_log_type_id, :sector_id, :occurred_at,
                    :observations, :status, :cctv_log_entry_id, :created_by
                )';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'guards_shift_id' => $data['guards_shift_id'],
            'guards_log_type_id' => $data['guards_log_type_id'],
            'sector_id' => $data['sector_id'] ?? null,
            'occurred_at' => $data['occurred_at'],
            'observations' => $data['observations'],
            'status' => $data['status'] ?? LogEntry::STATUS_REGISTERED,
            'cctv_log_entry_id' => $data['cctv_log_entry_id'] ?? null,
            'created_by' => $data['created_by'],
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function attachCctvLogEntry(int $id, int $cctvLogEntryId): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE guards_log_entries
             SET cctv_log_entry_id = :cctv_log_entry_id, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'id' => $id,
            'cctv_log_entry_id' => $cctvLogEntryId,
        ]);
    }

    private function selectSql(): string
    {
        return 'SELECT e.*,
                       lt.slug AS log_type_slug,
                       lt.name AS log_type_name,
                       lt.tone AS log_type_tone,
                       sector.name AS sector_name,
                       guard.name AS guard_name,
                       creator.name AS created_by_name
                ' . $this->fromSql();
    }

    private function fromSql(): string
    {
        return 'FROM guards_log_entries e
                INNER JOIN guards_log_types lt ON lt.id = e.guards_log_type_id
                INNER JOIN guards_shifts s ON s.id = e.guards_shift_id
                INNER JOIN users guard ON guard.id = s.guard_id
                INNER JOIN users creator ON creator.id = e.created_by
                LEFT JOIN sectors sector ON sector.id = e.sector_id';
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filterSql(array $filters): array
    {
        $where = ' AND e.deleted_at IS NULL';
        $params = [];

        $guardId = (int) ($filters['guard_id'] ?? 0);
        if ($guardId > 0) {
            $where .= ' AND s.guard_id = :guard_id';
            $params['guard_id'] = $guardId;
        }

        $shiftId = (int) ($filters['shift_id'] ?? 0);
        if ($shiftId > 0) {
            $where .= ' AND e.guards_shift_id = :shift_id';
            $params['shift_id'] = $shiftId;
        }

        $typeId = (int) ($filters['log_type_id'] ?? 0);
        if ($typeId > 0) {
            $where .= ' AND e.guards_log_type_id = :log_type_id';
            $params['log_type_id'] = $typeId;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $where .= ' AND DATE(e.occurred_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $where .= ' AND DATE(e.occurred_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        return [$where, $params];
    }
}
