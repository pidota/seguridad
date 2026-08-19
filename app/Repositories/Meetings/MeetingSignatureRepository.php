<?php

declare(strict_types=1);

namespace App\Repositories\Meetings;

use Core\Database;

final class MeetingSignatureRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO meeting_signatures (
                meeting_id, participant_id, user_id, status
             ) VALUES (
                :meeting_id, :participant_id, :user_id, :status
             )'
        );
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forMeeting(int $meetingId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT ms.*, u.name AS user_name, mp.participant_type
             FROM meeting_signatures ms
             INNER JOIN users u ON u.id = ms.user_id
             INNER JOIN meeting_participants mp ON mp.id = ms.participant_id
             WHERE ms.meeting_id = :meeting_id
             ORDER BY ms.id ASC'
        );
        $stmt->execute(['meeting_id' => $meetingId]);

        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT ms.*, u.name AS user_name
             FROM meeting_signatures ms
             INNER JOIN users u ON u.id = ms.user_id
             WHERE ms.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findPendingForUser(int $meetingId, int $userId): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT ms.*
             FROM meeting_signatures ms
             WHERE ms.meeting_id = :meeting_id
               AND ms.user_id = :user_id
               AND ms.status = :status
               AND ms.invalidated_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'meeting_id' => $meetingId,
            'user_id' => $userId,
            'status' => 'pending',
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function markSigned(int $id, array $data): bool
    {
        $stmt = $this->db()->prepare(
            'UPDATE meeting_signatures SET
                status = :status,
                signature_snapshot_path = :signature_snapshot_path,
                signed_at = :signed_at,
                signed_ip = :signed_ip,
                content_hash_at_signing = :content_hash_at_signing,
                updated_at = NOW()
             WHERE id = :id AND status = :pending'
        );
        $stmt->execute(array_merge($data, [
            'id' => $id,
            'pending' => 'pending',
        ]));

        return $stmt->rowCount() > 0;
    }

    public function invalidateForMeeting(int $meetingId): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE meeting_signatures
             SET invalidated_at = NOW(), updated_at = NOW()
             WHERE meeting_id = :meeting_id AND invalidated_at IS NULL'
        );
        $stmt->execute(['meeting_id' => $meetingId]);
    }

    public function countPendingForUser(int $userId): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*)
             FROM meeting_signatures ms
             INNER JOIN meetings m ON m.id = ms.meeting_id AND m.deleted_at IS NULL
             WHERE ms.user_id = :user_id
               AND ms.status = :status
               AND ms.invalidated_at IS NULL
               AND m.status IN (:pending, :partial, :correction)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'status' => 'pending',
            'pending' => 'pending_signatures',
            'partial' => 'partially_signed',
            'correction' => 'correction_requested',
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingMeetingsForUser(int $userId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT m.*, u.name AS created_by_name, ms.id AS signature_id,
                    (SELECT COUNT(*) FROM meeting_signatures s WHERE s.meeting_id = m.id AND s.invalidated_at IS NULL) AS signatures_total,
                    (SELECT COUNT(*) FROM meeting_signatures s WHERE s.meeting_id = m.id AND s.status = :signed AND s.invalidated_at IS NULL) AS signatures_signed
             FROM meeting_signatures ms
             INNER JOIN meetings m ON m.id = ms.meeting_id AND m.deleted_at IS NULL
             INNER JOIN users u ON u.id = m.created_by
             WHERE ms.user_id = :user_id
               AND ms.status = :pending
               AND ms.invalidated_at IS NULL
               AND m.status IN (:status_pending, :status_partial)
             ORDER BY m.meeting_date DESC, m.id DESC'
        );
        $stmt->execute([
            'user_id' => $userId,
            'pending' => 'pending',
            'signed' => 'signed',
            'status_pending' => 'pending_signatures',
            'status_partial' => 'partially_signed',
        ]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return array{total: int, signed: int, pending: int}
     */
    public function countsForMeeting(int $meetingId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = :signed THEN 1 ELSE 0 END) AS signed,
                SUM(CASE WHEN status = :pending THEN 1 ELSE 0 END) AS pending
             FROM meeting_signatures
             WHERE meeting_id = :meeting_id AND invalidated_at IS NULL'
        );
        $stmt->execute([
            'meeting_id' => $meetingId,
            'signed' => 'signed',
            'pending' => 'pending',
        ]);
        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'signed' => (int) ($row['signed'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
        ];
    }

    public function markRejected(int $id, string $reason): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE meeting_signatures SET
                status = :status,
                rejected_at = NOW(),
                rejection_reason = :reason,
                updated_at = NOW()
             WHERE id = :id AND status = :pending'
        );
        $stmt->execute([
            'id' => $id,
            'status' => 'rejected',
            'reason' => $reason,
            'pending' => 'pending',
        ]);
    }
}
