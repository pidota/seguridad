<?php

declare(strict_types=1);

namespace App\Validators\WomenOffice;

use Core\Validator;

final class PersonUpdateValidator
{
    public function validate(array $data): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'first_names' => 'required|min:2|max:150',
            'paternal_surname' => 'required|min:2|max:120',
            'maternal_surname' => 'nullable|max:120',
            'birth_date' => 'required|date',
            'address' => 'nullable|max:255',
            'phone' => 'nullable|max:30',
            'email' => 'nullable|email|max:150',
            'nationality' => 'nullable|max:80',
            'occupation' => 'nullable|max:150',
            'sector_id' => 'nullable|integer',
            'education_level_id' => 'nullable|integer',
            'safe_contact' => 'nullable|in:yes,no,restricted',
            'safe_contact_notes' => 'nullable|max:2000',
        ], [
            'first_names' => 'nombres',
            'paternal_surname' => 'apellido paterno',
            'maternal_surname' => 'apellido materno',
            'birth_date' => 'fecha de nacimiento',
            'address' => 'dirección',
            'phone' => 'teléfono',
            'email' => 'correo electrónico',
            'nationality' => 'nacionalidad',
            'occupation' => 'ocupación',
            'sector_id' => 'sector',
            'education_level_id' => 'nivel educacional',
            'safe_contact' => 'contacto seguro',
            'safe_contact_notes' => 'indicaciones de contacto seguro',
        ]);

        $errors = $validator->firstErrors();

        $safeContact = trim((string) ($data['safe_contact'] ?? ''));
        if ($safeContact === 'restricted' && trim((string) ($data['safe_contact_notes'] ?? '')) === '') {
            $errors['safe_contact_notes'] = 'Indique las indicaciones de contacto seguro.';
        }

        return $errors;
    }
}
