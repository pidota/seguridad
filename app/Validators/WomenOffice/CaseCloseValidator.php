<?php

declare(strict_types=1);

namespace App\Validators\WomenOffice;

use Core\Validator;

final class CaseCloseValidator
{
    public function validate(array $data): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'closure_notes' => 'nullable|max:2000',
        ], [
            'closure_notes' => 'observaciones de cierre',
        ]);

        return $validator->firstErrors();
    }
}
