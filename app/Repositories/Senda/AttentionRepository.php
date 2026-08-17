<?php

declare(strict_types=1);

namespace App\Repositories\Senda;

use Core\Database;

final class AttentionRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function findById(int $id, bool $withDeleted = false): ?array
    {
        $sql = 'SELECT a.*, u.name AS created_by_name,
                       p.first_names, p.paternal_surname, p.maternal_surname, p.rut, p.birth_date
                FROM senda_attentions a
                LEFT JOIN users u ON u.id = a.created_by
                LEFT JOIN senda_people p ON p.id = a.senda_person_id
                WHERE a.id = :id';

        if (!$withDeleted) {
            $sql .= ' AND a.deleted_at IS NULL';
        }

        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @param array{
     *     q?: string,
     *     rut?: string,
     *     name?: string,
     *     date_from?: string,
     *     date_to?: string,
     *     entry_type?: string,
     *     created_by?: int|null,
     *     ficha?: string
     * } $filters
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->filterSql($filters);
        $from = 'FROM senda_attentions a
                LEFT JOIN users u ON u.id = a.created_by
                LEFT JOIN senda_people p ON p.id = a.senda_person_id
                WHERE a.deleted_at IS NULL' . $where;

        $count = $this->db()->prepare('SELECT COUNT(*) ' . $from);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT a.*, u.name AS created_by_name,
                       p.first_names, p.paternal_surname, p.maternal_surname, p.rut, p.birth_date,
                       (SELECT COUNT(*) FROM senda_assisted_referrals r
                         WHERE r.senda_attention_id = a.id AND r.deleted_at IS NULL) AS ficha_count,
                       (SELECT r.id FROM senda_assisted_referrals r
                         WHERE r.senda_attention_id = a.id AND r.deleted_at IS NULL
                         ORDER BY r.id DESC LIMIT 1) AS ficha_id,
                       (SELECT COUNT(*) FROM senda_follow_ups f
                         WHERE f.senda_attention_id = a.id AND f.deleted_at IS NULL) AS followup_count
                ' . $from . '
                ORDER BY a.attention_date DESC, a.attention_time DESC, a.id DESC
                LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function staffOptions(): array
    {
        $sql = 'SELECT DISTINCT u.id, u.name
                FROM users u
                INNER JOIN senda_attentions a ON a.created_by = u.id AND a.deleted_at IS NULL
                ORDER BY u.name ASC';
        $stmt = $this->db()->query($sql);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filterSql(array $filters): array
    {
        $where = '';
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (
                a.attention_number LIKE :q
                OR p.first_names LIKE :q2
                OR p.paternal_surname LIKE :q3
                OR p.maternal_surname LIKE :q4
                OR p.rut LIKE :q5
                OR p.rut_normalized LIKE :q6
            )';
            $like = '%' . $q . '%';
            $normalized = \App\Support\ChileanRutValidator::normalize($q)
                ?? \App\Support\ChileanRutValidator::clean($q);
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
            $params['q5'] = $like;
            $params['q6'] = '%' . ($normalized ?? $q) . '%';
        }

        $rut = trim((string) ($filters['rut'] ?? ''));
        if ($rut !== '') {
            $normalized = \App\Support\ChileanRutValidator::normalize($rut)
                ?? \App\Support\ChileanRutValidator::clean($rut)
                ?? $rut;
            $where .= ' AND (p.rut_normalized LIKE :rut OR p.rut LIKE :rut2)';
            $params['rut'] = '%' . $normalized . '%';
            $params['rut2'] = '%' . $rut . '%';
        }

        $name = trim((string) ($filters['name'] ?? ''));
        if ($name !== '') {
            $where .= ' AND (
                p.first_names LIKE :name
                OR p.paternal_surname LIKE :name2
                OR p.maternal_surname LIKE :name3
            )';
            $likeName = '%' . $name . '%';
            $params['name'] = $likeName;
            $params['name2'] = $likeName;
            $params['name3'] = $likeName;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $where .= ' AND a.attention_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $where .= ' AND a.attention_date <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $entryType = trim((string) ($filters['entry_type'] ?? ''));
        if ($entryType !== '') {
            $where .= ' AND a.entry_type = :entry_type';
            $params['entry_type'] = $entryType;
        }

        $createdBy = $filters['created_by'] ?? null;
        if ($createdBy !== null && $createdBy !== '') {
            $where .= ' AND a.created_by = :created_by';
            $params['created_by'] = (int) $createdBy;
        }

        $ficha = trim((string) ($filters['ficha'] ?? ''));
        if ($ficha === 'con') {
            $where .= ' AND EXISTS (
                SELECT 1 FROM senda_assisted_referrals r
                WHERE r.senda_attention_id = a.id AND r.deleted_at IS NULL
            )';
        } elseif ($ficha === 'sin') {
            $where .= ' AND NOT EXISTS (
                SELECT 1 FROM senda_assisted_referrals r
                WHERE r.senda_attention_id = a.id AND r.deleted_at IS NULL
            )';
        }

        return [$where, $params];
    }

    public function all(): array
    {
        $sql = 'SELECT a.*, u.name AS created_by_name,
                       p.first_names, p.paternal_surname, p.maternal_surname, p.rut, p.birth_date
                FROM senda_attentions a
                LEFT JOIN users u ON u.id = a.created_by
                LEFT JOIN senda_people p ON p.id = a.senda_person_id
                WHERE a.deleted_at IS NULL
                ORDER BY a.attention_date DESC, a.attention_time DESC, a.id DESC';
        $stmt = $this->db()->query($sql);

        return $stmt->fetchAll() ?: [];
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO senda_attentions (
                    attention_number, senda_person_id, attention_date, attention_time, entry_type,
                    referral_institution_type, referral_institution_name, referral_person,
                    referral_phone, referral_email, referral_notes, summary, created_by
                ) VALUES (
                    :attention_number, :senda_person_id, :attention_date, :attention_time, :entry_type,
                    :referral_institution_type, :referral_institution_name, :referral_person,
                    :referral_phone, :referral_email, :referral_notes, :summary, :created_by
                )';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sql = 'UPDATE senda_attentions
                SET attention_date = :attention_date,
                    attention_time = :attention_time,
                    entry_type = :entry_type,
                    referral_institution_type = :referral_institution_type,
                    referral_institution_name = :referral_institution_name,
                    referral_person = :referral_person,
                    referral_phone = :referral_phone,
                    referral_email = :referral_email,
                    referral_notes = :referral_notes,
                    summary = :summary,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_merge($data, ['id' => $id]));
    }
}
