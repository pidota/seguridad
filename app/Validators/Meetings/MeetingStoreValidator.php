<?php

declare(strict_types=1);

namespace App\Validators\Meetings;

use Core\Validator;

final class MeetingStoreValidator
{
    public function validate(array $data): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'meeting_date' => 'required|date',
            'meeting_time' => 'required',
            'meeting_place' => 'required|min:2|max:255',
            'additional_notes' => 'nullable|max:5000',
            'next_meeting_required' => 'nullable|in:yes,no',
            'next_meeting_date' => 'nullable|date',
            'next_meeting_time' => 'nullable',
            'next_meeting_notes' => 'nullable|max:2000',
        ], [
            'meeting_date' => 'fecha',
            'meeting_time' => 'hora',
            'meeting_place' => 'lugar',
            'additional_notes' => 'observaciones',
            'next_meeting_required' => 'próxima reunión',
            'next_meeting_date' => 'fecha próxima reunión',
            'next_meeting_time' => 'hora próxima reunión',
            'next_meeting_notes' => 'observaciones próxima reunión',
        ]);

        $errors = $validator->firstErrors();

        if (trim((string) ($data['next_meeting_required'] ?? '')) === 'yes'
            && trim((string) ($data['next_meeting_date'] ?? '')) === '') {
            $errors['next_meeting_date'] = 'Indique la fecha de la próxima reunión o seguimiento.';
        }

        return $errors;
    }
}
