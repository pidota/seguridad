<?php

declare(strict_types=1);

namespace App\Repositories\WomenOffice;

use Core\Database;

final class ProtectiveMeasureRepository
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
            'SELECT pm.id, pm.case_id, pm.measure_type_id, pm.institution, pm.start_date,
                    pm.end_date, pm.cause_number, pm.notes, pm.created_at,
                    mt.slug AS measure_type_slug,
                    mt.name AS measure_type_name
             FROM women_case_protective_measures pm
             LEFT JOIN women_protective_measure_types mt ON mt.id = pm.measure_type_id
             WHERE pm.case_id = :case_id
             ORDER BY pm.start_date DESC, pm.id ASC'
        );
        $stmt->execute(['case_id' => $caseId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<array{
     *     measure_type_id: ?int,
     *     institution: ?string,
     *     start_date: ?string,
     *     end_date: ?string,
     *     cause_number: ?string,
     *     notes: ?string
     * }> $items
     */
    public function sync(int $caseId, array $items): void
    {
        $delete = $this->db()->prepare('DELETE FROM women_case_protective_measures WHERE case_id = :case_id');
        $delete->execute(['case_id' => $caseId]);

        if ($items === []) {
            return;
        }

        $insert = $this->db()->prepare(
            'INSERT INTO women_case_protective_measures (
                case_id, measure_type_id, institution, start_date, end_date, cause_number, notes
             ) VALUES (
                :case_id, :measure_type_id, :institution, :start_date, :end_date, :cause_number, :notes
             )'
        );

        foreach ($items as $item) {
            $insert->execute([
                'case_id' => $caseId,
                'measure_type_id' => $item['measure_type_id'],
                'institution' => $item['institution'],
                'start_date' => $item['start_date'],
                'end_date' => $item['end_date'],
                'cause_number' => $item['cause_number'],
                'notes' => $item['notes'],
            ]);
        }
    }
}
