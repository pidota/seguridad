<?php

declare(strict_types=1);

namespace App\Repositories\WomenOffice;

use Core\Database;

final class PreviousReportRepository
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
            'SELECT id, case_id, institution_name, report_date, reference_number, notes, created_at
             FROM women_case_previous_reports
             WHERE case_id = :case_id
             ORDER BY report_date DESC, id ASC'
        );
        $stmt->execute(['case_id' => $caseId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<array{institution_name: string, report_date: ?string, reference_number: ?string, notes: ?string}> $items
     */
    public function sync(int $caseId, array $items): void
    {
        $delete = $this->db()->prepare('DELETE FROM women_case_previous_reports WHERE case_id = :case_id');
        $delete->execute(['case_id' => $caseId]);

        if ($items === []) {
            return;
        }

        $insert = $this->db()->prepare(
            'INSERT INTO women_case_previous_reports (case_id, institution_name, report_date, reference_number, notes)
             VALUES (:case_id, :institution_name, :report_date, :reference_number, :notes)'
        );

        foreach ($items as $item) {
            $insert->execute([
                'case_id' => $caseId,
                'institution_name' => $item['institution_name'],
                'report_date' => $item['report_date'],
                'reference_number' => $item['reference_number'],
                'notes' => $item['notes'],
            ]);
        }
    }
}
