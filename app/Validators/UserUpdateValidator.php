<?php

declare(strict_types=1);

namespace App\Validators;

use Core\Validator;

final class UserUpdateValidator
{
    public function validate(array $data): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'name' => 'required|min:3|max:150',
            'email' => 'required|email|max:150',
            'password' => 'nullable|min:8|max:255|confirmed',
            'role_ids' => 'required|array|min:1',
        ]);

        return $validator->firstErrors();
    }
}
