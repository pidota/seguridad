<?php

declare(strict_types=1);

namespace App\Repositories\WomenOffice;

use Core\Database;

final class CaseDocumentRepository
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
            'SELECT d.*, u.name AS uploaded_by_name
             FROM women_case_documents d
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.case_id = :case_id AND d.deleted_at IS NULL
             ORDER BY d.uploaded_at DESC, d.id DESC'
        );
        $stmt->execute(['case_id' => $caseId]);

        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT d.*, u.name AS uploaded_by_name
             FROM women_case_documents d
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.id = :id AND d.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO women_case_documents (
                case_id, original_filename, stored_filename, storage_path,
                mime_type, file_size, uploaded_by
             ) VALUES (
                :case_id, :original_filename, :stored_filename, :storage_path,
                :mime_type, :file_size, :uploaded_by
             )'
        );
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE women_case_documents SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
    }
}
