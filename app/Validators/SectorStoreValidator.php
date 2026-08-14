<?php

declare(strict_types=1);

namespace App\Validators;

use Core\Validator;

final class SectorStoreValidator
{
    public function validate(array $data): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'name' => 'required|min:2|max:120',
            'slug' => 'nullable|alpha_dash|max:40',
            'description' => 'nullable|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|in:0,1',
        ], [
            'sort_order' => 'orden',
        ]);

        return $validator->firstErrors();
    }
}
