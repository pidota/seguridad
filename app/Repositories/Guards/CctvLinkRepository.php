<?php

declare(strict_types=1);

namespace App\Repositories\Guards;

use App\Models\Cctv\LogContact;
use App\Models\Guards\LogEntry;
use Core\Database;

final class CctvLinkRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    /**
     * Incidentes CCTV donde se notificó a guardias municipales.
     *
     * @return list<array<string, mixed>>
     */
    public function listIncomingCoordinations(int $limit = 15): array
    {
        $limit = max(1, min($limit, 50));
        $stmt = $this->db()->prepare(
            'SELECT e.id AS cctv_log_entry_id,
                    e.occurred_at,
                    e.observations,
                    e.sector_id,
                    sector.name AS sector_name,
                    lt.name AS log_type_name,
                    lt.slug AS log_type_slug,
                    operator.name AS cctv_operator_name,
                    shift.id AS cctv_shift_id,
                    lc.contacted_at,
                    lc.contact_name,
                    lc.notes AS contact_notes
             FROM cctv_log_contacts lc
             INNER JOIN cctv_log_entries e ON e.id = lc.cctv_log_entry_id AND e.deleted_at IS NULL
             INNER JOIN cctv_log_types lt ON lt.id = e.cctv_log_type_id
             INNER JOIN cctv_shifts shift ON shift.id = e.cctv_shift_id AND shift.deleted_at IS NULL
             INNER JOIN users operator ON operator.id = shift.operator_id
             LEFT JOIN sectors sector ON sector.id = e.sector_id
             WHERE lc.contact_type = :contact_type
             ORDER BY e.occurred_at DESC, e.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute(['contact_type' => LogContact::TYPE_GUARDIAS_MUNICIPALES]);

        return $stmt->fetchAll() ?: [];
    }

    public function countIncomingCoordinations(): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(DISTINCT e.id)
             FROM cctv_log_contacts lc
             INNER JOIN cctv_log_entries e ON e.id = lc.cctv_log_entry_id AND e.deleted_at IS NULL
             WHERE lc.contact_type = :contact_type'
        );
        $stmt->execute(['contact_type' => LogContact::TYPE_GUARDIAS_MUNICIPALES]);

        return (int) $stmt->fetchColumn();
    }

    public function findCctvEntryByGuardEntry(int $guardLogEntryId): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT e.id, e.occurred_at, e.observations, lt.name AS log_type_name
             FROM cctv_log_entries e
             INNER JOIN cctv_log_types lt ON lt.id = e.cctv_log_type_id
             WHERE e.related_entity_type = :entity_type
               AND e.related_entity_id = :entity_id
               AND e.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'entity_type' => LogEntry::RELATED_ENTITY_TYPE,
            'entity_id' => $guardLogEntryId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
