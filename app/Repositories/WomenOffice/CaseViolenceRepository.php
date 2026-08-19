<?php

declare(strict_types=1);

namespace App\Repositories\WomenOffice;

use Core\Database;

final class CaseViolenceRepository
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
            'SELECT cvt.violence_type_id, cvt.other_text,
                    vt.slug AS violence_type_slug,
                    vt.name AS violence_type_name,
                    vt.allows_other
             FROM women_case_violence_types cvt
             INNER JOIN women_violence_types vt ON vt.id = cvt.violence_type_id
             WHERE cvt.case_id = :case_id
             ORDER BY vt.sort_order ASC, vt.name ASC'
        );
        $stmt->execute(['case_id' => $caseId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<array{violence_type_id: int, other_text: ?string}> $items
     */
    public function sync(int $caseId, array $items): void
    {
        $delete = $this->db()->prepare('DELETE FROM women_case_violence_types WHERE case_id = :case_id');
        $delete->execute(['case_id' => $caseId]);

        if ($items === []) {
            return;
        }

        $insert = $this->db()->prepare(
            'INSERT INTO women_case_violence_types (case_id, violence_type_id, other_text)
             VALUES (:case_id, :violence_type_id, :other_text)'
        );

        foreach ($items as $item) {
            $insert->execute([
                'case_id' => $caseId,
                'violence_type_id' => $item['violence_type_id'],
                'other_text' => $item['other_text'],
            ]);
        }
    }
}
