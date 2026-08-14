<?php

declare(strict_types=1);

namespace App\Repositories\Senda;

use Core\Database;

final class AssistResultRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forReferral(int $referralId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, assisted_referral_id, substance, score, risk_level, created_at, updated_at
             FROM senda_assist_results
             WHERE assisted_referral_id = :referral_id
             ORDER BY id ASC'
        );
        $stmt->execute(['referral_id' => $referralId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<int> $referralIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function groupedByReferralIds(array $referralIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $referralIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = 'id' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $stmt = $this->db()->prepare(
            'SELECT id, assisted_referral_id, substance, score, risk_level, created_at, updated_at
             FROM senda_assist_results
             WHERE assisted_referral_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY id ASC'
        );
        $stmt->execute($params);

        $grouped = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $grouped[(int) $row['assisted_referral_id']][] = $row;
        }

        return $grouped;
    }

    /**
     * @param list<array{substance: string, score: ?int, risk_level: ?string}> $rows
     */
    public function replaceForReferral(int $referralId, array $rows): void
    {
        $delete = $this->db()->prepare(
            'DELETE FROM senda_assist_results WHERE assisted_referral_id = :referral_id'
        );
        $delete->execute(['referral_id' => $referralId]);

        if ($rows === []) {
            return;
        }

        $insert = $this->db()->prepare(
            'INSERT INTO senda_assist_results (assisted_referral_id, substance, score, risk_level)
             VALUES (:assisted_referral_id, :substance, :score, :risk_level)'
        );

        foreach ($rows as $row) {
            $insert->execute([
                'assisted_referral_id' => $referralId,
                'substance' => $row['substance'],
                'score' => $row['score'],
                'risk_level' => $row['risk_level'],
            ]);
        }
    }
}
