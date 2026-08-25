<?php

declare(strict_types=1);

namespace App\Repositories\Senda;

use App\Services\Senda\EntryType;
use App\Services\Senda\ReferralStatus;
use Core\Database;

final class StatsRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    /**
     * @return array{
     *     attentions_today: int,
     *     attentions_month: int,
     *     derivations_month: int,
     *     spontaneous_month: int,
     *     referrals_total: int,
     *     screenings_total: int,
     *     followups_month: int
     * }
     */
    public function dashboardTotals(string $today, string $monthStart, string $monthEnd): array
    {
        $attentions = $this->db()->prepare(
            'SELECT
                SUM(CASE WHEN a.attention_date = :today THEN 1 ELSE 0 END) AS attentions_today,
                SUM(CASE WHEN a.attention_date BETWEEN :month_start AND :month_end THEN 1 ELSE 0 END) AS attentions_month,
                SUM(CASE WHEN a.entry_type = :derivacion AND a.attention_date BETWEEN :month_start2 AND :month_end2 THEN 1 ELSE 0 END) AS derivations_month,
                SUM(CASE WHEN a.entry_type = :espontanea AND a.attention_date BETWEEN :month_start3 AND :month_end3 THEN 1 ELSE 0 END) AS spontaneous_month
             FROM senda_attentions a
             WHERE a.deleted_at IS NULL'
        );
        $attentions->execute([
            'today' => $today,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
            'month_start2' => $monthStart,
            'month_end2' => $monthEnd,
            'month_start3' => $monthStart,
            'month_end3' => $monthEnd,
            'derivacion' => EntryType::DERIVACION,
            'espontanea' => EntryType::DEMANDA_ESPONTANEA,
        ]);
        $row = $attentions->fetch() ?: [];

        $referrals = $this->db()->prepare(
            'SELECT
                SUM(CASE WHEN r.status = :completed THEN 1 ELSE 0 END) AS referrals_total,
                SUM(CASE WHEN r.screening_used = 1 THEN 1 ELSE 0 END) AS screenings_total
             FROM senda_assisted_referrals r
             WHERE r.deleted_at IS NULL'
        );
        $referrals->execute(['completed' => ReferralStatus::COMPLETED]);
        $referralRow = $referrals->fetch() ?: [];

        $followUps = $this->db()->prepare(
            'SELECT COUNT(*) FROM senda_follow_ups f
             WHERE f.deleted_at IS NULL AND f.follow_up_date BETWEEN :month_start AND :month_end'
        );
        $followUps->execute([
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
        ]);

        return [
            'attentions_today' => (int) ($row['attentions_today'] ?? 0),
            'attentions_month' => (int) ($row['attentions_month'] ?? 0),
            'derivations_month' => (int) ($row['derivations_month'] ?? 0),
            'spontaneous_month' => (int) ($row['spontaneous_month'] ?? 0),
            'referrals_total' => (int) ($referralRow['referrals_total'] ?? 0),
            'screenings_total' => (int) ($referralRow['screenings_total'] ?? 0),
            'followups_month' => (int) $followUps->fetchColumn(),
        ];
    }

    /**
     * @return array{
     *     attentions_period: int,
     *     derivations_period: int,
     *     spontaneous_period: int,
     *     referrals_completed: int,
     *     screenings_period: int,
     *     followups_period: int
     * }
     */
    public function periodTotals(string $dateFrom, string $dateTo): array
    {
        $attentions = $this->db()->prepare(
            'SELECT
                COUNT(*) AS attentions_period,
                SUM(CASE WHEN a.entry_type = :derivacion THEN 1 ELSE 0 END) AS derivations_period,
                SUM(CASE WHEN a.entry_type = :espontanea THEN 1 ELSE 0 END) AS spontaneous_period
             FROM senda_attentions a
             WHERE a.deleted_at IS NULL
               AND a.attention_date BETWEEN :date_from AND :date_to'
        );
        $attentions->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'derivacion' => EntryType::DERIVACION,
            'espontanea' => EntryType::DEMANDA_ESPONTANEA,
        ]);
        $row = $attentions->fetch() ?: [];

        $referrals = $this->db()->prepare(
            'SELECT
                SUM(CASE WHEN r.status = :completed THEN 1 ELSE 0 END) AS referrals_completed,
                SUM(CASE WHEN r.screening_used = 1 THEN 1 ELSE 0 END) AS screenings_period
             FROM senda_assisted_referrals r
             WHERE r.deleted_at IS NULL
               AND r.request_date BETWEEN :date_from AND :date_to'
        );
        $referrals->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'completed' => ReferralStatus::COMPLETED,
        ]);
        $referralRow = $referrals->fetch() ?: [];

        $followUps = $this->db()->prepare(
            'SELECT COUNT(*) FROM senda_follow_ups f
             WHERE f.deleted_at IS NULL
               AND f.follow_up_date BETWEEN :date_from AND :date_to'
        );
        $followUps->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        return [
            'attentions_period' => (int) ($row['attentions_period'] ?? 0),
            'derivations_period' => (int) ($row['derivations_period'] ?? 0),
            'spontaneous_period' => (int) ($row['spontaneous_period'] ?? 0),
            'referrals_completed' => (int) ($referralRow['referrals_completed'] ?? 0),
            'screenings_period' => (int) ($referralRow['screenings_period'] ?? 0),
            'followups_period' => (int) $followUps->fetchColumn(),
        ];
    }

    /**
     * @return list<array{period: string, total: int}>
     */
    public function attentionsByMonthForPeriod(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db()->prepare(
            'SELECT DATE_FORMAT(a.attention_date, \'%Y-%m\') AS period, COUNT(*) AS total
             FROM senda_attentions a
             WHERE a.deleted_at IS NULL
               AND a.attention_date BETWEEN :date_from AND :date_to
             GROUP BY DATE_FORMAT(a.attention_date, \'%Y-%m\')
             ORDER BY period ASC'
        );
        $stmt->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array{period: string, total: int}>
     */
    public function attentionsByMonth(int $months = 12): array
    {
        $months = max(1, $months);
        $from = (new \DateTimeImmutable('first day of this month'))
            ->modify('-' . ($months - 1) . ' months')
            ->format('Y-m-d');
        $to = date('Y-m-d');

        return $this->attentionsByMonthForPeriod($from, $to);
    }

    /**
     * @return list<array{bucket: string, total: int}>
     */
    public function attentionsByAge(?string $dateFrom = null, ?string $dateTo = null): array
    {
        [$dateSql, $params] = $this->attentionDateSql($dateFrom, $dateTo);
        $sql = 'SELECT bucket, COUNT(*) AS total FROM (
                    SELECT CASE
                        WHEN p.birth_date IS NULL THEN \'sin_dato\'
                        WHEN TIMESTAMPDIFF(YEAR, p.birth_date, a.attention_date) < 18 THEN \'0_17\'
                        WHEN TIMESTAMPDIFF(YEAR, p.birth_date, a.attention_date) < 30 THEN \'18_29\'
                        WHEN TIMESTAMPDIFF(YEAR, p.birth_date, a.attention_date) < 45 THEN \'30_44\'
                        WHEN TIMESTAMPDIFF(YEAR, p.birth_date, a.attention_date) < 60 THEN \'45_59\'
                        ELSE \'60_plus\'
                    END AS bucket
                    FROM senda_attentions a
                    LEFT JOIN senda_people p ON p.id = a.senda_person_id
                    WHERE a.deleted_at IS NULL' . $dateSql . '
                ) aged
                GROUP BY bucket';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array{entry_type: string, total: int}>
     */
    public function attentionsByEntryType(?string $dateFrom = null, ?string $dateTo = null): array
    {
        [$dateSql, $params] = $this->attentionDateSql($dateFrom, $dateTo);
        $stmt = $this->db()->prepare(
            'SELECT a.entry_type, COUNT(*) AS total
             FROM senda_attentions a
             WHERE a.deleted_at IS NULL' . $dateSql . '
             GROUP BY a.entry_type
             ORDER BY total DESC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array{destination_center: string, total: int}>
     */
    public function referralsByDestinationCenter(?string $dateFrom = null, ?string $dateTo = null): array
    {
        [$dateSql, $params] = $this->referralDateSql($dateFrom, $dateTo);
        $stmt = $this->db()->prepare(
            'SELECT TRIM(r.destination_center) AS destination_center, COUNT(*) AS total
             FROM senda_assisted_referrals r
             WHERE r.deleted_at IS NULL
               AND r.destination_center IS NOT NULL
               AND TRIM(r.destination_center) <> \'\'' . $dateSql . '
             GROUP BY TRIM(r.destination_center)
             ORDER BY total DESC, destination_center ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array{risk_level: string, total: int}>
     */
    public function assistByClassification(?string $dateFrom = null, ?string $dateTo = null): array
    {
        [$dateSql, $params] = $this->referralDateSql($dateFrom, $dateTo, 'r');
        $stmt = $this->db()->prepare(
            'SELECT COALESCE(NULLIF(ar.risk_level, \'\'), \'sin_clasificar\') AS risk_level, COUNT(*) AS total
             FROM senda_assist_results ar
             INNER JOIN senda_assisted_referrals r ON r.id = ar.assisted_referral_id AND r.deleted_at IS NULL
             WHERE ar.score IS NOT NULL' . $dateSql . '
             GROUP BY risk_level
             ORDER BY total DESC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array{result: string, total: int}>
     */
    public function followUpsByResult(?string $dateFrom = null, ?string $dateTo = null): array
    {
        [$dateSql, $params] = $this->followUpDateSql($dateFrom, $dateTo);
        $stmt = $this->db()->prepare(
            'SELECT f.result, COUNT(*) AS total
             FROM senda_follow_ups f
             WHERE f.deleted_at IS NULL' . $dateSql . '
             GROUP BY f.result
             ORDER BY total DESC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function followUpTotal(?string $dateFrom = null, ?string $dateTo = null): int
    {
        [$dateSql, $params] = $this->followUpDateSql($dateFrom, $dateTo);
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*) FROM senda_follow_ups f WHERE f.deleted_at IS NULL' . $dateSql
        );
        $stmt->execute($params);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function attentionDateSql(?string $dateFrom, ?string $dateTo): array
    {
        if ($dateFrom === null || $dateTo === null) {
            return ['', []];
        }

        return [
            ' AND a.attention_date BETWEEN :date_from AND :date_to',
            ['date_from' => $dateFrom, 'date_to' => $dateTo],
        ];
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function referralDateSql(?string $dateFrom, ?string $dateTo, string $alias = 'r'): array
    {
        if ($dateFrom === null || $dateTo === null) {
            return ['', []];
        }

        return [
            ' AND ' . $alias . '.request_date BETWEEN :date_from AND :date_to',
            ['date_from' => $dateFrom, 'date_to' => $dateTo],
        ];
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function followUpDateSql(?string $dateFrom, ?string $dateTo): array
    {
        if ($dateFrom === null || $dateTo === null) {
            return ['', []];
        }

        return [
            ' AND f.follow_up_date BETWEEN :date_from AND :date_to',
            ['date_from' => $dateFrom, 'date_to' => $dateTo],
        ];
    }
}

