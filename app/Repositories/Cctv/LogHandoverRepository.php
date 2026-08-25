<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

use App\Models\Cctv\LogHandover;
use Core\Database;

final class LogHandoverRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO cctv_log_handovers (
                log_entry_id, from_shift_id, to_shift_id, from_operator_id, to_operator_id,
                handover_status, handover_notes, decision, decision_reason, decision_details,
                approx_completion_time, delegated_institution, handed_over_at, reviewed_at, reviewed_by
             ) VALUES (
                :log_entry_id, :from_shift_id, :to_shift_id, :from_operator_id, :to_operator_id,
                :handover_status, :handover_notes, :decision, :decision_reason, :decision_details,
                :approx_completion_time, :delegated_institution, :handed_over_at, :reviewed_at, :reviewed_by
             )'
        );
        $stmt->execute([
            'log_entry_id' => $data['log_entry_id'],
            'from_shift_id' => $data['from_shift_id'],
            'to_shift_id' => $data['to_shift_id'] ?? null,
            'from_operator_id' => $data['from_operator_id'],
            'to_operator_id' => $data['to_operator_id'] ?? null,
            'handover_status' => $data['handover_status'] ?? LogHandover::STATUS_PENDING,
            'handover_notes' => $data['handover_notes'] ?? null,
            'decision' => $data['decision'] ?? null,
            'decision_reason' => $data['decision_reason'] ?? null,
            'decision_details' => $data['decision_details'] ?? null,
            'approx_completion_time' => $data['approx_completion_time'] ?? null,
            'delegated_institution' => $data['delegated_institution'] ?? null,
            'handed_over_at' => $data['handed_over_at'],
            'reviewed_at' => $data['reviewed_at'] ?? null,
            'reviewed_by' => $data['reviewed_by'] ?? null,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare(
            $this->selectSql() . ' WHERE h.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findPendingForShift(int $shiftId, int $operatorId): ?array
    {
        $stmt = $this->db()->prepare(
            $this->selectSql() . '
             WHERE h.to_shift_id = :shift_id
               AND h.to_operator_id = :operator_id
               AND h.handover_status = :status
             LIMIT 1'
        );
        $stmt->execute([
            'shift_id' => $shiftId,
            'operator_id' => $operatorId,
            'status' => LogHandover::STATUS_PENDING,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function countUnreviewedForShift(int $shiftId, int $operatorId): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*)
             FROM cctv_log_handovers
             WHERE to_shift_id = :shift_id
               AND to_operator_id = :operator_id
               AND handover_status = :status'
        );
        $stmt->execute([
            'shift_id' => $shiftId,
            'operator_id' => $operatorId,
            'status' => LogHandover::STATUS_PENDING,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPendingForShift(int $shiftId, int $operatorId): array
    {
        $stmt = $this->db()->prepare(
            $this->selectSql() . '
             WHERE h.to_shift_id = :shift_id
               AND h.to_operator_id = :operator_id
               AND h.handover_status = :status
             ORDER BY e.occurred_at DESC, h.id DESC'
        );
        $stmt->execute([
            'shift_id' => $shiftId,
            'operator_id' => $operatorId,
            'status' => LogHandover::STATUS_PENDING,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForEntry(int $entryId): array
    {
        $stmt = $this->db()->prepare(
            $this->selectSql() . '
             WHERE h.log_entry_id = :entry_id
             ORDER BY h.handed_over_at ASC, h.id ASC'
        );
        $stmt->execute(['entry_id' => $entryId]);

        return $stmt->fetchAll() ?: [];
    }

    public function assignUnassignedPending(int $shiftId, int $operatorId): int
    {
        $stmt = $this->db()->prepare(
            'UPDATE cctv_log_handovers
             SET to_shift_id = :shift_id,
                 to_operator_id = :operator_id,
                 updated_at = NOW()
             WHERE handover_status = :status
               AND to_shift_id IS NULL'
        );
        $stmt->execute([
            'shift_id' => $shiftId,
            'operator_id' => $operatorId,
            'status' => LogHandover::STATUS_PENDING,
        ]);

        return $stmt->rowCount();
    }

    public function updateReview(int $id, array $data): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE cctv_log_handovers
             SET handover_status = :handover_status,
                 decision = :decision,
                 decision_reason = :decision_reason,
                 decision_details = :decision_details,
                 approx_completion_time = :approx_completion_time,
                 delegated_institution = :delegated_institution,
                 reviewed_at = :reviewed_at,
                 reviewed_by = :reviewed_by,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'handover_status' => $data['handover_status'],
            'decision' => $data['decision'] ?? null,
            'decision_reason' => $data['decision_reason'] ?? null,
            'decision_details' => $data['decision_details'] ?? null,
            'approx_completion_time' => $data['approx_completion_time'] ?? null,
            'delegated_institution' => $data['delegated_institution'] ?? null,
            'reviewed_at' => $data['reviewed_at'],
            'reviewed_by' => $data['reviewed_by'],
        ]);
    }

    public function hasOpenPendingForEntry(int $entryId): bool
    {
        $stmt = $this->db()->prepare(
            'SELECT 1 FROM cctv_log_handovers
             WHERE log_entry_id = :entry_id AND handover_status = :status
             LIMIT 1'
        );
        $stmt->execute([
            'entry_id' => $entryId,
            'status' => LogHandover::STATUS_PENDING,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function findOpenPendingForOperator(int $entryId, int $operatorId): ?array
    {
        $stmt = $this->db()->prepare(
            $this->selectSql() . '
             WHERE h.log_entry_id = :entry_id
               AND h.to_operator_id = :operator_id
               AND h.handover_status = :status
             LIMIT 1'
        );
        $stmt->execute([
            'entry_id' => $entryId,
            'operator_id' => $operatorId,
            'status' => LogHandover::STATUS_PENDING,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function selectSql(): string
    {
        return 'SELECT h.*,
                       e.occurred_at, e.status AS entry_status, e.observations AS entry_observations,
                       e.cctv_shift_id AS entry_shift_id, e.created_by AS entry_created_by,
                       lt.slug AS log_type_slug, lt.name AS log_type_name,
                       it.name AS incident_type_name, e.incident_type_other,
                       s.name AS sector_name,
                       cam.name AS camera_name, cam.code AS camera_code,
                       u_from.name AS from_operator_name,
                       u_to.name AS to_operator_name,
                       u_reviewer.name AS reviewed_by_name,
                       u_creator.name AS entry_creator_name,
                       fs.started_at AS from_shift_started_at, fs.ended_at AS from_shift_ended_at,
                       ts.started_at AS to_shift_started_at, ts.ended_at AS to_shift_ended_at
                FROM cctv_log_handovers h
                INNER JOIN cctv_log_entries e ON e.id = h.log_entry_id AND e.deleted_at IS NULL
                INNER JOIN cctv_log_types lt ON lt.id = e.cctv_log_type_id
                LEFT JOIN cctv_incident_types it ON it.id = e.cctv_incident_type_id
                LEFT JOIN sectors s ON s.id = e.sector_id
                LEFT JOIN cctv_cameras cam ON cam.id = e.cctv_camera_id
                LEFT JOIN users u_from ON u_from.id = h.from_operator_id
                LEFT JOIN users u_to ON u_to.id = h.to_operator_id
                LEFT JOIN users u_reviewer ON u_reviewer.id = h.reviewed_by
                LEFT JOIN users u_creator ON u_creator.id = e.created_by
                LEFT JOIN cctv_shifts fs ON fs.id = h.from_shift_id
                LEFT JOIN cctv_shifts ts ON ts.id = h.to_shift_id';
    }
}
