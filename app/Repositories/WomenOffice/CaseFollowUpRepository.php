<?php

declare(strict_types=1);

namespace App\Repositories\WomenOffice;

use Core\Database;

final class CaseFollowUpRepository
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
            'SELECT f.id, f.case_id, f.follow_up_date, f.follow_up_time, f.contact_type_id,
                    f.contact_type_other, f.result_id, f.result_other, f.notes,
                    f.requires_follow_up, f.next_follow_up_date, f.created_by, f.created_at,
                    ct.slug AS contact_type_slug,
                    ct.name AS contact_type_name,
                    rt.slug AS result_slug,
                    rt.name AS result_name,
                    u.name AS created_by_name
             FROM women_case_followups f
             LEFT JOIN women_followup_contact_types ct ON ct.id = f.contact_type_id
             LEFT JOIN women_followup_results rt ON rt.id = f.result_id
             LEFT JOIN users u ON u.id = f.created_by
             WHERE f.case_id = :case_id AND f.deleted_at IS NULL
             ORDER BY f.follow_up_date DESC, f.follow_up_time DESC, f.id DESC'
        );
        $stmt->execute(['case_id' => $caseId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<array{
     *     id?: int,
     *     follow_up_date: string,
     *     follow_up_time: ?string,
     *     contact_type_id: ?int,
     *     contact_type_other: ?string,
     *     result_id: ?int,
     *     result_other: ?string,
     *     notes: ?string,
     *     requires_follow_up: int,
     *     next_follow_up_date: ?string
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
                    'UPDATE women_case_followups SET
                        follow_up_date = :follow_up_date,
                        follow_up_time = :follow_up_time,
                        contact_type_id = :contact_type_id,
                        contact_type_other = :contact_type_other,
                        result_id = :result_id,
                        result_other = :result_other,
                        notes = :notes,
                        requires_follow_up = :requires_follow_up,
                        next_follow_up_date = :next_follow_up_date,
                        updated_at = NOW()
                     WHERE id = :id AND case_id = :case_id AND deleted_at IS NULL'
                );
                $update->execute([
                    'id' => $id,
                    'case_id' => $caseId,
                    'follow_up_date' => $item['follow_up_date'],
                    'follow_up_time' => $item['follow_up_time'],
                    'contact_type_id' => $item['contact_type_id'],
                    'contact_type_other' => $item['contact_type_other'],
                    'result_id' => $item['result_id'],
                    'result_other' => $item['result_other'],
                    'notes' => $item['notes'],
                    'requires_follow_up' => $item['requires_follow_up'],
                    'next_follow_up_date' => $item['next_follow_up_date'],
                ]);
                $kept[] = $id;

                continue;
            }

            $insert = $this->db()->prepare(
                'INSERT INTO women_case_followups (
                    case_id, follow_up_date, follow_up_time, contact_type_id, contact_type_other,
                    result_id, result_other, notes, requires_follow_up, next_follow_up_date, created_by
                 ) VALUES (
                    :case_id, :follow_up_date, :follow_up_time, :contact_type_id, :contact_type_other,
                    :result_id, :result_other, :notes, :requires_follow_up, :next_follow_up_date, :created_by
                 )'
            );
            $insert->execute([
                'case_id' => $caseId,
                'follow_up_date' => $item['follow_up_date'],
                'follow_up_time' => $item['follow_up_time'],
                'contact_type_id' => $item['contact_type_id'],
                'contact_type_other' => $item['contact_type_other'],
                'result_id' => $item['result_id'],
                'result_other' => $item['result_other'],
                'notes' => $item['notes'],
                'requires_follow_up' => $item['requires_follow_up'],
                'next_follow_up_date' => $item['next_follow_up_date'],
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
            'UPDATE women_case_followups
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
            'SELECT id FROM women_case_followups WHERE case_id = :case_id AND deleted_at IS NULL'
        );
        $stmt->execute(['case_id' => $caseId]);

        $map = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $map[(int) $row['id']] = true;
        }

        return $map;
    }
}
