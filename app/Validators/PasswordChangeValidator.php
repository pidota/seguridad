<?php

declare(strict_types=1);

namespace App\Validators;

use Core\Validator;

final class PasswordChangeValidator
{
    public function validate(array $data): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'current_password' => 'required|min:8|max:255',
            'password' => 'required|min:8|max:255|confirmed',
        ]);

        $errors = $validator->firstErrors();

        if (
            empty($errors['password'])
            && isset($data['password'], $data['current_password'])
            && hash_equals((string) $data['current_password'], (string) $data['password'])
        ) {
            $errors['password'] = 'La nueva contraseña debe ser distinta a la actual.';
        }

        return $errors;
    }
}
