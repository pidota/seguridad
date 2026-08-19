<?php

declare(strict_types=1);

namespace App\Services\Meetings;

use App\Repositories\Meetings\MeetingSequenceRepository;
use Core\Database;
use Core\Exceptions\HttpException;

final class MeetingNumberService
{
    public function __construct(
        private readonly MeetingSequenceRepository $sequences = new MeetingSequenceRepository()
    ) {
    }

    public function next(?int $year = null): string
    {
        $year = $year ?? (int) date('Y');
        $pdo = Database::connection();
        $started = $pdo->inTransaction();

        if (!$started) {
            $pdo->beginTransaction();
        }

        try {
            $sequence = $this->sequences->next($year);
            $number = sprintf('REU-%d-%06d', $year, $sequence);

            if (!$started) {
                $pdo->commit();
            }

            return $number;
        } catch (\Throwable $e) {
            if (!$started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw new HttpException(500, 'No fue posible generar el número de reunión.');
        }
    }
}
