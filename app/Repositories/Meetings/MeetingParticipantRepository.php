<?php

declare(strict_types=1);

namespace App\Repositories\Meetings;

use Core\Database;

final class MeetingParticipantRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forMeeting(int $meetingId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT mp.*, u.name AS user_name, u.email AS user_email
             FROM meeting_participants mp
             LEFT JOIN users u ON u.id = mp.user_id
             WHERE mp.meeting_id = :meeting_id
             ORDER BY mp.sort_order ASC, mp.id ASC'
        );
        $stmt->execute(['meeting_id' => $meetingId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    public function sync(int $meetingId, array $items): void
    {
        $this->db()->prepare('DELETE FROM meeting_participants WHERE meeting_id = :id')
            ->execute(['id' => $meetingId]);

        if ($items === []) {
            return;
        }

        $stmt = $this->db()->prepare(
            'INSERT INTO meeting_participants (
                meeting_id, participant_type, user_id,
                external_name, external_position, external_organization, external_email,
                signature_required, sort_order
             ) VALUES (
                :meeting_id, :participant_type, :user_id,
                :external_name, :external_position, :external_organization, :external_email,
                :signature_required, :sort_order
             )'
        );

        foreach ($items as $item) {
            $stmt->execute([
                'meeting_id' => $meetingId,
                'participant_type' => $item['participant_type'],
                'user_id' => $item['user_id'],
                'external_name' => $item['external_name'],
                'external_position' => $item['external_position'],
                'external_organization' => $item['external_organization'],
                'external_email' => $item['external_email'],
                'signature_required' => $item['signature_required'],
                'sort_order' => $item['sort_order'],
            ]);
        }
    }

    public function isParticipant(int $meetingId, int $userId): bool
    {
        $stmt = $this->db()->prepare(
            'SELECT 1 FROM meeting_participants
             WHERE meeting_id = :meeting_id AND user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['meeting_id' => $meetingId, 'user_id' => $userId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByAttendanceToken(string $token): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT mp.*, m.meeting_number, m.status AS meeting_status
             FROM meeting_participants mp
             INNER JOIN meetings m ON m.id = mp.meeting_id
             WHERE mp.attendance_token = :token
               AND mp.participant_type = \'external\'
               AND m.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function ensureAttendanceToken(int $participantId): string
    {
        $stmt = $this->db()->prepare(
            'SELECT attendance_token FROM meeting_participants WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $participantId]);
        $existing = trim((string) ($stmt->fetchColumn() ?: ''));

        if ($existing !== '') {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        $update = $this->db()->prepare(
            'UPDATE meeting_participants
             SET attendance_token = :token,
                 attendance_status = \'pending\',
                 attendance_responded_at = NULL,
                 attendance_response_ip = NULL
             WHERE id = :id'
        );
        $update->execute(['token' => $token, 'id' => $participantId]);

        return $token;
    }

    public function markAttendanceEmailSent(int $participantId): void
    {
        $this->db()->prepare(
            'UPDATE meeting_participants
             SET attendance_email_sent_at = :sent_at
             WHERE id = :id'
        )->execute([
            'sent_at' => date('Y-m-d H:i:s'),
            'id' => $participantId,
        ]);
    }

    public function updateAttendanceResponse(int $participantId, string $status, ?string $ip): void
    {
        if (!in_array($status, ['confirmed', 'declined'], true)) {
            throw new \InvalidArgumentException('Estado de asistencia inválido.');
        }

        $this->db()->prepare(
            'UPDATE meeting_participants
             SET attendance_status = :status,
                 attendance_responded_at = :responded_at,
                 attendance_response_ip = :ip
             WHERE id = :id'
        )->execute([
            'status' => $status,
            'responded_at' => date('Y-m-d H:i:s'),
            'ip' => $ip,
            'id' => $participantId,
        ]);
    }

    public function resetAttendanceForMeeting(int $meetingId): void
    {
        $this->db()->prepare(
            'UPDATE meeting_participants
             SET attendance_token = NULL,
                 attendance_status = \'pending\',
                 attendance_responded_at = NULL,
                 attendance_email_sent_at = NULL,
                 attendance_response_ip = NULL
             WHERE meeting_id = :meeting_id
               AND participant_type = \'external\''
        )->execute(['meeting_id' => $meetingId]);
    }

    public function hasConfirmedExternalAttendance(int $meetingId): bool
    {
        $stmt = $this->db()->prepare(
            'SELECT 1 FROM meeting_participants
             WHERE meeting_id = :meeting_id
               AND participant_type = \'external\'
               AND attendance_status = \'confirmed\'
             LIMIT 1'
        );
        $stmt->execute(['meeting_id' => $meetingId]);

        return (bool) $stmt->fetchColumn();
    }
}
