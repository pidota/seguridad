<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

use Core\Database;

final class RecordingRequestStatusRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allActive(): array
    {
        $stmt = $this->db()->query(
            'SELECT *
             FROM cctv_recording_request_statuses
             WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY sort_order ASC, name ASC'
        );

        return $stmt->fetchAll() ?: [];
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT *
             FROM cctv_recording_request_statuses
             WHERE slug = :slug AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
