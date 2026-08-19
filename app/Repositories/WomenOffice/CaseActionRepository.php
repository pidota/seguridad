<?php

declare(strict_types=1);

namespace App\Repositories\WomenOffice;

use Core\Database;

final class CaseActionRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCase(int $caseId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT a.id, a.case_id, a.action_date, a.action_time, a.action_type_id,
                    a.description, a.institution, a.created_by, a.created_at,
                    at.slug AS action_type_slug,
                    at.name AS action_type_name,
                    u.name AS created_by_name
             FROM women_case_actions a
             INNER JOIN women_action_types at ON at.id = a.action_type_id
             LEFT JOIN users u ON u.id = a.created_by
             WHERE a.case_id = :case_id AND a.deleted_at IS NULL
             ORDER BY a.action_date DESC, a.action_time DESC, a.id DESC'
        );
        $stmt->execute(['case_id' => $caseId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<array{
     *     id?: int,
     *     action_date: string,
     *     action_time: ?string,
     *     action_type_id: int,
     *     description: ?string,
     *     institution: ?string
     * }> $items
     */
    public function sync(int $caseId, array $items, int $actorId): void
    {
        $existing = $this->existingIds($caseId);
        $kept = [];

        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);

            if ($id > 0 && isset($existing[$id])) {
                $update = $this->db()->prepare(
                    'UPDATE women_case_actions SET
                        action_date = :action_date,
                        action_time = :action_time,
                        action_type_id = :action_type_id,
                        description = :description,
                        institution = :institution,
                        updated_at = NOW()
                     WHERE id = :id AND case_id = :case_id AND deleted_at IS NULL'
                );
                $update->execute([
                    'id' => $id,
                    'case_id' => $caseId,
                    'action_date' => $item['action_date'],
                    'action_time' => $item['action_time'],
                    'action_type_id' => $item['action_type_id'],
                    'description' => $item['description'],
                    'institution' => $item['institution'],
                ]);
                $kept[] = $id;

                continue;
            }

            $insert = $this->db()->prepare(
                'INSERT INTO women_case_actions (
                    case_id, action_date, action_time, action_type_id, description, institution, created_by
                 ) VALUES (
                    :case_id, :action_date, :action_time, :action_type_id, :description, :institution, :created_by
                 )'
            );
            $insert->execute([
                'case_id' => $caseId,
                'action_date' => $item['action_date'],
                'action_time' => $item['action_time'],
                'action_type_id' => $item['action_type_id'],
                'description' => $item['description'],
                'institution' => $item['institution'],
                'created_by' => $actorId,
            ]);
            $kept[] = (int) $this->db()->lastInsertId();
        }

        $remove = array_diff(array_keys($existing), $kept);
        if ($remove === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($remove), '?'));
        $delete = $this->db()->prepare(
            'UPDATE women_case_actions
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE case_id = ? AND deleted_at IS NULL AND id IN (' . $placeholders . ')'
        );
        $delete->execute(array_merge([$caseId], array_values($remove)));
    }

    /**
     * @return array<int, true>
     */
    private function existingIds(int $caseId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT id FROM women_case_actions WHERE case_id = :case_id AND deleted_at IS NULL'
        );
        $stmt->execute(['case_id' => $caseId]);

        $map = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $map[(int) $row['id']] = true;
        }

        return $map;
    }
}
