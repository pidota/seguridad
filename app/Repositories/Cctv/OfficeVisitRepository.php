<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

use Core\Database;

final class OfficeVisitRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO cctv_office_visits (
                cctv_shift_id, visitor_type, visit_reason, visit_reason_other,
                visit_date, arrival_time, departure_time,
                requester_name, requester_rut, requester_phone, requester_email,
                organization, authorized_by, reason, internal_notes,
                recording_requested, created_by
             ) VALUES (
                :cctv_shift_id, :visitor_type, :visit_reason, :visit_reason_other,
                :visit_date, :arrival_time, :departure_time,
                :requester_name, :requester_rut, :requester_phone, :requester_email,
                :organization, :authorized_by, :reason, :internal_notes,
                :recording_requested, :created_by
             )'
        );
        $stmt->execute([
            'cctv_shift_id' => $data['cctv_shift_id'],
            'visitor_type' => $data['visitor_type'],
            'visit_reason' => $data['visit_reason'] ?? null,
            'visit_reason_other' => $data['visit_reason_other'] ?? null,
            'visit_date' => $data['visit_date'],
            'arrival_time' => $data['arrival_time'],
            'departure_time' => $data['departure_time'] ?? null,
            'requester_name' => $data['requester_name'],
            'requester_rut' => $data['requester_rut'] ?? null,
            'requester_phone' => $data['requester_phone'] ?? null,
            'requester_email' => $data['requester_email'] ?? null,
            'organization' => $data['organization'] ?? null,
            'authorized_by' => $data['authorized_by'] ?? null,
            'reason' => $data['reason'],
            'internal_notes' => $data['internal_notes'] ?? null,
            'recording_requested' => (int) ($data['recording_requested'] ?? 0),
            'created_by' => $data['created_by'],
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT v.*,
                    u.name AS operator_name,
                    s.started_at AS shift_started_at
             FROM cctv_office_visits v
             INNER JOIN users u ON u.id = v.created_by
             INNER JOIN cctv_shifts s ON s.id = v.cctv_shift_id
             WHERE v.id = :id AND v.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;
        [$where, $params] = $this->buildFilters($filters);

        $countStmt = $this->db()->prepare(
            'SELECT COUNT(*)
             FROM cctv_office_visits v
             WHERE v.deleted_at IS NULL' . $where
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = 'SELECT v.*,
                       u.name AS operator_name,
                       rr.request_number,
                       rr.status AS recording_status
                FROM cctv_office_visits v
                INNER JOIN users u ON u.id = v.created_by
                LEFT JOIN cctv_recording_requests rr ON rr.office_visit_id = v.id AND rr.deleted_at IS NULL
                WHERE v.deleted_at IS NULL' . $where . '
                ORDER BY v.visit_date DESC, v.arrival_time DESC, v.id DESC
                LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return [
            'data' => $stmt->fetchAll() ?: [],
            'total' => $total,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE cctv_office_visits
             SET departure_time = :departure_time,
                 requester_name = :requester_name,
                 requester_rut = :requester_rut,
                 requester_phone = :requester_phone,
                 requester_email = :requester_email,
                 organization = :organization,
                 reason = :reason,
                 updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'id' => $id,
            'departure_time' => $data['departure_time'] ?? null,
            'requester_name' => $data['requester_name'],
            'requester_rut' => $data['requester_rut'] ?? null,
            'requester_phone' => $data['requester_phone'] ?? null,
            'requester_email' => $data['requester_email'] ?? null,
            'organization' => $data['organization'] ?? null,
            'reason' => $data['reason'],
        ]);
    }

    public function countToday(string $date): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*)
             FROM cctv_office_visits
             WHERE deleted_at IS NULL AND visit_date = :date'
        );
        $stmt->execute(['date' => $date]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function currentInOffice(?string $date = null): array
    {
        $date ??= date('Y-m-d');
        $stmt = $this->db()->prepare(
            'SELECT v.id, v.requester_name, v.arrival_time, v.visitor_type, v.visit_reason
             FROM cctv_office_visits v
             WHERE v.deleted_at IS NULL
               AND v.visit_date = :date
               AND v.departure_time IS NULL
               AND v.recording_requested = 0
             ORDER BY v.arrival_time ASC'
        );
        $stmt->execute(['date' => $date]);

        return $stmt->fetchAll() ?: [];
    }

    public function registerDeparture(int $id, string $departureTime): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE cctv_office_visits
             SET departure_time = :departure_time, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL AND departure_time IS NULL'
        );
        $stmt->execute(['id' => $id, 'departure_time' => $departureTime]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByRut(string $rut, int $limit = 10): array
    {
        $stmt = $this->db()->prepare(
            'SELECT v.*, rr.request_number, rr.status AS recording_status
             FROM cctv_office_visits v
             LEFT JOIN cctv_recording_requests rr ON rr.office_visit_id = v.id AND rr.deleted_at IS NULL
             WHERE v.deleted_at IS NULL AND v.requester_rut = :rut
             ORDER BY v.visit_date DESC, v.id DESC
             LIMIT ' . max(1, min($limit, 50))
        );
        $stmt->execute(['rut' => $rut]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildFilters(array $filters): array
    {
        $where = '';
        $params = [];

        if (!empty($filters['date_from'])) {
            $where .= ' AND v.visit_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where .= ' AND v.visit_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['visitor_type'])) {
            $where .= ' AND v.visitor_type = :visitor_type';
            $params['visitor_type'] = $filters['visitor_type'];
        }

        if (!empty($filters['requester_rut'])) {
            $where .= ' AND v.requester_rut = :requester_rut';
            $params['requester_rut'] = $filters['requester_rut'];
        }

        if (!empty($filters['requester_name'])) {
            $where .= ' AND v.requester_name LIKE :requester_name';
            $params['requester_name'] = '%' . $filters['requester_name'] . '%';
        }

        if (!empty($filters['created_by'])) {
            $where .= ' AND v.created_by = :created_by';
            $params['created_by'] = (int) $filters['created_by'];
        }

        if (($filters['tab'] ?? '') === 'recordings') {
            $where .= ' AND v.recording_requested = 1';
        }

        return [$where, $params];
    }
}
