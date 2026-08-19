<?php

declare(strict_types=1);

namespace App\Repositories\WomenOffice;

use Core\Database;

final class CaseNeedRepository
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
            'SELECT cn.need_id, cn.other_text,
                    n.slug AS need_slug,
                    n.name AS need_name,
                    n.allows_other
             FROM women_case_needs cn
             INNER JOIN women_needs n ON n.id = cn.need_id
             WHERE cn.case_id = :case_id
             ORDER BY n.sort_order ASC, n.name ASC'
        );
        $stmt->execute(['case_id' => $caseId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<array{need_id: int, other_text: ?string}> $items
     */
    public function sync(int $caseId, array $items): void
    {
        $delete = $this->db()->prepare('DELETE FROM women_case_needs WHERE case_id = :case_id');
        $delete->execute(['case_id' => $caseId]);

        if ($items === []) {
            return;
        }

        $insert = $this->db()->prepare(
            'INSERT INTO women_case_needs (case_id, need_id, other_text)
             VALUES (:case_id, :need_id, :other_text)'
        );

        foreach ($items as $item) {
            $insert->execute([
                'case_id' => $caseId,
                'need_id' => $item['need_id'],
                'other_text' => $item['other_text'],
            ]);
        }
    }
}
