<?php

declare(strict_types=1);

namespace App\Validators;

use Core\Validator;

final class ForgotPasswordValidator
{
    public function validate(array $data): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'email' => 'required|email|max:150',
        ]);

        return $validator->firstErrors();
    }
}
