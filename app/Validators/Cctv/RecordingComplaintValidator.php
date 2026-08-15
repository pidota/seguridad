<?php

declare(strict_types=1);

namespace App\Validators\Cctv;

use Core\Validator;

final class RecordingComplaintValidator
{
    public function validate(array $data): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'complaint_institution' => 'required|max:80',
            'complaint_number' => 'required|max:120',
            'complaint_date' => 'required|date',
            'complaint_observations' => 'nullable|max:5000',
        ], [
            'complaint_institution' => 'institución de la denuncia',
            'complaint_number' => 'número de denuncia',
            'complaint_date' => 'fecha de la denuncia',
        ]);

        return $validator->firstErrors();
    }
}
