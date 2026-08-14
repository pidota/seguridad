<?php

declare(strict_types=1);

namespace App\Validators\Senda;

use App\Support\ChileanRutValidator;
use Core\Validator;

final class FollowUpSearchValidator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $rut = trim((string) ($data['rut'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));

        if ($rut === '' && $name === '') {
            return ['rut' => 'Ingrese un RUT o un nombre para buscar.'];
        }

        if ($rut === '') {
            return [];
        }

        $validator = new Validator();
        $validator->validate(['rut' => $rut], [
            'rut' => 'required|max:20',
        ], [
            'rut' => 'RUT',
        ]);

        $errors = $validator->firstErrors();

        if ($errors === [] && !ChileanRutValidator::isValid($rut)) {
            $errors['rut'] = 'Ingrese un RUT chileno válido, por ejemplo 12.345.678-5 o 12345678-5.';
        }

        return $errors;
    }
}
