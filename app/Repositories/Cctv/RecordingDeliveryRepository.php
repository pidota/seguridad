<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

use Core\Database;

final class RecordingDeliveryRepository
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
            'INSERT INTO cctv_recording_deliveries (
                recording_request_id, delivered_at, delivered_by,
                receiver_name, receiver_rut, receiver_relationship, authorization_document,
                delivery_medium, notes, public_notes, internal_notes,
                file_internal_name, file_camera_id, file_video_date, file_time_from, file_time_to,
                file_size_bytes, file_hash_sha256
             ) VALUES (
                :recording_request_id, :delivered_at, :delivered_by,
                :receiver_name, :receiver_rut, :receiver_relationship, :authorization_document,
                :delivery_medium, :notes, :public_notes, :internal_notes,
                :file_internal_name, :file_camera_id, :file_video_date, :file_time_from, :file_time_to,
                :file_size_bytes, :file_hash_sha256
             )'
        );
        $stmt->execute([
            'recording_request_id' => $data['recording_request_id'],
            'delivered_at' => $data['delivered_at'],
            'delivered_by' => $data['delivered_by'],
            'receiver_name' => $data['receiver_name'],
            'receiver_rut' => $data['receiver_rut'],
            'receiver_relationship' => $data['receiver_relationship'] ?? null,
            'authorization_document' => $data['authorization_document'] ?? null,
            'delivery_medium' => $data['delivery_medium'],
            'notes' => $data['notes'] ?? null,
            'public_notes' => $data['public_notes'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
            'file_internal_name' => $data['file_internal_name'] ?? null,
            'file_camera_id' => $data['file_camera_id'] ?? null,
            'file_video_date' => $data['file_video_date'] ?? null,
            'file_time_from' => $data['file_time_from'] ?? null,
            'file_time_to' => $data['file_time_to'] ?? null,
            'file_size_bytes' => $data['file_size_bytes'] ?? null,
            'file_hash_sha256' => $data['file_hash_sha256'] ?? null,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function findByRequestId(int $requestId): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT d.*, u.name AS delivered_by_name, c.name AS file_camera_name
             FROM cctv_recording_deliveries d
             INNER JOIN users u ON u.id = d.delivered_by
             LEFT JOIN cctv_cameras c ON c.id = d.file_camera_id
             WHERE d.recording_request_id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $requestId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
