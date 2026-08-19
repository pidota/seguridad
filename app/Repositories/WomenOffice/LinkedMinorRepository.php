<?php

declare(strict_types=1);

namespace App\Repositories\WomenOffice;

use Core\Database;

final class LinkedMinorRepository
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
            'SELECT lm.id, lm.case_id, lm.age_range_id, lm.gender, lm.notes, lm.created_at,
                    ar.slug AS age_range_slug,
                    ar.name AS age_range_name
             FROM women_case_linked_minors lm
             LEFT JOIN women_minor_age_ranges ar ON ar.id = lm.age_range_id
             WHERE lm.case_id = :case_id
             ORDER BY ar.sort_order ASC, lm.id ASC'
        );
        $stmt->execute(['case_id' => $caseId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<array{age_range_id: ?int, gender: ?string, notes: ?string}> $items
     */
    public function sync(int $caseId, array $items): void
    {
        $delete = $this->db()->prepare('DELETE FROM women_case_linked_minors WHERE case_id = :case_id');
        $delete->execute(['case_id' => $caseId]);

        if ($items === []) {
            return;
        }

        $insert = $this->db()->prepare(
            'INSERT INTO women_case_linked_minors (case_id, age_range_id, gender, notes)
             VALUES (:case_id, :age_range_id, :gender, :notes)'
        );

        foreach ($items as $item) {
            $insert->execute([
                'case_id' => $caseId,
                'age_range_id' => $item['age_range_id'],
                'gender' => $item['gender'],
                'notes' => $item['notes'],
            ]);
        }
    }
}
