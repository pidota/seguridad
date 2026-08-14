<?php

declare(strict_types=1);

namespace App\Validators\Senda;

final class PersonUpdateValidator
{
    public function validate(array $data): array
    {
        return (new PersonStoreValidator())->validate($data);
    }
}
