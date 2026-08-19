<?php

declare(strict_types=1);

namespace App\Repositories\Meetings;

use Core\Database;

final class MeetingRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT m.*, u.name AS created_by_name
             FROM meetings m
             INNER JOIN users u ON u.id = m.created_by
             WHERE m.id = :id AND m.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->filterSql($filters);
        $from = 'FROM meetings m
                 INNER JOIN users u ON u.id = m.created_by
                 WHERE m.deleted_at IS NULL' . $where;

        $count = $this->db()->prepare('SELECT COUNT(*) ' . $from);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT m.*, u.name AS created_by_name ' . $from . '
                ORDER BY m.meeting_date DESC, m.meeting_time DESC, m.id DESC
                LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    public function create(array $data): int
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO meetings (
                meeting_number, source_module, source_record_id,
                meeting_date, meeting_time, meeting_place,
                additional_notes, next_meeting_required,
                next_meeting_date, next_meeting_time, next_meeting_notes,
                status, created_by
             ) VALUES (
                :meeting_number, :source_module, :source_record_id,
                :meeting_date, :meeting_time, :meeting_place,
                :additional_notes, :next_meeting_required,
                :next_meeting_date, :next_meeting_time, :next_meeting_notes,
                :status, :created_by
             )'
        );
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE meetings SET
                meeting_date = :meeting_date,
                meeting_time = :meeting_time,
                meeting_place = :meeting_place,
                additional_notes = :additional_notes,
                next_meeting_required = :next_meeting_required,
                next_meeting_date = :next_meeting_date,
                next_meeting_time = :next_meeting_time,
                next_meeting_notes = :next_meeting_notes,
                updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL AND status = :status_draft'
        );
        $stmt->execute(array_merge($data, [
            'id' => $id,
            'status_draft' => 'draft',
        ]));
    }

    public function finalize(int $id, string $contentHash): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE meetings SET
                status = :status,
                content_hash = :content_hash,
                finalized_at = NOW(),
                updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL AND status = :draft'
        );
        $stmt->execute([
            'id' => $id,
            'status' => 'pending_signatures',
            'content_hash' => $contentHash,
            'draft' => 'draft',
        ]);

        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException('No fue posible finalizar la reunión.');
        }
    }

    public function updateStatus(int $id, string $status, ?string $completedAt = null): void
    {
        $sql = 'UPDATE meetings SET status = :status, updated_at = NOW()';
        $params = ['id' => $id, 'status' => $status];

        if ($completedAt !== null) {
            $sql .= ', completed_at = :completed_at';
            $params['completed_at'] = $completedAt;
        }

        $sql .= ' WHERE id = :id AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
    }

    public function markCorrectionRequested(int $id): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE meetings SET status = :status, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'status' => 'correction_requested']);
    }

    public function cancel(int $id, array $data): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE meetings SET
                status = :status,
                cancelled_at = :cancelled_at,
                cancelled_by = :cancelled_by,
                cancellation_reason = :cancellation_reason,
                updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL AND status <> :cancelled_status'
        );
        $stmt->execute(array_merge($data, [
            'id' => $id,
            'status' => 'cancelled',
            'cancelled_status' => 'cancelled',
        ]));

        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException('No fue posible anular la reunión.');
        }
    }

    public function reopenToDraft(int $id, array $data): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE meetings SET
                status = :status,
                content_hash = NULL,
                content_version = content_version + 1,
                finalized_at = NULL,
                completed_at = NULL,
                reopened_at = :reopened_at,
                reopened_by = :reopened_by,
                reopen_reason = :reopen_reason,
                updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL AND status IN (:pending, :partial, :correction)'
        );
        $stmt->execute(array_merge($data, [
            'id' => $id,
            'status' => 'draft',
            'pending' => 'pending_signatures',
            'partial' => 'partially_signed',
            'correction' => 'correction_requested',
        ]));

        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException('No fue posible reabrir la reunión.');
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filterSql(array $filters): array
    {
        $where = '';
        $params = [];

        $source = trim((string) ($filters['source_module'] ?? ''));
        if ($source !== '') {
            $where .= ' AND m.source_module = :source_module';
            $params['source_module'] = $source;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where .= ' AND m.status = :status';
            $params['status'] = $status;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $where .= ' AND m.meeting_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $where .= ' AND m.meeting_date <= :date_to';
            $params['date_to'] = $dateTo;
        }

        if (!empty($filters['created_by'])) {
            $where .= ' AND m.created_by = :created_by';
            $params['created_by'] = (int) $filters['created_by'];
        }

        if (!empty($filters['participant_user_id'])) {
            $where .= ' AND EXISTS (
                SELECT 1 FROM meeting_participants mp
                WHERE mp.meeting_id = m.id AND mp.user_id = :participant_user_id
            )';
            $params['participant_user_id'] = (int) $filters['participant_user_id'];
        }

        if (!empty($filters['accessible_user_id'])) {
            $where .= ' AND (
                m.created_by = :accessible_user_id
                OR EXISTS (
                    SELECT 1 FROM meeting_participants mp2
                    WHERE mp2.meeting_id = m.id AND mp2.user_id = :accessible_user_id2
                )
                OR EXISTS (
                    SELECT 1 FROM meeting_signatures ms
                    WHERE ms.meeting_id = m.id AND ms.user_id = :accessible_user_id3
                )
            )';
            $params['accessible_user_id'] = (int) $filters['accessible_user_id'];
            $params['accessible_user_id2'] = (int) $filters['accessible_user_id'];
            $params['accessible_user_id3'] = (int) $filters['accessible_user_id'];
        }

        if (!empty($filters['pending_signature_user_id'])) {
            $where .= ' AND EXISTS (
                SELECT 1 FROM meeting_signatures ms2
                WHERE ms2.meeting_id = m.id
                  AND ms2.user_id = :pending_signature_user_id
                  AND ms2.status = :pending_status
            )';
            $params['pending_signature_user_id'] = (int) $filters['pending_signature_user_id'];
            $params['pending_status'] = 'pending';
        }

        return [$where, $params];
    }
}
