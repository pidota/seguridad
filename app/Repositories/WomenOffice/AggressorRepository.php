<?php

declare(strict_types=1);

namespace App\Repositories\WomenOffice;

use Core\Database;

final class AggressorRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function findByCaseId(int $caseId): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT *
             FROM women_case_aggressors
             WHERE case_id = :case_id AND deleted_at IS NULL
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
            $sql = 'INSERT INTO women_case_aggressors (
                        case_id, first_names, paternal_surname, maternal_surname,
                        rut, rut_normalized, birth_date, approximate_age,
                        phone, address, occupation, notes
                    ) VALUES (
                        :case_id, :first_names, :paternal_surname, :maternal_surname,
                        :rut, :rut_normalized, :birth_date, :approximate_age,
                        :phone, :address, :occupation, :notes
                    )';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute(array_merge($data, ['case_id' => $caseId]));

            return;
        }

        $sql = 'UPDATE women_case_aggressors SET
                    first_names = :first_names,
                    paternal_surname = :paternal_surname,
                    maternal_surname = :maternal_surname,
                    rut = :rut,
                    rut_normalized = :rut_normalized,
                    birth_date = :birth_date,
                    approximate_age = :approximate_age,
                    phone = :phone,
                    address = :address,
                    occupation = :occupation,
                    notes = :notes,
                    updated_at = NOW()
                WHERE case_id = :case_id AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_merge($data, ['case_id' => $caseId]));
    }

    public function deleteForCase(int $caseId): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE women_case_aggressors
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE case_id = :case_id AND deleted_at IS NULL'
        );
        $stmt->execute(['case_id' => $caseId]);
    }
}
