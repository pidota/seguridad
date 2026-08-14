<?php

declare(strict_types=1);

namespace App\Validators;

use Core\Validator;

final class RoleStoreValidator
{
    public function validate(array $data): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'name' => 'required|min:3|max:100',
            'slug' => 'nullable|alpha_dash|max:50',
            'description' => 'nullable|max:255',
            'permission_ids' => 'nullable|array',
        ]);

        return $validator->firstErrors();
    }
}
