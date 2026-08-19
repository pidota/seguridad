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
}
