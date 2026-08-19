<?php

declare(strict_types=1);

namespace App\Services\WomenOffice;

use App\Repositories\WomenOffice\CaseSequenceRepository;
use Core\Database;
use Core\Exceptions\HttpException;

final class WomenCaseNumberService
{
    public function __construct(
        private readonly CaseSequenceRepository $sequences = new CaseSequenceRepository()
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
            $number = sprintf('MUJER-%d-%06d', $year, $sequence);

            if (!$started) {
                $pdo->commit();
            }

            return $number;
        } catch (\Throwable $e) {
            if (!$started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw new HttpException(500, 'No fue posible generar el número de caso.');
        }
    }
}
