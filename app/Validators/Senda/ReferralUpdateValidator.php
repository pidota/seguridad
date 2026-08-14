<?php

declare(strict_types=1);

namespace App\Validators\Senda;

final class ReferralUpdateValidator
{
    public function validate(array $data): array
    {
        return (new ReferralStoreValidator())->validate($data);
    }
}
