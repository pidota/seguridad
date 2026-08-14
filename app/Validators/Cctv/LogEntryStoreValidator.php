<?php

declare(strict_types=1);

namespace App\Validators\Cctv;

use Core\Validator;

final class LogEntryStoreValidator
{
    /**
     * @param list<int> $logTypeIds
     */
    public function __construct(
        private readonly array $logTypeIds
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $allowedTypes = implode(',', array_map('strval', $this->logTypeIds));

        $validator = new Validator();
        $validator->validate($data, [
            'event_date' => 'required',
            'event_time' => 'required',
            'log_type_id' => 'required|in:' . $allowedTypes,
            'camera_id' => 'nullable|integer',
            'sector_id' => 'nullable|integer',
            'observations' => 'required|min:3|max:5000',
        ], [
            'event_date' => 'fecha',
            'event_time' => 'hora',
            'log_type_id' => 'tipo de registro',
            'camera_id' => 'cámara',
            'sector_id' => 'sector',
            'observations' => 'observaciones',
        ]);

        $errors = $validator->firstErrors();

        if ($errors === [] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) ($data['event_date'] ?? '')))) {
            $errors['event_date'] = 'Indique una fecha válida.';
        }

        if ($errors === [] && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', trim((string) ($data['event_time'] ?? '')))) {
            $errors['event_time'] = 'Indique una hora válida.';
        }

        return $errors;
    }
}
