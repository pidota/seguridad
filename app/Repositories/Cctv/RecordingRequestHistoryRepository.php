<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

use App\Services\Cctv\RecordingHistoryEventCatalog;
use Core\Database;

final class RecordingRequestHistoryRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO cctv_recording_request_history (
                recording_request_id, event_type, previous_status, new_status, notes, changed_by
             ) VALUES (
                :recording_request_id, :event_type, :previous_status, :new_status, :notes, :changed_by
             )'
        );
        $stmt->execute([
            'recording_request_id' => $data['recording_request_id'],
            'event_type' => $data['event_type'] ?? RecordingHistoryEventCatalog::STATUS_CHANGE,
            'previous_status' => $data['previous_status'] ?? null,
            'new_status' => $data['new_status'],
            'notes' => $data['notes'] ?? null,
            'changed_by' => $data['changed_by'],
        ]);

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByRequest(int $requestId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT h.*, u.name AS changed_by_name
             FROM cctv_recording_request_history h
             INNER JOIN users u ON u.id = h.changed_by
             WHERE h.recording_request_id = :id
             ORDER BY h.created_at ASC, h.id ASC'
        );
        $stmt->execute(['id' => $requestId]);

        return $stmt->fetchAll() ?: [];
    }

    public function hasStatus(int $requestId, string $status): bool
    {
        $stmt = $this->db()->prepare(
            'SELECT 1 FROM cctv_recording_request_history
             WHERE recording_request_id = :id AND new_status = :status
             LIMIT 1'
        );
        $stmt->execute(['id' => $requestId, 'status' => $status]);

        return (bool) $stmt->fetchColumn();
    }
}
