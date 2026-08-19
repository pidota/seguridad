<?php

declare(strict_types=1);

namespace App\Repositories\WomenOffice;

use Core\Database;

final class CaseRiskFactorRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCase(int $caseId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT crf.risk_factor_id, crf.other_text,
                    rf.slug AS risk_factor_slug,
                    rf.name AS risk_factor_name,
                    rf.allows_other
             FROM women_case_risk_factors crf
             INNER JOIN women_risk_factors rf ON rf.id = crf.risk_factor_id
             WHERE crf.case_id = :case_id
             ORDER BY rf.sort_order ASC, rf.name ASC'
        );
        $stmt->execute(['case_id' => $caseId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<array{risk_factor_id: int, other_text: ?string}> $items
     */
    public function sync(int $caseId, array $items): void
    {
        $delete = $this->db()->prepare('DELETE FROM women_case_risk_factors WHERE case_id = :case_id');
        $delete->execute(['case_id' => $caseId]);

        if ($items === []) {
            return;
        }

        $insert = $this->db()->prepare(
            'INSERT INTO women_case_risk_factors (case_id, risk_factor_id, other_text)
             VALUES (:case_id, :risk_factor_id, :other_text)'
        );

        foreach ($items as $item) {
            $insert->execute([
                'case_id' => $caseId,
                'risk_factor_id' => $item['risk_factor_id'],
                'other_text' => $item['other_text'],
            ]);
        }
    }
}
