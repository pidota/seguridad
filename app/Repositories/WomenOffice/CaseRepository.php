<?php

declare(strict_types=1);

namespace App\Repositories\WomenOffice;

use App\Services\WomenOffice\CaseStatus;
use Core\Database;

final class CaseRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function countRegisteredToday(?int $scopedUserId = null): int
    {
        return $this->countWhere(
            'c.deleted_at IS NULL
             AND DATE(c.reported_at) = CURDATE()',
            true,
            [],
            $scopedUserId
        );
    }

    public function countRegisteredThisMonth(?int $scopedUserId = null): int
    {
        return $this->countWhere(
            'c.deleted_at IS NULL
             AND YEAR(c.reported_at) = YEAR(CURDATE())
             AND MONTH(c.reported_at) = MONTH(CURDATE())',
            true,
            [],
            $scopedUserId
        );
    }

    public function countActive(?int $scopedUserId = null): int
    {
        $slugs = $this->quotedActiveSlugs();

        return $this->countWhere(
            'c.deleted_at IS NULL
             AND c.cancelled_at IS NULL
             AND s.slug IN (' . $slugs . ')',
            true,
            [],
            $scopedUserId
        );
    }

    public function countClosed(?int $scopedUserId = null): int
    {
        return $this->countWhere(
            'c.deleted_at IS NULL
             AND s.slug = :closed',
            true,
            ['closed' => CaseStatus::CLOSED],
            $scopedUserId
        );
    }

    public function countPendingFollowUps(?int $scopedUserId = null): int
    {
        return $this->countLatestFollowUp('f.next_follow_up_date IS NOT NULL', $scopedUserId);
    }

    public function countFollowUpsDueToday(?int $scopedUserId = null): int
    {
        return $this->countLatestFollowUp(
            'f.next_follow_up_date = CURDATE()',
            $scopedUserId
        );
    }

    public function countFollowUpsOverdue(?int $scopedUserId = null): int
    {
        return $this->countLatestFollowUp(
            'f.next_follow_up_date < CURDATE()',
            $scopedUserId
        );
    }

    public function countPendingReferrals(?int $scopedUserId = null): int
    {
        $params = [];
        $scope = $this->scopeClause($scopedUserId, $params);
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*)
             FROM women_case_referrals r
             INNER JOIN women_referral_statuses rs ON rs.id = r.referral_status_id
             INNER JOIN women_cases c ON c.id = r.case_id AND c.deleted_at IS NULL
             WHERE r.deleted_at IS NULL
               AND rs.slug = ' . $this->db()->quote('pending') . $scope
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function countUrgentActive(?int $scopedUserId = null): int
    {
        return $this->countWhere(
            'c.deleted_at IS NULL
             AND c.cancelled_at IS NULL
             AND c.priority = :priority
             AND s.slug IN (' . $this->quotedActiveSlugs() . ')',
            true,
            ['priority' => 'urgent'],
            $scopedUserId
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function followUpAgenda(array $filters, int $page, int $perPage, ?int $scopedUserId = null): array
    {
        $params = [];
        $where = '';
        $scope = $this->scopeClause($scopedUserId, $params);
        $where .= $scope;

        $due = trim((string) ($filters['due'] ?? 'pending'));
        if ($due === 'today') {
            $where .= ' AND lf.next_follow_up_date = CURDATE()';
        } elseif ($due === 'overdue') {
            $where .= ' AND lf.next_follow_up_date < CURDATE()';
        }

        $from = $this->listFromSql() . '
                INNER JOIN women_case_followups lf ON lf.id = (
                    SELECT id
                    FROM women_case_followups
                    WHERE case_id = c.id AND deleted_at IS NULL
                    ORDER BY follow_up_date DESC, follow_up_time DESC, id DESC
                    LIMIT 1
                )
                WHERE c.deleted_at IS NULL
                  AND c.cancelled_at IS NULL
                  AND s.slug IN (' . $this->quotedActiveSlugs() . ')
                  AND lf.requires_follow_up = 1
                  AND lf.next_follow_up_date IS NOT NULL
                  AND lf.deleted_at IS NULL' . $where;

        $count = $this->db()->prepare('SELECT COUNT(DISTINCT c.id) ' . $from);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;

        $sql = $this->listSelectSql() . ',
                       lf.next_follow_up_date AS agenda_next_follow_up_date ' . $from . '
                ORDER BY lf.next_follow_up_date ASC, FIELD(c.priority, \'urgent\', \'high\', \'medium\', \'low\'), c.id ASC
                LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            if (!empty($row['agenda_next_follow_up_date'])) {
                $row['next_follow_up_date'] = $row['agenda_next_follow_up_date'];
            }
        }
        unset($row);

        return ['data' => $rows, 'total' => $total];
    }

    private function countWhere(string $where, bool $joinStatus = true, array $params = [], ?int $scopedUserId = null): int
    {
        $from = 'FROM women_cases c';
        if ($joinStatus) {
            $from .= ' INNER JOIN women_case_statuses s ON s.id = c.case_status_id';
        }

        $where .= $this->scopeClause($scopedUserId, $params);

        $stmt = $this->db()->prepare('SELECT COUNT(*) ' . $from . ' WHERE ' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function countLatestFollowUp(string $condition, ?int $scopedUserId = null): int
    {
        $params = [];
        $scope = $this->scopeClause($scopedUserId, $params);
        $sql = 'SELECT COUNT(DISTINCT c.id)
                FROM women_cases c
                INNER JOIN women_case_statuses s ON s.id = c.case_status_id
                INNER JOIN women_case_followups f ON f.case_id = c.id AND f.deleted_at IS NULL
                WHERE c.deleted_at IS NULL
                  AND c.cancelled_at IS NULL
                  AND s.slug IN (' . $this->quotedActiveSlugs() . ')
                  AND f.requires_follow_up = 1
                  AND ' . $condition . '
                  AND f.id = (
                      SELECT lf.id
                      FROM women_case_followups lf
                      WHERE lf.case_id = c.id AND lf.deleted_at IS NULL
                      ORDER BY lf.follow_up_date DESC, lf.follow_up_time DESC, lf.id DESC
                      LIMIT 1
                  )' . $scope;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function scopeClause(?int $scopedUserId, array &$params): string
    {
        if ($scopedUserId === null || $scopedUserId < 1) {
            return '';
        }

        $params['scoped_user_id'] = $scopedUserId;

        return ' AND c.created_by = :scoped_user_id';
    }

    private function quotedActiveSlugs(): string
    {
        return implode(', ', array_map(
            fn (string $slug): string => $this->db()->quote($slug),
            CaseStatus::activeSlugs()
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $result = $this->paginate([], 1, 200);

        return $result['data'];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->filterSql($filters);
        $from = $this->listFromSql() . ' WHERE c.deleted_at IS NULL' . $where;

        $count = $this->db()->prepare('SELECT COUNT(DISTINCT c.id) ' . $from);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;

        $sql = $this->listSelectSql() . $from . '
                GROUP BY c.id
                ORDER BY c.reported_at DESC, c.id DESC
                LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function staffOptions(): array
    {
        $stmt = $this->db()->query(
            'SELECT DISTINCT u.id, u.name
             FROM users u
             INNER JOIN women_cases c ON c.created_by = u.id AND c.deleted_at IS NULL
             ORDER BY u.name ASC'
        );

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filterSql(array $filters): array
    {
        $where = '';
        $params = [];

        if (!empty($filters['scoped_user_id'])) {
            $where .= ' AND c.created_by = :scoped_user_id';
            $params['scoped_user_id'] = (int) $filters['scoped_user_id'];
        }

        $caseNumber = trim((string) ($filters['case_number'] ?? ''));
        if ($caseNumber !== '') {
            $where .= ' AND c.case_number LIKE :case_number';
            $params['case_number'] = '%' . $caseNumber . '%';
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $where .= ' AND DATE(c.reported_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $where .= ' AND DATE(c.reported_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $violenceTypeId = (int) ($filters['violence_type_id'] ?? 0);
        if ($violenceTypeId > 0) {
            $where .= ' AND EXISTS (
                SELECT 1 FROM women_case_violence_types cvt
                WHERE cvt.case_id = c.id
                  AND cvt.violence_type_id = :violence_type_id
            )';
            $params['violence_type_id'] = $violenceTypeId;
        }

        $sectorId = (int) ($filters['sector_id'] ?? 0);
        if ($sectorId > 0) {
            $where .= ' AND p.sector_id = :sector_id';
            $params['sector_id'] = $sectorId;
        }

        $ageRange = trim((string) ($filters['age_range'] ?? ''));
        if ($ageRange !== '') {
            $where .= ' AND p.birth_date IS NOT NULL AND ' . $this->ageRangeSql($ageRange);
        }

        $statusId = (int) ($filters['case_status_id'] ?? 0);
        if ($statusId > 0) {
            $where .= ' AND c.case_status_id = :case_status_id';
            $params['case_status_id'] = $statusId;
        }

        $priority = trim((string) ($filters['priority'] ?? ''));
        if (in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $where .= ' AND c.priority = :priority';
            $params['priority'] = $priority;
        }

        $createdBy = (int) ($filters['created_by'] ?? 0);
        if ($createdBy > 0 && empty($filters['scoped_user_id'])) {
            $where .= ' AND c.created_by = :created_by';
            $params['created_by'] = $createdBy;
        }

        if (($filters['pending_follow_up'] ?? '') === 'yes') {
            $where .= ' AND EXISTS (
                SELECT 1 FROM women_case_followups pf
                WHERE pf.case_id = c.id
                  AND pf.deleted_at IS NULL
                  AND pf.requires_follow_up = 1
                  AND pf.next_follow_up_date IS NOT NULL
                  AND pf.id = (
                      SELECT lf.id
                      FROM women_case_followups lf
                      WHERE lf.case_id = c.id AND lf.deleted_at IS NULL
                      ORDER BY lf.follow_up_date DESC, lf.follow_up_time DESC, lf.id DESC
                      LIMIT 1
                  )
            )';
        }

        $formalReport = trim((string) ($filters['formal_report'] ?? ''));
        if (in_array($formalReport, ['yes', 'no', 'unknown'], true)) {
            $where .= ' AND c.has_formal_current_report = :formal_report';
            $params['formal_report'] = $formalReport;
        }

        if (($filters['referral_pending'] ?? '') === 'yes') {
            $where .= ' AND EXISTS (
                SELECT 1 FROM women_case_referrals rr
                INNER JOIN women_referral_statuses rs ON rs.id = rr.referral_status_id
                WHERE rr.case_id = c.id
                  AND rr.deleted_at IS NULL
                  AND rs.slug = ' . $this->db()->quote('pending') . '
            )';
        }

        $referralInstitutionId = (int) ($filters['referral_institution_id'] ?? 0);
        if ($referralInstitutionId > 0) {
            $where .= ' AND EXISTS (
                SELECT 1 FROM women_case_referrals rr
                WHERE rr.case_id = c.id
                  AND rr.deleted_at IS NULL
                  AND rr.institution_id = :referral_institution_id
            )';
            $params['referral_institution_id'] = $referralInstitutionId;
        }

        return [$where, $params];
    }

    private function ageRangeSql(string $range): string
    {
        $age = 'TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE())';

        return match ($range) {
            'under_18' => $age . ' < 18',
            '18_29' => $age . ' BETWEEN 18 AND 29',
            '30_39' => $age . ' BETWEEN 30 AND 39',
            '40_49' => $age . ' BETWEEN 40 AND 49',
            '50_59' => $age . ' BETWEEN 50 AND 59',
            '60_plus' => $age . ' >= 60',
            default => '1 = 1',
        };
    }

    private function listFromSql(): string
    {
        return 'FROM women_cases c
                INNER JOIN women_case_statuses s ON s.id = c.case_status_id
                LEFT JOIN women_report_channels rc ON rc.id = c.report_channel_id
                LEFT JOIN sectors isec ON isec.id = c.incident_sector_id
                LEFT JOIN women_relationship_types rt ON rt.id = c.relationship_type_id
                INNER JOIN women_people p ON p.id = c.affected_person_id
                LEFT JOIN sectors psec ON psec.id = p.sector_id
                LEFT JOIN users u ON u.id = c.created_by
                LEFT JOIN users pu ON pu.id = c.priority_assigned_by';
    }

    private function listSelectSql(): string
    {
        return 'SELECT c.*,
                       s.slug AS case_status_slug,
                       s.name AS case_status_name,
                       rc.slug AS report_channel_slug,
                       rc.name AS report_channel_name,
                       isec.name AS incident_sector_name,
                       rt.slug AS relationship_type_slug,
                       rt.name AS relationship_type_name,
                       p.first_names, p.paternal_surname, p.maternal_surname, p.rut, p.birth_date,
                       p.sector_id AS person_sector_id,
                       psec.name AS person_sector_name,
                       u.name AS created_by_name,
                       pu.name AS priority_assigned_by_name,
                       (
                           SELECT GROUP_CONCAT(vt.name ORDER BY vt.sort_order SEPARATOR \', \')
                           FROM women_case_violence_types cvt
                           INNER JOIN women_violence_types vt ON vt.id = cvt.violence_type_id
                           WHERE cvt.case_id = c.id
                       ) AS violence_types_label,
                       (
                           SELECT lf.next_follow_up_date
                           FROM women_case_followups lf
                           WHERE lf.case_id = c.id
                             AND lf.deleted_at IS NULL
                             AND lf.requires_follow_up = 1
                             AND lf.next_follow_up_date IS NOT NULL
                           ORDER BY lf.follow_up_date DESC, lf.follow_up_time DESC, lf.id DESC
                           LIMIT 1
                       ) AS next_follow_up_date ';
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

    public function create(array $data): int
    {
        $sql = 'INSERT INTO women_cases (
                    case_number, reported_at, report_channel_id, report_channel_other,
                    incident_date_precision, affected_person_id, case_status_id, created_by
                ) VALUES (
                    :case_number, :reported_at, :report_channel_id, :report_channel_other,
                    :incident_date_precision, :affected_person_id, :case_status_id, :created_by
                )';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function updateIncidentFacts(int $id, array $data): void
    {
        $sql = 'UPDATE women_cases SET
                    incident_date_precision = :incident_date_precision,
                    incident_date = :incident_date,
                    incident_time = :incident_time,
                    incident_time_notes = :incident_time_notes,
                    incident_place = :incident_place,
                    incident_sector_id = :incident_sector_id,
                    incident_address = :incident_address,
                    description = :description,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_merge($data, ['id' => $id]));
    }

    public function updateRelationship(int $id, array $data): void
    {
        $sql = 'UPDATE women_cases SET
                    relationship_type_id = :relationship_type_id,
                    relationship_other = :relationship_other,
                    current_relationship = :current_relationship,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_merge($data, ['id' => $id]));
    }

    public function updateBackground(int $id, array $data): void
    {
        $sql = 'UPDATE women_cases SET
                    is_first_occurrence = :is_first_occurrence,
                    occurrence_frequency = :occurrence_frequency,
                    occurring_since = :occurring_since,
                    occurrence_notes = :occurrence_notes,
                    has_previous_reports = :has_previous_reports,
                    has_formal_current_report = :has_formal_current_report,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_merge($data, ['id' => $id]));
    }

    public function updateRiskPriority(int $id, array $data): void
    {
        $sql = 'UPDATE women_cases SET
                    priority = :priority,
                    priority_assigned_by = :priority_assigned_by,
                    priority_assigned_at = :priority_assigned_at,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_merge($data, ['id' => $id]));
    }

    public function updateSupport(int $id, array $data): void
    {
        $sql = 'UPDATE women_cases SET
                    has_protective_measures = :has_protective_measures,
                    has_linked_minors = :has_linked_minors,
                    has_dependents = :has_dependents,
                    dependents_count = :dependents_count,
                    dependents_notes = :dependents_notes,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_merge($data, ['id' => $id]));
    }

    public function updateCaseStatus(int $id, int $statusId): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE women_cases
             SET case_status_id = :status_id, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'status_id' => $statusId]);
    }

    public function closeCase(int $id, array $data): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE women_cases
             SET case_status_id = :status_id,
                 closed_at = :closed_at,
                 closed_by = :closed_by,
                 closure_notes = :closure_notes,
                 updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL AND cancelled_at IS NULL'
        );
        $stmt->execute(array_merge($data, ['id' => $id]));
    }

    public function cancelCase(int $id, array $data): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE women_cases
             SET case_status_id = :status_id,
                 cancelled_at = :cancelled_at,
                 cancelled_by = :cancelled_by,
                 cancellation_reason = :cancellation_reason,
                 updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL AND closed_at IS NULL'
        );
        $stmt->execute(array_merge($data, ['id' => $id]));
    }

    private function selectSql(): string
    {
        return 'SELECT c.*,
                       s.slug AS case_status_slug,
                       s.name AS case_status_name,
                       rc.slug AS report_channel_slug,
                       rc.name AS report_channel_name,
                       isec.name AS incident_sector_name,
                       rt.slug AS relationship_type_slug,
                       rt.name AS relationship_type_name,
                       p.first_names, p.paternal_surname, p.maternal_surname, p.rut, p.birth_date,
                       u.name AS created_by_name,
                       pu.name AS priority_assigned_by_name
                FROM women_cases c
                INNER JOIN women_case_statuses s ON s.id = c.case_status_id
                LEFT JOIN women_report_channels rc ON rc.id = c.report_channel_id
                LEFT JOIN sectors isec ON isec.id = c.incident_sector_id
                LEFT JOIN women_relationship_types rt ON rt.id = c.relationship_type_id
                INNER JOIN women_people p ON p.id = c.affected_person_id
                LEFT JOIN users u ON u.id = c.created_by
                LEFT JOIN users pu ON pu.id = c.priority_assigned_by';
    }
}
