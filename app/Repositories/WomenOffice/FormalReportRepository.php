<?php

declare(strict_types=1);

namespace App\Repositories\WomenOffice;

use Core\Database;

final class FormalReportRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function findByCaseId(int $caseId): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT fr.*,
                    i.slug AS institution_slug,
                    i.name AS institution_name
             FROM women_case_formal_reports fr
             LEFT JOIN women_formal_report_institutions i ON i.id = fr.institution_id
             WHERE fr.case_id = :case_id
             LIMIT 1'
        );
        $stmt->execute(['case_id' => $caseId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function upsert(int $caseId, array $data): void
    {
        $existing = $this->findByCaseId($caseId);

        if ($existing === null) {
            $sql = 'INSERT INTO women_case_formal_reports (
                        case_id, institution_id, institution_other, reference_number, report_date, notes
                    ) VALUES (
                        :case_id, :institution_id, :institution_other, :reference_number, :report_date, :notes
                    )';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute(array_merge($data, ['case_id' => $caseId]));

            return;
        }

        $sql = 'UPDATE women_case_formal_reports SET
                    institution_id = :institution_id,
                    institution_other = :institution_other,
                    reference_number = :reference_number,
                    report_date = :report_date,
                    notes = :notes
                WHERE case_id = :case_id';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_merge($data, ['case_id' => $caseId]));
    }

    public function deleteForCase(int $caseId): void
    {
        $stmt = $this->db()->prepare('DELETE FROM women_case_formal_reports WHERE case_id = :case_id');
        $stmt->execute(['case_id' => $caseId]);
    }
}
