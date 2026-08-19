<?php

declare(strict_types=1);

namespace App\Repositories\WomenOffice;

use Core\Database;

final class StatsRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function totalCases(string $dateFrom, string $dateTo): int
    {
        $params = [];
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*)
             FROM women_cases c
             WHERE c.deleted_at IS NULL
               AND ' . $this->caseDateClause($dateFrom, $dateTo, $params)
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array{period: string, total: int}>
     */
    public function casesByMonth(string $dateFrom, string $dateTo): array
    {
        $params = [];
        $stmt = $this->db()->prepare(
            'SELECT DATE_FORMAT(c.reported_at, \'%Y-%m\') AS period, COUNT(*) AS total
             FROM women_cases c
             WHERE c.deleted_at IS NULL
               AND ' . $this->caseDateClause($dateFrom, $dateTo, $params) . '
             GROUP BY period
             ORDER BY period ASC'
        );
        $stmt->execute($params);

        return array_map(
            static fn (array $row): array => [
                'period' => (string) ($row['period'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ],
            $stmt->fetchAll() ?: []
        );
    }

    /**
     * @return list<array{slug: string, label: string, total: int}>
     */
    public function casesByViolenceType(string $dateFrom, string $dateTo): array
    {
        $params = [];
        $stmt = $this->db()->prepare(
            'SELECT vt.slug, vt.name AS label, COUNT(DISTINCT c.id) AS total
             FROM women_violence_types vt
             LEFT JOIN women_case_violence_types cvt ON cvt.violence_type_id = vt.id
             LEFT JOIN women_cases c ON c.id = cvt.case_id
                AND c.deleted_at IS NULL
                AND ' . $this->caseDateClause($dateFrom, $dateTo, $params) . '
             WHERE vt.is_active = 1
             GROUP BY vt.id, vt.slug, vt.name, vt.sort_order
             ORDER BY vt.sort_order ASC, vt.name ASC'
        );
        $stmt->execute($params);

        return array_map(
            static fn (array $row): array => [
                'slug' => (string) ($row['slug'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ],
            $stmt->fetchAll() ?: []
        );
    }

    /**
     * @return list<array{bucket: string, total: int}>
     */
    public function casesByAgeRange(string $dateFrom, string $dateTo): array
    {
        $params = [];
        $age = 'TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE())';
        $stmt = $this->db()->prepare(
            'SELECT
                CASE
                    WHEN p.birth_date IS NULL THEN \'unknown\'
                    WHEN ' . $age . ' < 18 THEN \'under_18\'
                    WHEN ' . $age . ' BETWEEN 18 AND 29 THEN \'18_29\'
                    WHEN ' . $age . ' BETWEEN 30 AND 39 THEN \'30_39\'
                    WHEN ' . $age . ' BETWEEN 40 AND 49 THEN \'40_49\'
                    WHEN ' . $age . ' BETWEEN 50 AND 59 THEN \'50_59\'
                    ELSE \'60_plus\'
                END AS bucket,
                COUNT(*) AS total
             FROM women_cases c
             INNER JOIN women_people p ON p.id = c.affected_person_id
             WHERE c.deleted_at IS NULL
               AND ' . $this->caseDateClause($dateFrom, $dateTo, $params) . '
             GROUP BY bucket
             ORDER BY bucket ASC'
        );
        $stmt->execute($params);

        return array_map(
            static fn (array $row): array => [
                'bucket' => (string) ($row['bucket'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ],
            $stmt->fetchAll() ?: []
        );
    }

    /**
     * @return list<array{label: string, total: int}>
     */
    public function casesBySector(string $dateFrom, string $dateTo): array
    {
        $params = [];
        $stmt = $this->db()->prepare(
            'SELECT COALESCE(sec.name, \'Sin sector\') AS label, COUNT(*) AS total
             FROM women_cases c
             INNER JOIN women_people p ON p.id = c.affected_person_id
             LEFT JOIN sectors sec ON sec.id = p.sector_id
             WHERE c.deleted_at IS NULL
               AND ' . $this->caseDateClause($dateFrom, $dateTo, $params) . '
             GROUP BY sec.id, sec.name
             ORDER BY total DESC, label ASC'
        );
        $stmt->execute($params);

        return array_map(
            static fn (array $row): array => [
                'label' => (string) ($row['label'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ],
            $stmt->fetchAll() ?: []
        );
    }

    /**
     * @return list<array{label: string, total: int}>
     */
    public function casesByRelationship(string $dateFrom, string $dateTo): array
    {
        $params = [];
        $stmt = $this->db()->prepare(
            'SELECT COALESCE(rt.name, \'Sin relación registrada\') AS label, COUNT(*) AS total
             FROM women_cases c
             LEFT JOIN women_relationship_types rt ON rt.id = c.relationship_type_id
             WHERE c.deleted_at IS NULL
               AND ' . $this->caseDateClause($dateFrom, $dateTo, $params) . '
             GROUP BY rt.id, rt.name, rt.sort_order
             ORDER BY rt.sort_order ASC, label ASC'
        );
        $stmt->execute($params);

        return array_map(
            static fn (array $row): array => [
                'label' => (string) ($row['label'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ],
            $stmt->fetchAll() ?: []
        );
    }

    /**
     * @return list<array{value: string, total: int}>
     */
    public function casesByFormalReport(string $dateFrom, string $dateTo): array
    {
        $params = [];
        $stmt = $this->db()->prepare(
            'SELECT COALESCE(c.has_formal_current_report, \'\') AS value, COUNT(*) AS total
             FROM women_cases c
             WHERE c.deleted_at IS NULL
               AND ' . $this->caseDateClause($dateFrom, $dateTo, $params) . '
             GROUP BY value
             ORDER BY value ASC'
        );
        $stmt->execute($params);

        return array_map(
            static fn (array $row): array => [
                'value' => (string) ($row['value'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ],
            $stmt->fetchAll() ?: []
        );
    }

    /**
     * @return list<array{value: string, total: int}>
     */
    public function casesByPriority(string $dateFrom, string $dateTo): array
    {
        $params = [];
        $stmt = $this->db()->prepare(
            'SELECT COALESCE(c.priority, \'\') AS value, COUNT(*) AS total
             FROM women_cases c
             WHERE c.deleted_at IS NULL
               AND ' . $this->caseDateClause($dateFrom, $dateTo, $params) . '
             GROUP BY value
             ORDER BY FIELD(value, \'urgent\', \'high\', \'medium\', \'low\', \'\')'
        );
        $stmt->execute($params);

        return array_map(
            static fn (array $row): array => [
                'value' => (string) ($row['value'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ],
            $stmt->fetchAll() ?: []
        );
    }

    /**
     * @return list<array{label: string, total: int}>
     */
    public function casesByStatus(string $dateFrom, string $dateTo): array
    {
        $params = [];
        $stmt = $this->db()->prepare(
            'SELECT s.name AS label, COUNT(*) AS total
             FROM women_cases c
             INNER JOIN women_case_statuses s ON s.id = c.case_status_id
             WHERE c.deleted_at IS NULL
               AND ' . $this->caseDateClause($dateFrom, $dateTo, $params) . '
             GROUP BY s.id, s.name, s.sort_order
             ORDER BY s.sort_order ASC, s.name ASC'
        );
        $stmt->execute($params);

        return array_map(
            static fn (array $row): array => [
                'label' => (string) ($row['label'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ],
            $stmt->fetchAll() ?: []
        );
    }

    public function referralsTotal(string $dateFrom, string $dateTo): int
    {
        $params = [];
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*)
             FROM women_case_referrals r
             INNER JOIN women_cases c ON c.id = r.case_id AND c.deleted_at IS NULL
             WHERE r.deleted_at IS NULL
               AND ' . $this->referralDateClause($dateFrom, $dateTo, $params)
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array{label: string, total: int}>
     */
    public function referralsByInstitution(string $dateFrom, string $dateTo): array
    {
        $params = [];
        $stmt = $this->db()->prepare(
            'SELECT COALESCE(i.name, \'Sin institución\') AS label, COUNT(*) AS total
             FROM women_case_referrals r
             INNER JOIN women_cases c ON c.id = r.case_id AND c.deleted_at IS NULL
             LEFT JOIN women_referral_institutions i ON i.id = r.institution_id
             WHERE r.deleted_at IS NULL
               AND ' . $this->referralDateClause($dateFrom, $dateTo, $params) . '
             GROUP BY i.id, i.name, i.sort_order
             ORDER BY i.sort_order ASC, label ASC'
        );
        $stmt->execute($params);

        return array_map(
            static fn (array $row): array => [
                'label' => (string) ($row['label'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ],
            $stmt->fetchAll() ?: []
        );
    }

    /**
     * @return list<array{label: string, total: int}>
     */
    public function referralsByStatus(string $dateFrom, string $dateTo): array
    {
        $params = [];
        $stmt = $this->db()->prepare(
            'SELECT rs.name AS label, COUNT(*) AS total
             FROM women_case_referrals r
             INNER JOIN women_cases c ON c.id = r.case_id AND c.deleted_at IS NULL
             INNER JOIN women_referral_statuses rs ON rs.id = r.referral_status_id
             WHERE r.deleted_at IS NULL
               AND ' . $this->referralDateClause($dateFrom, $dateTo, $params) . '
             GROUP BY rs.id, rs.name, rs.sort_order
             ORDER BY rs.sort_order ASC, rs.name ASC'
        );
        $stmt->execute($params);

        return array_map(
            static fn (array $row): array => [
                'label' => (string) ($row['label'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ],
            $stmt->fetchAll() ?: []
        );
    }

    public function followUpsPerformed(string $dateFrom, string $dateTo): int
    {
        $params = [];
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*)
             FROM women_case_followups f
             INNER JOIN women_cases c ON c.id = f.case_id AND c.deleted_at IS NULL
             WHERE f.deleted_at IS NULL
               AND ' . $this->followUpDateClause($dateFrom, $dateTo, $params)
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function pendingFollowUpCases(string $dateFrom, string $dateTo): int
    {
        $params = [];
        $stmt = $this->db()->prepare(
            'SELECT COUNT(DISTINCT c.id)
             FROM women_cases c
             INNER JOIN women_case_statuses s ON s.id = c.case_status_id
             INNER JOIN women_case_followups lf ON lf.case_id = c.id AND lf.deleted_at IS NULL
             WHERE c.deleted_at IS NULL
               AND c.cancelled_at IS NULL
               AND ' . $this->caseDateClause($dateFrom, $dateTo, $params) . '
               AND lf.id = (
                   SELECT f.id
                   FROM women_case_followups f
                   WHERE f.case_id = c.id
                     AND f.deleted_at IS NULL
                   ORDER BY f.follow_up_date DESC, f.follow_up_time DESC, f.id DESC
                   LIMIT 1
               )
               AND lf.requires_follow_up = 1
               AND lf.next_follow_up_date IS NOT NULL'
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function overdueFollowUpCases(string $dateFrom, string $dateTo): int
    {
        $params = [];
        $stmt = $this->db()->prepare(
            'SELECT COUNT(DISTINCT c.id)
             FROM women_cases c
             INNER JOIN women_case_statuses s ON s.id = c.case_status_id
             INNER JOIN women_case_followups lf ON lf.case_id = c.id AND lf.deleted_at IS NULL
             WHERE c.deleted_at IS NULL
               AND c.cancelled_at IS NULL
               AND ' . $this->caseDateClause($dateFrom, $dateTo, $params) . '
               AND lf.id = (
                   SELECT f.id
                   FROM women_case_followups f
                   WHERE f.case_id = c.id
                     AND f.deleted_at IS NULL
                   ORDER BY f.follow_up_date DESC, f.follow_up_time DESC, f.id DESC
                   LIMIT 1
               )
               AND lf.requires_follow_up = 1
               AND lf.next_follow_up_date IS NOT NULL
               AND lf.next_follow_up_date < CURDATE()'
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, scalar> $params
     */
    private function caseDateClause(string $dateFrom, string $dateTo, array &$params, string $alias = 'c'): string
    {
        $params['date_from'] = $dateFrom;
        $params['date_to'] = $dateTo;

        return 'DATE(' . $alias . '.reported_at) BETWEEN :date_from AND :date_to';
    }

    /**
     * @param array<string, scalar> $params
     */
    private function referralDateClause(string $dateFrom, string $dateTo, array &$params): string
    {
        $params['referral_date_from'] = $dateFrom;
        $params['referral_date_to'] = $dateTo;

        return 'r.referral_date BETWEEN :referral_date_from AND :referral_date_to';
    }

    /**
     * @param array<string, scalar> $params
     */
    private function followUpDateClause(string $dateFrom, string $dateTo, array &$params): string
    {
        $params['follow_up_date_from'] = $dateFrom;
        $params['follow_up_date_to'] = $dateTo;

        return 'f.follow_up_date BETWEEN :follow_up_date_from AND :follow_up_date_to';
    }
}
