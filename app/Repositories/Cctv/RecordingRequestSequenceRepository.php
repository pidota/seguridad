<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

use Core\Database;

final class RecordingRequestSequenceRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function next(int $year): int
    {
        $ensure = $this->db()->prepare(
            'INSERT INTO cctv_recording_request_sequences (year, last_number)
             VALUES (:year, 0)
             ON DUPLICATE KEY UPDATE year = year'
        );
        $ensure->execute(['year' => $year]);

        $select = $this->db()->prepare(
            'SELECT last_number
             FROM cctv_recording_request_sequences
             WHERE year = :year
             FOR UPDATE'
        );
        $select->execute(['year' => $year]);
        $current = (int) $select->fetchColumn();
        $next = $current + 1;

        $update = $this->db()->prepare(
            'UPDATE cctv_recording_request_sequences
             SET last_number = :number
             WHERE year = :year'
        );
        $update->execute([
            'number' => $next,
            'year' => $year,
        ]);

        return $next;
    }

    public function formatNumber(int $year, int $sequence): string
    {
        return sprintf('CCTV-GRAB-%d-%06d', $year, $sequence);
    }
}
