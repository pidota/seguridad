<?php

declare(strict_types=1);

namespace App\Validators\WomenOffice;

use App\Repositories\WomenOffice\CatalogRepository;
use App\Support\ChileanRutValidator;
use Core\Validator;

final class CaseAggressorValidator
{
    /**
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $catalogs = new CatalogRepository();
        $relationshipTypes = $catalogs->relationshipTypes();
        $relationshipIds = implode(',', array_column($relationshipTypes, 'id'));

        $rules = [
            'relationship_type_id' => 'nullable|integer',
            'relationship_other' => 'nullable|max:180',
            'current_relationship' => 'nullable|in:yes,no,unknown',
            'aggressor_first_names' => 'nullable|max:150',
            'aggressor_paternal_surname' => 'nullable|max:120',
            'aggressor_maternal_surname' => 'nullable|max:120',
            'aggressor_rut' => 'nullable|max:20',
            'aggressor_birth_date' => 'nullable|date',
            'aggressor_approximate_age' => 'nullable|max:40',
            'aggressor_phone' => 'nullable|max:30',
            'aggressor_address' => 'nullable|max:255',
            'aggressor_occupation' => 'nullable|max:150',
            'aggressor_notes' => 'nullable|max:5000',
        ];

        if ($relationshipIds !== '') {
            $rules['relationship_type_id'] .= '|in:' . $relationshipIds;
        }

        $validator = new Validator();
        $validator->validate($data, $rules, [
            'relationship_type_id' => 'relación con la persona afectada',
            'relationship_other' => 'especifique relación',
            'current_relationship' => 'relación actual',
            'aggressor_first_names' => 'nombres',
            'aggressor_paternal_surname' => 'apellido paterno',
            'aggressor_maternal_surname' => 'apellido materno',
            'aggressor_rut' => 'RUT',
            'aggressor_birth_date' => 'fecha de nacimiento',
            'aggressor_approximate_age' => 'edad aproximada',
            'aggressor_phone' => 'teléfono',
            'aggressor_address' => 'domicilio',
            'aggressor_occupation' => 'ocupación',
            'aggressor_notes' => 'observaciones',
        ]);

        $errors = $validator->firstErrors();

        $relationshipId = (int) ($data['relationship_type_id'] ?? 0);
        if ($relationshipId > 0) {
            $slug = $catalogs->relationshipTypeSlug($relationshipId);
            if ($slug === 'otro' && trim((string) ($data['relationship_other'] ?? '')) === '') {
                $errors['relationship_other'] = 'Especifique la relación.';
            }
        }

        $rut = trim((string) ($data['aggressor_rut'] ?? ''));
        if ($rut !== '' && ChileanRutValidator::normalize($rut) === null) {
            $errors['aggressor_rut'] = 'El RUT ingresado no es válido.';
        }

        $birthDate = trim((string) ($data['aggressor_birth_date'] ?? ''));
        if ($birthDate !== '') {
            $birth = \DateTimeImmutable::createFromFormat('Y-m-d', $birthDate);
            if (!$birth instanceof \DateTimeImmutable) {
                $errors['aggressor_birth_date'] = 'La fecha de nacimiento no es válida.';
            } elseif ($birth > new \DateTimeImmutable('today')) {
                $errors['aggressor_birth_date'] = 'La fecha de nacimiento no puede ser futura.';
            }
        }

        return $errors;
    }
}
