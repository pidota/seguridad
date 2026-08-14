<?php

declare(strict_types=1);

namespace App\Validators;

use Core\Validator;

final class LoginValidator
{
    public function validate(array $data): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'email' => 'required|email|max:150',
            'password' => 'required|min:8|max:255',
        ]);

        return $validator->firstErrors();
    }
}
