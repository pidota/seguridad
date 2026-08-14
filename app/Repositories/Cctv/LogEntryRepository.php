<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

use App\Models\Cctv\LogEntry;
use Core\Database;

final class LogEntryRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function findById(int $id, bool $withDeleted = false): ?array
    {
        $sql = $this->selectSql() . ' WHERE e.id = :id';

        if (!$withDeleted) {
            $sql .= ' AND e.deleted_at IS NULL';
        }

        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByShift(int $shiftId, int $limit = 100, string $order = 'desc'): array
    {
        $limit = max(1, min($limit, 500));
        $direction = strtolower($order) === 'asc' ? 'ASC' : 'DESC';
        $stmt = $this->db()->prepare(
            $this->selectListSql() . '
             WHERE e.cctv_shift_id = :shift_id
               AND e.deleted_at IS NULL
             ORDER BY e.occurred_at ' . $direction . ', e.id ' . $direction . '
             LIMIT ' . $limit
        );
        $stmt->execute(['shift_id' => $shiftId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return array{
     *     total_entries: int,
     *     incidents: int,
     *     general_entries: int,
     *     technical_issues: int,
     *     coordinations: int
     * }
     */
    public function shiftStats(int $shiftId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*) AS total_entries,
                    SUM(CASE WHEN lt.slug = :incident_slug THEN 1 ELSE 0 END) AS incidents,
                    SUM(CASE WHEN lt.slug = :general_slug THEN 1 ELSE 0 END) AS general_entries,
                    SUM(CASE WHEN lt.slug = :technical_slug THEN 1 ELSE 0 END) AS technical_issues,
                    SUM(CASE WHEN e.coordination_notified = 1 THEN 1 ELSE 0 END) AS coordinations,
                    SUM(CASE WHEN (
                        (lt.slug = :incident_slug_police AND e.police_arrived = :police_yes)
                        OR EXISTS (
                            SELECT 1
                            FROM cctv_log_contacts lc
                            WHERE lc.cctv_log_entry_id = e.id
                              AND lc.contact_type = :contact_carabineros
                        )
                    ) THEN 1 ELSE 0 END) AS police_communications
             FROM cctv_log_entries e
             INNER JOIN cctv_log_types lt ON lt.id = e.cctv_log_type_id
             WHERE e.cctv_shift_id = :shift_id
               AND e.deleted_at IS NULL'
        );
        $stmt->execute([
            'shift_id' => $shiftId,
            'incident_slug' => 'incidente',
            'incident_slug_police' => 'incidente',
            'general_slug' => 'novedad',
            'technical_slug' => 'novedad_tecnica',
            'police_yes' => 1,
            'contact_carabineros' => 'carabineros',
        ]);
        $row = $stmt->fetch() ?: [];

        return [
            'total_entries' => (int) ($row['total_entries'] ?? 0),
            'incidents' => (int) ($row['incidents'] ?? 0),
            'general_entries' => (int) ($row['general_entries'] ?? 0),
            'technical_issues' => (int) ($row['technical_issues'] ?? 0),
            'coordinations' => (int) ($row['coordinations'] ?? 0),
            'police_communications' => (int) ($row['police_communications'] ?? 0),
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function paginateByShift(int $shiftId, int $page, int $perPage): array
    {
        $from = $this->fromSql() . '
                WHERE e.cctv_shift_id = :shift_id
                  AND e.deleted_at IS NULL';

        $count = $this->db()->prepare('SELECT COUNT(*) ' . $from);
        $count->execute(['shift_id' => $shiftId]);
        $total = (int) $count->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $sql = $this->selectSql() . '
                WHERE e.cctv_shift_id = :shift_id
                  AND e.deleted_at IS NULL
                ORDER BY e.occurred_at DESC, e.id DESC
                LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['shift_id' => $shiftId]);

        return ['data' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    /**
     * @param array{
     *     created_by?: int|string|null,
     *     date_from?: string,
     *     date_to?: string,
     *     log_type?: string,
     *     incident_type?: string,
     *     sector_id?: int|string|null,
     *     camera_id?: int|string|null,
     *     contact_type?: string,
     *     status?: string,
     *     q?: string,
     *     police_arrived?: string,
     *     coordination_notified?: string
     * } $filters
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->filterSql($filters);
        $from = $this->fromSql() . ' WHERE e.deleted_at IS NULL' . $where;

        $count = $this->db()->prepare('SELECT COUNT(*) ' . $from);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $sql = $this->selectListSql() . ' WHERE e.deleted_at IS NULL' . $where . '
                ORDER BY e.occurred_at DESC, e.id DESC
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
             INNER JOIN cctv_log_entries e ON e.created_by = u.id AND e.deleted_at IS NULL
             ORDER BY u.name ASC'
        );

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return array{
     *     total_entries: int,
     *     incidents: int,
     *     technical_issues: int,
     *     coordinations: int,
     *     police_communications: int
     * }
     */
    public function statsForDate(string $date): array
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return $this->emptyRangeStats();
        }

        return $this->statsForRange($date, $date);
    }

    /**
     * @return array{
     *     total_entries: int,
     *     incidents: int,
     *     technical_issues: int,
     *     coordinations: int,
     *     police_communications: int
     * }
     */
    public function statsForRange(string $dateFrom, string $dateTo): array
    {
        if (
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) !== 1
        ) {
            return $this->emptyRangeStats();
        }

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        [$occurredFrom, $occurredTo] = $this->rangeBounds($dateFrom, $dateTo);

        $stmt = $this->db()->prepare(
            'SELECT COUNT(*) AS total_entries,
                    SUM(CASE WHEN lt.slug = :incident_slug THEN 1 ELSE 0 END) AS incidents,
                    SUM(CASE WHEN lt.slug = :technical_slug THEN 1 ELSE 0 END) AS technical_issues,
                    SUM(CASE WHEN e.coordination_notified = 1 THEN 1 ELSE 0 END) AS coordinations,
                    SUM(CASE WHEN (
                        (lt.slug = :incident_slug_police AND e.police_arrived = :police_yes)
                        OR EXISTS (
                            SELECT 1
                            FROM cctv_log_contacts lc
                            WHERE lc.cctv_log_entry_id = e.id
                              AND lc.contact_type = :contact_carabineros
                        )
                    ) THEN 1 ELSE 0 END) AS police_communications
             FROM cctv_log_entries e
             INNER JOIN cctv_log_types lt ON lt.id = e.cctv_log_type_id
             WHERE e.deleted_at IS NULL
               AND e.occurred_at >= :occurred_from
               AND e.occurred_at < :occurred_to'
        );
        $stmt->execute([
            'occurred_from' => $occurredFrom,
            'occurred_to' => $occurredTo,
            'incident_slug' => 'incidente',
            'incident_slug_police' => 'incidente',
            'technical_slug' => 'novedad_tecnica',
            'police_yes' => 1,
            'contact_carabineros' => 'carabineros',
        ]);
        $row = $stmt->fetch() ?: [];

        return [
            'total_entries' => (int) ($row['total_entries'] ?? 0),
            'incidents' => (int) ($row['incidents'] ?? 0),
            'technical_issues' => (int) ($row['technical_issues'] ?? 0),
            'coordinations' => (int) ($row['coordinations'] ?? 0),
            'police_communications' => (int) ($row['police_communications'] ?? 0),
        ];
    }

    /**
     * @return list<array{sector_id: int|null, sector_name: string, total: int}>
     */
    public function incidentsBySector(string $dateFrom, string $dateTo, int $limit = 8): array
    {
        if (
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) !== 1
        ) {
            return [];
        }

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $limit = max(1, min($limit, 20));
        [$occurredFrom, $occurredTo] = $this->rangeBounds($dateFrom, $dateTo);

        $stmt = $this->db()->prepare(
            'SELECT e.sector_id,
                    COALESCE(sec.name, :unknown_sector) AS sector_name,
                    COUNT(*) AS total
             FROM cctv_log_entries e
             INNER JOIN cctv_log_types lt ON lt.id = e.cctv_log_type_id
             LEFT JOIN sectors sec ON sec.id = e.sector_id
             WHERE e.deleted_at IS NULL
               AND lt.slug = :incident_slug
               AND e.occurred_at >= :occurred_from
               AND e.occurred_at < :occurred_to
             GROUP BY e.sector_id, sec.name
             ORDER BY total DESC, sector_name ASC
             LIMIT ' . $limit
        );
        $stmt->execute([
            'occurred_from' => $occurredFrom,
            'occurred_to' => $occurredTo,
            'incident_slug' => 'incidente',
            'unknown_sector' => 'Sin sector',
        ]);

        return array_map(static function (array $row): array {
            $sectorId = $row['sector_id'] ?? null;

            return [
                'sector_id' => $sectorId !== null ? (int) $sectorId : null,
                'sector_name' => (string) ($row['sector_name'] ?? 'Sin sector'),
                'total' => (int) ($row['total'] ?? 0),
            ];
        }, $stmt->fetchAll() ?: []);
    }

    /**
     * @return list<array{slug: string, name: string, total: int}>
     */
    public function incidentsByType(string $dateFrom, string $dateTo, int $limit = 8): array
    {
        if (
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) !== 1
        ) {
            return [];
        }

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $limit = max(1, min($limit, 20));
        [$occurredFrom, $occurredTo] = $this->rangeBounds($dateFrom, $dateTo);

        $stmt = $this->db()->prepare(
            'SELECT it.slug,
                    it.name,
                    COUNT(*) AS total
             FROM cctv_log_entries e
             INNER JOIN cctv_log_types lt ON lt.id = e.cctv_log_type_id
             INNER JOIN cctv_incident_types it ON it.id = e.cctv_incident_type_id
             WHERE e.deleted_at IS NULL
               AND lt.slug = :incident_slug
               AND e.occurred_at >= :occurred_from
               AND e.occurred_at < :occurred_to
             GROUP BY it.id, it.slug, it.name
             ORDER BY total DESC, it.name ASC
             LIMIT ' . $limit
        );
        $stmt->execute([
            'occurred_from' => $occurredFrom,
            'occurred_to' => $occurredTo,
            'incident_slug' => 'incidente',
        ]);

        return array_map(static fn (array $row): array => [
            'slug' => (string) ($row['slug'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'total' => (int) ($row['total'] ?? 0),
        ], $stmt->fetchAll() ?: []);
    }

    /**
     * Incidentes con llegada de Carabineros registrada, listos para calcular tiempo de respuesta.
     *
     * @return list<array{
     *     entry_id: int,
     *     occurred_at: string,
     *     police_arrival_time: string,
     *     carabineros_notified_at: string|null
     * }>
     */
    public function policeResponseCandidates(string $dateFrom, string $dateTo): array
    {
        if (
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) !== 1
        ) {
            return [];
        }

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        [$occurredFrom, $occurredTo] = $this->rangeBounds($dateFrom, $dateTo);

        $stmt = $this->db()->prepare(
            'SELECT e.id AS entry_id,
                    e.occurred_at,
                    e.police_arrival_time,
                    notify.contacted_at AS carabineros_notified_at
             FROM cctv_log_entries e
             INNER JOIN cctv_log_types lt ON lt.id = e.cctv_log_type_id
             LEFT JOIN (
                 SELECT lc.cctv_log_entry_id,
                        MIN(lc.contacted_at) AS contacted_at
                 FROM cctv_log_contacts lc
                 WHERE lc.contact_type = :contact_carabineros
                 GROUP BY lc.cctv_log_entry_id
             ) notify ON notify.cctv_log_entry_id = e.id
             WHERE e.deleted_at IS NULL
               AND lt.slug = :incident_slug
               AND e.police_arrived = :police_yes
               AND e.police_arrival_time IS NOT NULL
               AND e.occurred_at >= :occurred_from
               AND e.occurred_at < :occurred_to
             ORDER BY e.occurred_at DESC, e.id DESC'
        );
        $stmt->execute([
            'occurred_from' => $occurredFrom,
            'occurred_to' => $occurredTo,
            'incident_slug' => 'incidente',
            'police_yes' => 1,
            'contact_carabineros' => 'carabineros',
        ]);

        return array_map(static function (array $row): array {
            return [
                'entry_id' => (int) ($row['entry_id'] ?? 0),
                'occurred_at' => (string) ($row['occurred_at'] ?? ''),
                'police_arrival_time' => (string) ($row['police_arrival_time'] ?? ''),
                'carabineros_notified_at' => isset($row['carabineros_notified_at']) && $row['carabineros_notified_at'] !== null
                    ? (string) $row['carabineros_notified_at']
                    : null,
            ];
        }, $stmt->fetchAll() ?: []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentEntries(int $limit = 8): array
    {
        $limit = max(1, min($limit, 20));
        $stmt = $this->db()->prepare(
            $this->selectListSql() . '
             WHERE e.deleted_at IS NULL
             ORDER BY e.occurred_at DESC, e.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param array{
     *     created_by?: int|string|null,
     *     date_from?: string,
     *     date_to?: string,
     *     log_type?: string,
     *     incident_type?: string,
     *     sector_id?: int|string|null,
     *     camera_id?: int|string|null,
     *     contact_type?: string,
     *     status?: string,
     *     q?: string,
     *     police_arrived?: string,
     *     coordination_notified?: string
     * } $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filterSql(array $filters): array
    {
        $where = '';
        $params = [];

        $createdBy = (int) ($filters['created_by'] ?? 0);
        if ($createdBy > 0) {
            $where .= ' AND e.created_by = :created_by';
            $params['created_by'] = $createdBy;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $where .= ' AND e.occurred_at >= :date_from';
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $where .= ' AND e.occurred_at < :date_to';
            $params['date_to'] = date('Y-m-d H:i:s', strtotime($dateTo . ' +1 day'));
        }

        $logType = trim((string) ($filters['log_type'] ?? ''));
        if ($logType !== '') {
            $where .= ' AND lt.slug = :log_type';
            $params['log_type'] = $logType;
        }

        $incidentType = trim((string) ($filters['incident_type'] ?? ''));
        if ($incidentType !== '') {
            $where .= ' AND it.slug = :incident_type';
            $params['incident_type'] = $incidentType;
        }

        $sectorId = (int) ($filters['sector_id'] ?? 0);
        if ($sectorId > 0) {
            $where .= ' AND e.sector_id = :sector_id';
            $params['sector_id'] = $sectorId;
        }

        $cameraId = (int) ($filters['camera_id'] ?? 0);
        if ($cameraId > 0) {
            $where .= ' AND e.cctv_camera_id = :camera_id';
            $params['camera_id'] = $cameraId;
        }

        $contactType = trim((string) ($filters['contact_type'] ?? ''));
        if ($contactType !== '') {
            $where .= ' AND EXISTS (
                SELECT 1
                FROM cctv_log_contacts lc
                WHERE lc.cctv_log_entry_id = e.id
                  AND lc.contact_type = :contact_type
            )';
            $params['contact_type'] = $contactType;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where .= ' AND e.status = :status';
            $params['status'] = $status;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if (mb_strlen($query) >= 2) {
            $where .= ' AND e.observations LIKE :q';
            $params['q'] = '%' . $query . '%';
        }

        $policeArrived = trim((string) ($filters['police_arrived'] ?? ''));
        if ($policeArrived !== '') {
            $where .= ' AND e.police_arrived = :police_arrived';
            $params['police_arrived'] = $policeArrived;
        }

        $coordinationNotified = trim((string) ($filters['coordination_notified'] ?? ''));
        if ($coordinationNotified === '1') {
            $where .= ' AND e.coordination_notified = 1';
        } elseif ($coordinationNotified === '0') {
            $where .= ' AND (e.coordination_notified IS NULL OR e.coordination_notified = 0)';
        }

        return [$where, $params];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $sql = 'INSERT INTO cctv_log_entries (
                    cctv_shift_id, cctv_log_type_id, cctv_incident_type_id, cctv_technical_issue_type_id,
                    incident_type_other, technical_issue_other,
                    cctv_camera_id, cctv_equipment_id, camera_status_applied, sector_id, occurred_at, observations,
                    police_arrived, police_arrival_time, coordination_notified, status, created_by
                ) VALUES (
                    :cctv_shift_id, :cctv_log_type_id, :cctv_incident_type_id, :cctv_technical_issue_type_id,
                    :incident_type_other, :technical_issue_other,
                    :cctv_camera_id, :cctv_equipment_id, :camera_status_applied, :sector_id, :occurred_at, :observations,
                    :police_arrived, :police_arrival_time, :coordination_notified, :status, :created_by
                )';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'cctv_shift_id' => $data['cctv_shift_id'],
            'cctv_log_type_id' => $data['cctv_log_type_id'],
            'cctv_incident_type_id' => $data['cctv_incident_type_id'] ?? null,
            'cctv_technical_issue_type_id' => $data['cctv_technical_issue_type_id'] ?? null,
            'incident_type_other' => $data['incident_type_other'] ?? null,
            'technical_issue_other' => $data['technical_issue_other'] ?? null,
            'cctv_camera_id' => $data['cctv_camera_id'] ?? null,
            'cctv_equipment_id' => $data['cctv_equipment_id'] ?? null,
            'camera_status_applied' => $data['camera_status_applied'] ?? null,
            'sector_id' => $data['sector_id'] ?? null,
            'occurred_at' => $data['occurred_at'],
            'observations' => $data['observations'],
            'police_arrived' => $data['police_arrived'] ?? null,
            'police_arrival_time' => $data['police_arrival_time'] ?? null,
            'coordination_notified' => $data['coordination_notified'] ?? null,
            'status' => $data['status'] ?? LogEntry::STATUS_REGISTERED,
            'created_by' => $data['created_by'],
        ]);

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $sql = 'UPDATE cctv_log_entries
                SET cctv_log_type_id = :cctv_log_type_id,
                    cctv_incident_type_id = :cctv_incident_type_id,
                    cctv_technical_issue_type_id = :cctv_technical_issue_type_id,
                    incident_type_other = :incident_type_other,
                    technical_issue_other = :technical_issue_other,
                    cctv_camera_id = :cctv_camera_id,
                    cctv_equipment_id = :cctv_equipment_id,
                    camera_status_applied = :camera_status_applied,
                    sector_id = :sector_id,
                    occurred_at = :occurred_at,
                    observations = :observations,
                    police_arrived = :police_arrived,
                    police_arrival_time = :police_arrival_time,
                    coordination_notified = :coordination_notified,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'cctv_log_type_id' => $data['cctv_log_type_id'],
            'cctv_incident_type_id' => $data['cctv_incident_type_id'] ?? null,
            'cctv_technical_issue_type_id' => $data['cctv_technical_issue_type_id'] ?? null,
            'incident_type_other' => $data['incident_type_other'] ?? null,
            'technical_issue_other' => $data['technical_issue_other'] ?? null,
            'cctv_camera_id' => $data['cctv_camera_id'] ?? null,
            'cctv_equipment_id' => $data['cctv_equipment_id'] ?? null,
            'camera_status_applied' => $data['camera_status_applied'] ?? null,
            'sector_id' => $data['sector_id'] ?? null,
            'occurred_at' => $data['occurred_at'],
            'observations' => $data['observations'],
            'police_arrived' => $data['police_arrived'] ?? null,
            'police_arrival_time' => $data['police_arrival_time'] ?? null,
            'coordination_notified' => $data['coordination_notified'] ?? null,
            'status' => $data['status'] ?? LogEntry::STATUS_REGISTERED,
        ]);
    }

    public function softDelete(int $id, int $cancelledBy): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE cctv_log_entries
             SET deleted_at = NOW(), updated_at = NOW(), cancelled_by = :cancelled_by
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'id' => $id,
            'cancelled_by' => $cancelledBy,
        ]);
    }

    private function selectSql(): string
    {
        return 'SELECT ' . $this->entryColumnsSql() . ',
                       s.shift_date,
                       s.status AS shift_status,
                       operator.name AS shift_operator_name,
                       lt.slug AS log_type_slug,
                       lt.name AS log_type_name,
                       lt.tone AS log_type_tone,
                       lt.requires_incident AS log_type_requires_incident,
                       it.slug AS incident_type_slug,
                       it.name AS incident_type_name,
                       it.tone AS incident_type_tone,
                       tit.slug AS technical_issue_type_slug,
                       tit.name AS technical_issue_type_name,
                       tit.tone AS technical_issue_type_tone,
                       eq.slug AS equipment_slug,
                       eq.name AS equipment_name,
                       cam.code AS camera_code,
                       cam.name AS camera_name,
                       sec.name AS sector_name,
                       creator.name AS created_by_name,
                       canceller.name AS cancelled_by_name
                ' . $this->fromSql();
    }

    private function selectListSql(): string
    {
        return $this->selectSql();
    }

    private function entryColumnsSql(): string
    {
        return 'e.id,
                e.cctv_shift_id,
                e.cctv_log_type_id,
                e.cctv_incident_type_id,
                e.cctv_technical_issue_type_id,
                e.incident_type_other,
                e.technical_issue_other,
                e.cctv_camera_id,
                e.cctv_equipment_id,
                e.camera_status_applied,
                e.sector_id,
                e.occurred_at,
                e.observations,
                e.police_arrived,
                e.police_arrival_time,
                e.coordination_notified,
                e.status,
                e.created_by,
                e.cancelled_by,
                e.created_at,
                e.updated_at,
                e.deleted_at';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function dayRangeBounds(string $date): array
    {
        return $this->rangeBounds($date, $date);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function rangeBounds(string $dateFrom, string $dateTo): array
    {
        return [
            $dateFrom . ' 00:00:00',
            date('Y-m-d H:i:s', strtotime($dateTo . ' +1 day')),
        ];
    }

    /**
     * @return array{
     *     total_entries: int,
     *     incidents: int,
     *     technical_issues: int,
     *     coordinations: int,
     *     police_communications: int
     * }
     */
    private function emptyRangeStats(): array
    {
        return [
            'total_entries' => 0,
            'incidents' => 0,
            'technical_issues' => 0,
            'coordinations' => 0,
            'police_communications' => 0,
        ];
    }

    private function fromSql(): string
    {
        return 'FROM cctv_log_entries e
                INNER JOIN cctv_shifts s ON s.id = e.cctv_shift_id
                INNER JOIN users operator ON operator.id = s.operator_id
                INNER JOIN cctv_log_types lt ON lt.id = e.cctv_log_type_id
                LEFT JOIN cctv_incident_types it ON it.id = e.cctv_incident_type_id
                LEFT JOIN cctv_technical_issue_types tit ON tit.id = e.cctv_technical_issue_type_id
                LEFT JOIN cctv_cameras cam ON cam.id = e.cctv_camera_id
                LEFT JOIN cctv_equipment eq ON eq.id = e.cctv_equipment_id
                LEFT JOIN sectors sec ON sec.id = e.sector_id
                INNER JOIN users creator ON creator.id = e.created_by
                LEFT JOIN users canceller ON canceller.id = e.cancelled_by';
    }
}
