<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

use Core\Database;

final class LogEntryHistoryRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function create(array $data): int
    {
        $metadata = $data['metadata'] ?? null;
        $metadataJson = $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $this->db()->prepare(
            'INSERT INTO cctv_log_entry_history (log_entry_id, action_type, description, user_id, shift_id, metadata)
             VALUES (:log_entry_id, :action_type, :description, :user_id, :shift_id, :metadata)'
        );
        $stmt->execute([
            'log_entry_id' => $data['log_entry_id'],
            'action_type' => $data['action_type'],
            'description' => $data['description'],
            'user_id' => $data['user_id'] ?? null,
            'shift_id' => $data['shift_id'] ?? null,
            'metadata' => $metadataJson,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForEntry(int $entryId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT h.*, u.name AS user_name
             FROM cctv_log_entry_history h
             LEFT JOIN users u ON u.id = h.user_id
             WHERE h.log_entry_id = :entry_id
             ORDER BY h.created_at ASC, h.id ASC'
        );
        $stmt->execute(['entry_id' => $entryId]);
        $rows = $stmt->fetchAll() ?: [];

        foreach ($rows as &$row) {
            if (!empty($row['metadata']) && is_string($row['metadata'])) {
                $decoded = json_decode($row['metadata'], true);
                $row['metadata'] = is_array($decoded) ? $decoded : null;
            }
        }
        unset($row);

        return $rows;
    }

    public function findLastForEntry(int $entryId): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT h.*, u.name AS user_name
             FROM cctv_log_entry_history h
             LEFT JOIN users u ON u.id = h.user_id
             WHERE h.log_entry_id = :entry_id
             ORDER BY h.created_at DESC, h.id DESC
             LIMIT 1'
        );
        $stmt->execute(['entry_id' => $entryId]);
        $row = $stmt->fetch();

        if ($row && !empty($row['metadata']) && is_string($row['metadata'])) {
            $decoded = json_decode($row['metadata'], true);
            $row['metadata'] = is_array($decoded) ? $decoded : null;
        }

        return $row ?: null;
    }
}
