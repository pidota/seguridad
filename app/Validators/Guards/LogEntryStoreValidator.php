<?php

declare(strict_types=1);

namespace App\Validators\Guards;

final class LogEntryStoreValidator
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    public function validate(array $payload): array
    {
        $errors = [];

        if ((int) ($payload['log_type_id'] ?? 0) < 1) {
            $errors['log_type_id'] = 'Seleccione el tipo de novedad.';
        }

        $date = trim((string) ($payload['event_date'] ?? ''));
        if ($date === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $errors['event_date'] = 'Indique la fecha del suceso.';
        }

        $time = trim((string) ($payload['event_time'] ?? ''));
        if ($time === '' || preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time) !== 1) {
            $errors['event_time'] = 'Indique la hora del suceso.';
        }

        if (trim((string) ($payload['observations'] ?? '')) === '') {
            $errors['observations'] = 'Describa la novedad.';
        }

        return $errors;
    }
}
