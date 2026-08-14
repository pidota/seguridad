<?php



declare(strict_types=1);



namespace App\Repositories\Cctv;



use Core\Database;



final class LogContactRepository

{

    private function db(): \PDO

    {

        return Database::connection();

    }



    /**

     * @param array<string, mixed> $data

     */

    public function create(int $entryId, array $data): int

    {

        $sql = 'INSERT INTO cctv_log_contacts (

                    cctv_log_entry_id, contact_type, contact_name, contacted_at, notes

                ) VALUES (

                    :cctv_log_entry_id, :contact_type, :contact_name, :contacted_at, :notes

                )';



        $stmt = $this->db()->prepare($sql);

        $stmt->execute([

            'cctv_log_entry_id' => $entryId,

            'contact_type' => $data['contact_type'],

            'contact_name' => $data['contact_name'] ?? null,

            'contacted_at' => $data['contacted_at'],

            'notes' => $data['notes'] ?? null,

        ]);



        return (int) $this->db()->lastInsertId();

    }



    /**

     * @param list<array<string, mixed>> $contacts

     * @return list<int>

     */

    public function createMany(int $entryId, array $contacts): array

    {

        $ids = [];



        foreach ($contacts as $contact) {

            $ids[] = $this->create($entryId, $contact);

        }



        return $ids;

    }

    public function deleteByEntry(int $entryId): void
    {
        if ($entryId < 1) {
            return;
        }

        $stmt = $this->db()->prepare(
            'DELETE FROM cctv_log_contacts WHERE cctv_log_entry_id = :entry_id'
        );
        $stmt->execute(['entry_id' => $entryId]);
    }

    /**
     * @return list<array<string, mixed>>

     */

    public function listByEntry(int $entryId): array

    {

        $stmt = $this->db()->prepare(

            'SELECT id,

                    cctv_log_entry_id,

                    contact_type,

                    contact_name,

                    contacted_at,

                    notes,

                    created_at

             FROM cctv_log_contacts

             WHERE cctv_log_entry_id = :entry_id

             ORDER BY contacted_at ASC, id ASC'

        );

        $stmt->execute(['entry_id' => $entryId]);



        return $stmt->fetchAll() ?: [];

    }



    /**

     * @param list<int> $entryIds

     * @return array<int, list<array<string, mixed>>>

     */

    public function listByEntries(array $entryIds): array

    {

        $entryIds = array_values(array_unique(array_filter(

            array_map(static fn (mixed $id): int => (int) $id, $entryIds),

            static fn (int $id): bool => $id > 0

        )));



        if ($entryIds === []) {

            return [];

        }



        $placeholders = implode(', ', array_fill(0, count($entryIds), '?'));

        $stmt = $this->db()->prepare(

            'SELECT id,

                    cctv_log_entry_id,

                    contact_type,

                    contact_name,

                    contacted_at,

                    notes,

                    created_at

             FROM cctv_log_contacts

             WHERE cctv_log_entry_id IN (' . $placeholders . ')

             ORDER BY cctv_log_entry_id ASC, contacted_at ASC, id ASC'

        );

        $stmt->execute($entryIds);



        $grouped = [];

        foreach ($stmt->fetchAll() ?: [] as $row) {

            $entryId = (int) ($row['cctv_log_entry_id'] ?? 0);

            if ($entryId < 1) {

                continue;

            }



            $grouped[$entryId][] = $row;

        }



        return $grouped;

    }

}


