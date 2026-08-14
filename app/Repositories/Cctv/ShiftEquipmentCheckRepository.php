<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

use Core\Database;

final class ShiftEquipmentCheckRepository
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
        $sql = 'INSERT INTO cctv_shift_equipment_checks (
                    cctv_shift_id, cctv_equipment_id, check_phase, status,
                    observations, checked_at, checked_by
                ) VALUES (
                    :cctv_shift_id, :cctv_equipment_id, :check_phase, :status,
                    :observations, :checked_at, :checked_by
                )';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'cctv_shift_id' => $data['cctv_shift_id'],
            'cctv_equipment_id' => $data['cctv_equipment_id'],
            'check_phase' => $data['check_phase'],
            'status' => $data['status'],
            'observations' => $data['observations'] ?? null,
            'checked_at' => $data['checked_at'],
            'checked_by' => $data['checked_by'],
        ]);

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByShiftAndPhase(int $shiftId, string $phase): array
    {
        $stmt = $this->db()->prepare(
            'SELECT c.*,
                    e.slug AS equipment_slug,
                    e.name AS equipment_name,
                    e.sort_order AS equipment_sort_order,
                    checker.name AS checked_by_name
             FROM cctv_shift_equipment_checks c
             INNER JOIN cctv_equipment e ON e.id = c.cctv_equipment_id
             INNER JOIN users checker ON checker.id = c.checked_by
             WHERE c.cctv_shift_id = :shift_id
               AND c.check_phase = :phase
             ORDER BY e.sort_order ASC, e.name ASC'
        );
        $stmt->execute([
            'shift_id' => $shiftId,
            'phase' => $phase,
        ]);

        return $stmt->fetchAll() ?: [];
    }
}
