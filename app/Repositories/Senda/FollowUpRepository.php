<?php

declare(strict_types=1);

namespace App\Repositories\Senda;

use App\Services\Senda\FollowUpStatus;
use App\Support\ChileanRutValidator;
use Core\Database;

final class FollowUpRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function findById(int $id, bool $withDeleted = false): ?array
    {
        $sql = $this->selectSql() . ' WHERE f.id = :id';

        if (!$withDeleted) {
            $sql .= ' AND f.deleted_at IS NULL';
        }

        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(?int $attentionId = null): array
    {
        $sql = $this->selectSql() . ' WHERE f.deleted_at IS NULL';
        $params = [];

        if ($attentionId !== null && $attentionId > 0) {
            $sql .= ' AND f.senda_attention_id = :attention_id';
            $params['attention_id'] = $attentionId;
        }

        $sql .= ' ORDER BY f.follow_up_date DESC, f.follow_up_time DESC, f.id DESC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->filterSql($filters);
        $from = $this->fromSql() . ' WHERE f.deleted_at IS NULL' . $where;

        $count = $this->db()->prepare('SELECT COUNT(*) ' . $from);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $sql = $this->selectSql() . ' WHERE f.deleted_at IS NULL' . $where . '
                ORDER BY f.follow_up_date DESC, f.follow_up_time DESC, f.id DESC
                LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    /**
     * @return array<string, int>
     */
    public function scheduleCounts(?string $today = null): array
    {
        $today = FollowUpStatus::today($today);
        $counts = [];

        foreach (FollowUpStatus::countableKeys() as $status) {
            $sql = 'SELECT COUNT(*) ' . $this->fromSql() . '
                    WHERE f.deleted_at IS NULL
                      AND ' . FollowUpStatus::matchSql($status);
            $stmt = $this->db()->prepare($sql);
            $params = FollowUpStatus::usesTodayParam($status)
                ? ['status_today' => $today]
                : [];
            $stmt->execute($params);
            $counts[$status] = (int) $stmt->fetchColumn();
        }

        return $counts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingAlerts(int $limit = 8, ?string $today = null): array
    {
        $limit = max(1, min($limit, 20));
        $status = FollowUpStatus::PENDING;
        $sql = $this->selectSql() . '
                WHERE f.deleted_at IS NULL
                  AND ' . FollowUpStatus::matchSql($status) . '
                ORDER BY f.next_follow_up_date ASC, f.id ASC
                LIMIT ' . $limit;
        $stmt = $this->db()->prepare($sql);
        $params = FollowUpStatus::usesTodayParam($status)
            ? ['status_today' => FollowUpStatus::today($today)]
            : [];
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function staffOptions(): array
    {
        $sql = 'SELECT DISTINCT u.id, u.name
                FROM users u
                INNER JOIN senda_follow_ups f ON f.created_by = u.id AND f.deleted_at IS NULL
                ORDER BY u.name ASC';
        $stmt = $this->db()->query($sql);

        return $stmt->fetchAll() ?: [];
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO senda_follow_ups (
                    senda_attention_id, follow_up_date, follow_up_time,
                    contact_type, contact_type_other, result, result_other, notes,
                    requires_follow_up, next_follow_up_date, created_by
                ) VALUES (
                    :senda_attention_id, :follow_up_date, :follow_up_time,
                    :contact_type, :contact_type_other, :result, :result_other, :notes,
                    :requires_follow_up, :next_follow_up_date, :created_by
                )';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sql = 'UPDATE senda_follow_ups
                SET follow_up_date = :follow_up_date,
                    follow_up_time = :follow_up_time,
                    contact_type = :contact_type,
                    contact_type_other = :contact_type_other,
                    result = :result,
                    result_other = :result_other,
                    notes = :notes,
                    requires_follow_up = :requires_follow_up,
                    next_follow_up_date = :next_follow_up_date,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_merge($data, ['id' => $id]));
    }

    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE senda_follow_ups SET deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
    }

    /**
     * @param list<int> $attentionIds
     * @return list<array<string, mixed>>
     */
    public function forAttentionIds(array $attentionIds): array
    {
        [$placeholders, $params] = $this->idPlaceholders($attentionIds);

        if ($placeholders === null) {
            return [];
        }

        $sql = $this->selectSql() . ' WHERE f.deleted_at IS NULL
                AND f.senda_attention_id IN (' . $placeholders . ')
                ORDER BY f.follow_up_date ASC, f.follow_up_time ASC, f.id ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    private function selectSql(): string
    {
        return 'SELECT f.*,
                       a.attention_number, a.attention_date, a.entry_type, a.senda_person_id,
                       p.first_names, p.paternal_surname, p.maternal_surname, p.rut,
                       u.name AS created_by_name
                ' . $this->fromSql();
    }

    private function fromSql(): string
    {
        return 'FROM senda_follow_ups f
                INNER JOIN senda_attentions a ON a.id = f.senda_attention_id
                LEFT JOIN senda_people p ON p.id = a.senda_person_id
                LEFT JOIN users u ON u.id = f.created_by';
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filterSql(array $filters): array
    {
        $where = '';
        $params = [];

        $attentionId = (int) ($filters['attention'] ?? 0);
        if ($attentionId > 0) {
            $where .= ' AND f.senda_attention_id = :attention_id';
            $params['attention_id'] = $attentionId;
        }

        $name = trim((string) ($filters['name'] ?? ''));
        if ($name !== '') {
            $where .= ' AND (
                p.first_names LIKE :name
                OR p.paternal_surname LIKE :name2
                OR p.maternal_surname LIKE :name3
                OR CONCAT_WS(\' \', p.first_names, p.paternal_surname, p.maternal_surname) LIKE :name4
            )';
            $likeName = '%' . $name . '%';
            $params['name'] = $likeName;
            $params['name2'] = $likeName;
            $params['name3'] = $likeName;
            $params['name4'] = $likeName;
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

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $where .= ' AND f.follow_up_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $where .= ' AND f.follow_up_date <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $contactType = trim((string) ($filters['contact_type'] ?? ''));
        if ($contactType !== '') {
            $where .= ' AND f.contact_type = :contact_type';
            $params['contact_type'] = $contactType;
        }

        $result = trim((string) ($filters['result'] ?? ''));
        if ($result !== '') {
            $where .= ' AND f.result = :result';
            $params['result'] = $result;
        }

        $createdBy = $filters['created_by'] ?? null;
        if ($createdBy !== null && $createdBy !== '') {
            $where .= ' AND f.created_by = :created_by';
            $params['created_by'] = (int) $createdBy;
        }

        $pending = trim((string) ($filters['pending'] ?? ''));
        if ($pending === 'si') {
            $where .= ' AND f.requires_follow_up = 1';
        } elseif ($pending === 'no') {
            $where .= ' AND f.requires_follow_up = 0';
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if (FollowUpStatus::isValid($status)) {
            $where .= ' AND ' . FollowUpStatus::matchSql($status);
            if (FollowUpStatus::usesTodayParam($status)) {
                $params['status_today'] = FollowUpStatus::today();
            }
        }

        return [$where, $params];
    }

    /**
     * @param list<int> $ids
     * @return array{0: string|null, 1: array<string, int>}
     */
    private function idPlaceholders(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [null, []];
        }

        $placeholders = [];
        $params = [];

        foreach ($ids as $index => $id) {
            $key = 'id' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        return [implode(', ', $placeholders), $params];
    }
}
