<?php

declare(strict_types=1);

namespace App\Repositories\Senda;

use Core\Database;

final class PersonRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function findById(int $id, bool $withDeleted = false): ?array
    {
        $sql = 'SELECT * FROM senda_people WHERE id = :id';

        if (!$withDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }

        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByNormalizedRut(string $normalized, bool $withDeleted = true): ?array
    {
        $sql = 'SELECT * FROM senda_people WHERE rut_normalized = :rut';

        if (!$withDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }

        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute(['rut' => $normalized]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function search(?string $term = null): array
    {
        $sql = 'SELECT p.*,
                    (SELECT COUNT(*) FROM senda_attentions a WHERE a.senda_person_id = p.id AND a.deleted_at IS NULL) AS attentions_count
                FROM senda_people p
                WHERE p.deleted_at IS NULL';
        $params = [];

        if ($term !== null && $term !== '') {
            $sql .= ' AND (
                p.rut_normalized LIKE :q1
                OR p.rut LIKE :q2
                OR p.first_names LIKE :q3
                OR p.paternal_surname LIKE :q4
                OR p.maternal_surname LIKE :q5
            )';
            $like = '%' . $term . '%';
            $params = [
                'q1' => $like,
                'q2' => $like,
                'q3' => $like,
                'q4' => $like,
                'q5' => $like,
            ];
        }

        $sql .= ' ORDER BY p.paternal_surname ASC, p.maternal_surname ASC, p.first_names ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function attentionsFor(int $personId): array
    {
        $sql = 'SELECT a.*, u.name AS created_by_name,
                       (SELECT r.id FROM senda_assisted_referrals r
                         WHERE r.senda_attention_id = a.id AND r.deleted_at IS NULL
                         ORDER BY r.id DESC LIMIT 1) AS ficha_id,
                       (SELECT COUNT(*) FROM senda_follow_ups f
                         WHERE f.senda_attention_id = a.id AND f.deleted_at IS NULL) AS followup_count
                FROM senda_attentions a
                LEFT JOIN users u ON u.id = a.created_by
                WHERE a.senda_person_id = :person_id AND a.deleted_at IS NULL
                ORDER BY a.attention_number ASC, a.attention_date ASC, a.id ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['person_id' => $personId]);

        return $stmt->fetchAll() ?: [];
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO senda_people (
                    first_names, paternal_surname, maternal_surname, rut, rut_normalized,
                    birth_date, address, phone, email, education, occupation
                ) VALUES (
                    :first_names, :paternal_surname, :maternal_surname, :rut, :rut_normalized,
                    :birth_date, :address, :phone, :email, :education, :occupation
                )';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sql = 'UPDATE senda_people
                SET first_names = :first_names,
                    paternal_surname = :paternal_surname,
                    maternal_surname = :maternal_surname,
                    rut = :rut,
                    rut_normalized = :rut_normalized,
                    birth_date = :birth_date,
                    address = :address,
                    phone = :phone,
                    email = :email,
                    education = :education,
                    occupation = :occupation,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_merge($data, ['id' => $id]));
    }

    public function restore(int $id): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE senda_people SET deleted_at = NULL, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }
}
