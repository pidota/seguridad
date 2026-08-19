<?php

declare(strict_types=1);

namespace App\Repositories\WomenOffice;

use Core\Database;

final class CaseReferralRepository
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
            'SELECT r.id, r.case_id, r.referral_date, r.institution_id, r.program_area,
                    r.reason, r.contact_person, r.referral_status_id, r.notes,
                    r.created_by, r.created_at,
                    ri.slug AS institution_slug,
                    ri.name AS institution_name,
                    rs.slug AS referral_status_slug,
                    rs.name AS referral_status_name,
                    u.name AS created_by_name
             FROM women_case_referrals r
             LEFT JOIN women_referral_institutions ri ON ri.id = r.institution_id
             INNER JOIN women_referral_statuses rs ON rs.id = r.referral_status_id
             LEFT JOIN users u ON u.id = r.created_by
             WHERE r.case_id = :case_id AND r.deleted_at IS NULL
             ORDER BY r.referral_date DESC, r.id DESC'
        );
        $stmt->execute(['case_id' => $caseId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<array{
     *     id?: int,
     *     referral_date: string,
     *     institution_id: ?int,
     *     program_area: ?string,
     *     reason: ?string,
     *     contact_person: ?string,
     *     referral_status_id: int,
     *     notes: ?string
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
                    'UPDATE women_case_referrals SET
                        referral_date = :referral_date,
                        institution_id = :institution_id,
                        program_area = :program_area,
                        reason = :reason,
                        contact_person = :contact_person,
                        referral_status_id = :referral_status_id,
                        notes = :notes,
                        updated_at = NOW()
                     WHERE id = :id AND case_id = :case_id AND deleted_at IS NULL'
                );
                $update->execute([
                    'id' => $id,
                    'case_id' => $caseId,
                    'referral_date' => $item['referral_date'],
                    'institution_id' => $item['institution_id'],
                    'program_area' => $item['program_area'],
                    'reason' => $item['reason'],
                    'contact_person' => $item['contact_person'],
                    'referral_status_id' => $item['referral_status_id'],
                    'notes' => $item['notes'],
                ]);
                $kept[] = $id;

                continue;
            }

            $insert = $this->db()->prepare(
                'INSERT INTO women_case_referrals (
                    case_id, referral_date, institution_id, program_area, reason,
                    contact_person, referral_status_id, notes, created_by
                 ) VALUES (
                    :case_id, :referral_date, :institution_id, :program_area, :reason,
                    :contact_person, :referral_status_id, :notes, :created_by
                 )'
            );
            $insert->execute([
                'case_id' => $caseId,
                'referral_date' => $item['referral_date'],
                'institution_id' => $item['institution_id'],
                'program_area' => $item['program_area'],
                'reason' => $item['reason'],
                'contact_person' => $item['contact_person'],
                'referral_status_id' => $item['referral_status_id'],
                'notes' => $item['notes'],
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
            'UPDATE women_case_referrals
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
            'SELECT id FROM women_case_referrals WHERE case_id = :case_id AND deleted_at IS NULL'
        );
        $stmt->execute(['case_id' => $caseId]);

        $map = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $map[(int) $row['id']] = true;
        }

        return $map;
    }
}
