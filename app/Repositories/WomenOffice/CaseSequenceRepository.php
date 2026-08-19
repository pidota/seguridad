<?php

declare(strict_types=1);

namespace App\Repositories\WomenOffice;

use Core\Database;

final class CaseSequenceRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    /**
     * Obtiene el siguiente correlativo del año con bloqueo de fila.
     * Debe ejecutarse dentro de una transacción.
     */
    public function next(int $year): int
    {
        $ensure = $this->db()->prepare(
            'INSERT INTO women_case_sequences (year, last_number)
             VALUES (:year, 0)
             ON DUPLICATE KEY UPDATE year = year'
        );
        $ensure->execute(['year' => $year]);

        $select = $this->db()->prepare(
            'SELECT last_number
             FROM women_case_sequences
             WHERE year = :year
             FOR UPDATE'
        );
        $select->execute(['year' => $year]);
        $current = (int) $select->fetchColumn();
        $next = $current + 1;

        $update = $this->db()->prepare(
            'UPDATE women_case_sequences
             SET last_number = :number
             WHERE year = :year'
        );
        $update->execute([
            'number' => $next,
            'year' => $year,
        ]);

        return $next;
    }
}
