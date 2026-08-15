<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

use Core\Database;

final class RecordingRequestRepository
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
            'INSERT INTO cctv_recording_requests (
                office_visit_id, request_number, incident_date, time_from, time_to,
                sector_id, cctv_camera_id, incident_description, has_complaint,
                complaint_institution, complaint_number, complaint_date, complaint_observations,
                complaint_document_path, complaint_document_original_name,
                complaint_document_mime, complaint_document_size, status, received_by
             ) VALUES (
                :office_visit_id, :request_number, :incident_date, :time_from, :time_to,
                :sector_id, :cctv_camera_id, :incident_description, :has_complaint,
                :complaint_institution, :complaint_number, :complaint_date, :complaint_observations,
                :complaint_document_path, :complaint_document_original_name,
                :complaint_document_mime, :complaint_document_size, :status, :received_by
             )'
        );
        $stmt->execute([
            'office_visit_id' => $data['office_visit_id'],
            'request_number' => $data['request_number'],
            'incident_date' => $data['incident_date'],
            'time_from' => $data['time_from'],
            'time_to' => $data['time_to'],
            'sector_id' => $data['sector_id'] ?? null,
            'cctv_camera_id' => $data['cctv_camera_id'] ?? null,
            'incident_description' => $data['incident_description'],
            'has_complaint' => (int) ($data['has_complaint'] ?? 0),
            'complaint_institution' => $data['complaint_institution'] ?? null,
            'complaint_number' => $data['complaint_number'] ?? null,
            'complaint_date' => $data['complaint_date'] ?? null,
            'complaint_observations' => $data['complaint_observations'] ?? null,
            'complaint_document_path' => $data['complaint_document_path'] ?? null,
            'complaint_document_original_name' => $data['complaint_document_original_name'] ?? null,
            'complaint_document_mime' => $data['complaint_document_mime'] ?? null,
            'complaint_document_size' => $data['complaint_document_size'] ?? null,
            'status' => $data['status'],
            'received_by' => $data['received_by'] ?? null,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare($this->selectSql() . ' WHERE rr.id = :id AND rr.deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByVisitId(int $visitId): ?array
    {
        $stmt = $this->db()->prepare(
            $this->selectSql() . ' WHERE rr.office_visit_id = :visit_id AND rr.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['visit_id' => $visitId]);
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
             FROM cctv_recording_requests rr
             INNER JOIN cctv_office_visits v ON v.id = rr.office_visit_id AND v.deleted_at IS NULL
             WHERE rr.deleted_at IS NULL' . $where
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = $this->selectSql() . '
                WHERE rr.deleted_at IS NULL' . $where . '
                ORDER BY rr.created_at DESC, rr.id DESC
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
        $fields = [];
        $params = ['id' => $id];

        foreach ([
            'incident_date', 'time_from', 'time_to', 'sector_id', 'cctv_camera_id',
            'incident_description', 'has_complaint', 'complaint_institution', 'complaint_number',
            'complaint_date', 'complaint_observations', 'complaint_document_path',
            'complaint_document_original_name', 'complaint_document_mime', 'complaint_document_size',
            'status', 'received_by', 'complaint_verified_by', 'complaint_verified_at',
            'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at', 'assigned_to',
            'recording_preserved', 'preserved_by', 'preserved_at',
            'rejection_reason', 'rejection_notes',
            'not_found_reason', 'not_found_notes', 'not_found_cameras_reviewed',
            'retention_until', 'cancelled_by', 'cancelled_at', 'cancellation_reason',
            'public_notes', 'internal_notes',
            'delivered_by', 'delivered_at', 'delivery_notes',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if ($fields === []) {
            return;
        }

        $fields[] = 'updated_at = NOW()';
        $stmt = $this->db()->prepare(
            'UPDATE cctv_recording_requests SET ' . implode(', ', $fields) . ' WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute($params);
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*) FROM cctv_recording_requests WHERE deleted_at IS NULL AND status = :status'
        );
        $stmt->execute(['status' => $status]);

        return (int) $stmt->fetchColumn();
    }

    public function countToday(string $date): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*)
             FROM cctv_recording_requests rr
             INNER JOIN cctv_office_visits v ON v.id = rr.office_visit_id AND v.deleted_at IS NULL
             WHERE rr.deleted_at IS NULL AND v.visit_date = :date'
        );
        $stmt->execute(['date' => $date]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingAlerts(int $limit = 8): array
    {
        $limit = max(1, min($limit, 20));
        $stmt = $this->db()->query(
            'SELECT rr.id, rr.request_number, rr.status, v.visit_date, v.arrival_time
             FROM cctv_recording_requests rr
             INNER JOIN cctv_office_visits v ON v.id = rr.office_visit_id AND v.deleted_at IS NULL
             WHERE rr.deleted_at IS NULL
               AND rr.status IN (\'pending_review\', \'incomplete_documentation\', \'approved\', \'recording_found\')
             ORDER BY FIELD(rr.status, \'incomplete_documentation\', \'pending_review\', \'recording_found\', \'approved\'), rr.created_at ASC
             LIMIT ' . $limit
        );

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByRut(string $rut, int $limit = 10): array
    {
        $stmt = $this->db()->prepare(
            $this->selectSql() . '
             WHERE rr.deleted_at IS NULL AND v.requester_rut = :rut
             ORDER BY rr.created_at DESC
             LIMIT ' . max(1, min($limit, 50))
        );
        $stmt->execute(['rut' => $rut]);

        return $stmt->fetchAll() ?: [];
    }

    private function selectSql(): string
    {
        return 'SELECT rr.*,
                       v.visit_date, v.arrival_time, v.requester_name, v.requester_rut,
                       v.requester_phone, v.requester_email, v.organization, v.reason,
                       v.cctv_shift_id, v.created_by AS visit_created_by,
                       u.name AS operator_name,
                       recv.name AS received_by_name,
                       verifier.name AS complaint_verified_by_name,
                       reviewer.name AS reviewed_by_name,
                       approver.name AS approved_by_name,
                       assignee.name AS assigned_to_name,
                       sec.name AS sector_name,
                       cam.name AS camera_name
                FROM cctv_recording_requests rr
                INNER JOIN cctv_office_visits v ON v.id = rr.office_visit_id AND v.deleted_at IS NULL
                INNER JOIN users u ON u.id = v.created_by
                LEFT JOIN users recv ON recv.id = rr.received_by
                LEFT JOIN users verifier ON verifier.id = rr.complaint_verified_by
                LEFT JOIN users reviewer ON reviewer.id = rr.reviewed_by
                LEFT JOIN users approver ON approver.id = rr.approved_by
                LEFT JOIN users assignee ON assignee.id = rr.assigned_to
                LEFT JOIN sectors sec ON sec.id = rr.sector_id AND sec.deleted_at IS NULL
                LEFT JOIN cctv_cameras cam ON cam.id = rr.cctv_camera_id AND cam.deleted_at IS NULL';
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildFilters(array $filters): array
    {
        $where = '';
        $params = [];

        if (!empty($filters['status'])) {
            $where .= ' AND rr.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where .= ' AND v.visit_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where .= ' AND v.visit_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['requester_rut'])) {
            $where .= ' AND v.requester_rut = :requester_rut';
            $params['requester_rut'] = $filters['requester_rut'];
        }

        if (!empty($filters['requester_name'])) {
            $where .= ' AND v.requester_name LIKE :requester_name';
            $params['requester_name'] = '%' . $filters['requester_name'] . '%';
        }

        if (!empty($filters['sector_id'])) {
            $where .= ' AND rr.sector_id = :sector_id';
            $params['sector_id'] = (int) $filters['sector_id'];
        }

        if (!empty($filters['created_by'])) {
            $where .= ' AND v.created_by = :created_by';
            $params['created_by'] = (int) $filters['created_by'];
        }

        if (!empty($filters['complaint_number'])) {
            $where .= ' AND rr.complaint_number LIKE :complaint_number';
            $params['complaint_number'] = '%' . $filters['complaint_number'] . '%';
        }

        if (!empty($filters['request_number'])) {
            $where .= ' AND rr.request_number LIKE :request_number';
            $params['request_number'] = '%' . $filters['request_number'] . '%';
        }

        if (!empty($filters['q'])) {
            $where .= ' AND (
                rr.request_number LIKE :q OR
                v.requester_name LIKE :q OR
                v.requester_rut LIKE :q OR
                rr.complaint_number LIKE :q
            )';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        return [$where, $params];
    }

    public function countDeliveredToday(string $date): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*)
             FROM cctv_recording_requests
             WHERE deleted_at IS NULL
               AND status = :status
               AND DATE(delivered_at) = :date'
        );
        $stmt->execute(['status' => 'delivered', 'date' => $date]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stalePending(int $days, int $limit = 10): array
    {
        $days = max(1, $days);
        $limit = max(1, min($limit, 50));
        $stmt = $this->db()->prepare(
            'SELECT rr.id, rr.request_number, rr.status, rr.created_at, v.visit_date,
                    assignee.name AS assigned_to_name
             FROM cctv_recording_requests rr
             INNER JOIN cctv_office_visits v ON v.id = rr.office_visit_id AND v.deleted_at IS NULL
             LEFT JOIN users assignee ON assignee.id = rr.assigned_to
             WHERE rr.deleted_at IS NULL
               AND rr.status IN (\'pending_complaint\', \'incomplete_documentation\', \'pending_review\', \'under_review\', \'recording_found\', \'approved\')
               AND DATEDIFF(CURDATE(), DATE(rr.created_at)) >= :days
             ORDER BY rr.created_at ASC
             LIMIT ' . $limit
        );
        $stmt->execute(['days' => $days]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function supervisionList(int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));
        $stmt = $this->db()->query(
            'SELECT rr.id, rr.request_number, rr.status, rr.created_at, v.visit_date,
                    assignee.name AS assigned_to_name,
                    DATEDIFF(CURDATE(), DATE(rr.created_at)) AS pending_days
             FROM cctv_recording_requests rr
             INNER JOIN cctv_office_visits v ON v.id = rr.office_visit_id AND v.deleted_at IS NULL
             LEFT JOIN users assignee ON assignee.id = rr.assigned_to
             WHERE rr.deleted_at IS NULL
               AND rr.status NOT IN (\'delivered\', \'rejected\', \'cancelled\')
             ORDER BY rr.created_at ASC
             LIMIT ' . $limit
        );

        return $stmt->fetchAll() ?: [];
    }
}
