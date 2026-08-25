<?php

declare(strict_types=1);

namespace App\Validators\Guards;

final class ShiftStoreValidator
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    public function validate(array $payload): array
    {
        $errors = [];

        $date = trim((string) ($payload['shift_date'] ?? ''));
        if ($date === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $errors['shift_date'] = 'Indique la fecha del turno.';
        }

        return $errors;
    }
}
