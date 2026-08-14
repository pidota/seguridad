<?php

declare(strict_types=1);

namespace App\Validators\Senda;

final class FollowUpUpdateValidator
{
    public function validate(array $data): array
    {
        return (new FollowUpStoreValidator())->validate($data);
    }
}
