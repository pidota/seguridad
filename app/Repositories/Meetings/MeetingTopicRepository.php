<?php

declare(strict_types=1);

namespace App\Repositories\Meetings;

use Core\Database;

final class MeetingTopicRepository
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
            'SELECT * FROM meeting_topics
             WHERE meeting_id = :meeting_id
             ORDER BY position ASC, id ASC'
        );
        $stmt->execute(['meeting_id' => $meetingId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<array{position: int, description: string}> $items
     */
    public function sync(int $meetingId, array $items): void
    {
        $this->db()->prepare('DELETE FROM meeting_topics WHERE meeting_id = :id')
            ->execute(['id' => $meetingId]);

        if ($items === []) {
            return;
        }

        $stmt = $this->db()->prepare(
            'INSERT INTO meeting_topics (meeting_id, position, description)
             VALUES (:meeting_id, :position, :description)'
        );

        foreach ($items as $item) {
            $stmt->execute([
                'meeting_id' => $meetingId,
                'position' => $item['position'],
                'description' => $item['description'],
            ]);
        }
    }
}
