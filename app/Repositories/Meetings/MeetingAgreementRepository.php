<?php

declare(strict_types=1);

namespace App\Repositories\Meetings;

use Core\Database;

final class MeetingAgreementRepository
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
            'SELECT ma.*, u.name AS responsible_user_name
             FROM meeting_agreements ma
             LEFT JOIN users u ON u.id = ma.responsible_user_id
             WHERE ma.meeting_id = :meeting_id
             ORDER BY ma.position ASC, ma.id ASC'
        );
        $stmt->execute(['meeting_id' => $meetingId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    public function sync(int $meetingId, array $items): void
    {
        $this->db()->prepare('DELETE FROM meeting_agreements WHERE meeting_id = :id')
            ->execute(['id' => $meetingId]);

        if ($items === []) {
            return;
        }

        $stmt = $this->db()->prepare(
            'INSERT INTO meeting_agreements (
                meeting_id, position, description,
                responsible_user_id, responsible_text, due_date
             ) VALUES (
                :meeting_id, :position, :description,
                :responsible_user_id, :responsible_text, :due_date
             )'
        );

        foreach ($items as $item) {
            $stmt->execute([
                'meeting_id' => $meetingId,
                'position' => $item['position'],
                'description' => $item['description'],
                'responsible_user_id' => $item['responsible_user_id'],
                'responsible_text' => $item['responsible_text'],
                'due_date' => $item['due_date'],
            ]);
        }
    }
}
