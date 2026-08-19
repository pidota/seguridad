<?php

declare(strict_types=1);

namespace App\Repositories\WomenOffice;

use Core\Database;

final class PersonRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function findById(int $id, bool $withDeleted = false): ?array
    {
        $sql = 'SELECT p.*, s.name AS sector_name, e.name AS education_level_name
                FROM women_people p
                LEFT JOIN sectors s ON s.id = p.sector_id
                LEFT JOIN women_education_levels e ON e.id = p.education_level_id
                WHERE p.id = :id';

        if (!$withDeleted) {
            $sql .= ' AND p.deleted_at IS NULL';
        }

        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByNormalizedRut(string $normalized, bool $withDeleted = true): ?array
    {
        $sql = 'SELECT p.*, s.name AS sector_name, e.name AS education_level_name
                FROM women_people p
                LEFT JOIN sectors s ON s.id = p.sector_id
                LEFT JOIN women_education_levels e ON e.id = p.education_level_id
                WHERE p.rut_normalized = :rut';

        if (!$withDeleted) {
            $sql .= ' AND p.deleted_at IS NULL';
        }

        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute(['rut' => $normalized]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO women_people (
                    first_names, paternal_surname, maternal_surname, rut, rut_normalized,
                    birth_date, phone, email, address, sector_id, nationality, occupation,
                    education_level_id, safe_contact, safe_contact_notes
                ) VALUES (
                    :first_names, :paternal_surname, :maternal_surname, :rut, :rut_normalized,
                    :birth_date, :phone, :email, :address, :sector_id, :nationality, :occupation,
                    :education_level_id, :safe_contact, :safe_contact_notes
                )';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function restore(int $id): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE women_people SET deleted_at = NULL, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE women_people SET
                first_names = :first_names,
                paternal_surname = :paternal_surname,
                maternal_surname = :maternal_surname,
                birth_date = :birth_date,
                phone = :phone,
                email = :email,
                address = :address,
                sector_id = :sector_id,
                nationality = :nationality,
                occupation = :occupation,
                education_level_id = :education_level_id,
                safe_contact = :safe_contact,
                safe_contact_notes = :safe_contact_notes,
                updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(array_merge($data, ['id' => $id]));
    }

    public function isAffectedInUserCases(int $personId, int $userId): bool
    {
        $stmt = $this->db()->prepare(
            'SELECT 1 FROM women_cases
             WHERE affected_person_id = :person_id
               AND created_by = :user_id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['person_id' => $personId, 'user_id' => $userId]);

        return (bool) $stmt->fetchColumn();
    }
}
