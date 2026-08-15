<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

use App\Services\Cctv\CameraReviewStatusCatalog;
use Core\Database;

final class RecordingRequestCameraRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    /**
     * @param list<int> $cameraIds
     */
    public function syncForRequest(int $requestId, array $cameraIds, int $primaryCameraId = 0): void
    {
        $cameraIds = array_values(array_unique(array_filter(array_map('intval', $cameraIds))));
        if ($primaryCameraId > 0 && !in_array($primaryCameraId, $cameraIds, true)) {
            array_unshift($cameraIds, $primaryCameraId);
        }

        if ($cameraIds === []) {
            return;
        }

        $existing = $this->listByRequest($requestId);
        $existingIds = array_map(static fn (array $row): int => (int) $row['camera_id'], $existing);

        foreach ($cameraIds as $cameraId) {
            if (in_array($cameraId, $existingIds, true)) {
                continue;
            }

            $this->create($requestId, $cameraId);
        }
    }

    public function create(int $requestId, int $cameraId): int
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO cctv_recording_request_cameras (recording_request_id, camera_id, review_status)
             VALUES (:request_id, :camera_id, :review_status)'
        );
        $stmt->execute([
            'request_id' => $requestId,
            'camera_id' => $cameraId,
            'review_status' => CameraReviewStatusCatalog::PENDING,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByRequest(int $requestId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT rc.*, c.name AS camera_name, c.code AS camera_code, u.name AS reviewed_by_name
             FROM cctv_recording_request_cameras rc
             INNER JOIN cctv_cameras c ON c.id = rc.camera_id
             LEFT JOIN users u ON u.id = rc.reviewed_by
             WHERE rc.recording_request_id = :id
             ORDER BY c.name ASC'
        );
        $stmt->execute(['id' => $requestId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE cctv_recording_request_cameras
             SET review_status = :review_status,
                 reviewed_by = :reviewed_by,
                 reviewed_at = :reviewed_at,
                 notes = :notes,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'review_status' => $data['review_status'],
            'reviewed_by' => $data['reviewed_by'] ?? null,
            'reviewed_at' => $data['reviewed_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM cctv_recording_request_cameras WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
