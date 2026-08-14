<?php

declare(strict_types=1);

namespace App\Validators\Senda;

use App\Support\ChileanRutValidator;
use Core\Validator;

final class PersonLookupValidator
{
    public function validate(array $data): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'rut' => 'required|max:20',
        ], [
            'rut' => 'RUT',
        ]);

        $errors = $validator->firstErrors();

        if ($errors === [] && !ChileanRutValidator::isValid((string) ($data['rut'] ?? ''))) {
            $errors['rut'] = 'Ingrese un RUT chileno válido, por ejemplo 12.345.678-5 o 12345678-5.';
        }

        return $errors;
    }
}
