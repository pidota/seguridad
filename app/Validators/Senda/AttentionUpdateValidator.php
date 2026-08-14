<?php

declare(strict_types=1);

namespace App\Validators\Senda;

final class AttentionUpdateValidator
{
    public function validate(array $data): array
    {
        return (new AttentionStoreValidator())->validate($data);
    }
}
