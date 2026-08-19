<?php

declare(strict_types=1);

namespace App\Validators\WomenOffice;

use Core\Validator;

final class CaseCancelValidator
{
    public function validate(array $data): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'cancellation_reason' => 'required|min:10|max:2000',
        ], [
            'cancellation_reason' => 'motivo de anulación',
        ]);

        return $validator->firstErrors();
    }
}
