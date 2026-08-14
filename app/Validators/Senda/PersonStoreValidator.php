<?php

declare(strict_types=1);

namespace App\Validators\Senda;

use App\Support\ChileanRutValidator;
use Core\Validator;

final class PersonStoreValidator
{
    public function validate(array $data): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'first_names' => 'required|min:2|max:150',
            'paternal_surname' => 'required|min:2|max:120',
            'maternal_surname' => 'nullable|max:120',
            'rut' => 'required|max:20',
            'birth_date' => 'required|date',
            'address' => 'nullable|max:255',
            'phone' => 'nullable|max:30',
            'email' => 'nullable|email|max:150',
            'education' => 'nullable|max:150',
            'occupation' => 'nullable|max:150',
        ], [
            'first_names' => 'nombres',
            'paternal_surname' => 'apellido paterno',
            'maternal_surname' => 'apellido materno',
            'rut' => 'RUT',
            'birth_date' => 'fecha de nacimiento',
            'address' => 'dirección',
            'phone' => 'teléfono',
            'email' => 'correo electrónico',
            'education' => 'escolaridad',
            'occupation' => 'ocupación',
        ]);

        $errors = $validator->firstErrors();

        if (!isset($errors['rut']) && !ChileanRutValidator::isValid((string) ($data['rut'] ?? ''))) {
            $errors['rut'] = 'Ingrese un RUT chileno válido, por ejemplo 12.345.678-5 o 12345678-5.';
        }

        return $errors;
    }
}
