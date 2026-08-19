<?php

declare(strict_types=1);

namespace App\Validators\WomenOffice;

use App\Repositories\WomenOffice\CatalogRepository;
use Core\Validator;

final class CaseSupportValidator
{
    /**
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $catalogs = new CatalogRepository();
        $measureTypeIds = implode(',', array_column($catalogs->protectiveMeasureTypes(), 'id'));
        $ageRangeIds = implode(',', array_column($catalogs->minorAgeRanges(), 'id'));
        $needTypes = $catalogs->needs();

        $rules = [
            'has_protective_measures' => 'nullable|in:yes,no,unknown',
            'has_linked_minors' => 'nullable|in:yes,no,unknown',
            'has_dependents' => 'nullable|in:yes,no',
            'dependents_count' => 'nullable|integer|min:1|max:20',
            'dependents_notes' => 'nullable|max:5000',
        ];

        $validator = new Validator();
        $validator->validate($data, $rules, [
            'has_protective_measures' => 'medidas de protección',
            'has_linked_minors' => 'NNA vinculados',
            'has_dependents' => 'personas dependientes',
            'dependents_count' => 'cantidad de dependientes',
            'dependents_notes' => 'observaciones de dependientes',
        ]);

        $errors = $validator->firstErrors();

        if (($data['has_protective_measures'] ?? '') === 'yes' && $this->protectiveMeasureRows($data) === []) {
            $errors['protective_measures'] = 'Agregue al menos una medida de protección informada.';
        }

        foreach ($this->protectiveMeasureRows($data) as $index => $row) {
            $typeId = (int) ($row['measure_type_id'] ?? 0);
            if ($measureTypeIds !== '' && $typeId > 0 && !in_array($typeId, array_map('intval', explode(',', $measureTypeIds)), true)) {
                $errors['protective_measures_' . $index . '_type'] = 'Seleccione un tipo de medida válido.';
            }
        }

        if (($data['has_linked_minors'] ?? '') === 'yes' && $this->linkedMinorRows($data) === []) {
            $errors['linked_minors'] = 'Agregue al menos un registro de NNA vinculado.';
        }

        foreach ($this->linkedMinorRows($data) as $index => $row) {
            $ageRangeId = (int) ($row['age_range_id'] ?? 0);
            if ($ageRangeIds !== '' && $ageRangeId > 0 && !in_array($ageRangeId, array_map('intval', explode(',', $ageRangeIds)), true)) {
                $errors['linked_minors_' . $index . '_age'] = 'Seleccione un rango etario válido.';
            }

            $gender = trim((string) ($row['gender'] ?? ''));
            if ($gender !== '' && !in_array($gender, ['female', 'male', 'other', 'unknown'], true)) {
                $errors['linked_minors_' . $index . '_gender'] = 'Seleccione un sexo válido.';
            }
        }

        if (($data['has_dependents'] ?? '') === 'yes') {
            $count = trim((string) ($data['dependents_count'] ?? ''));
            if ($count === '' || (int) $count < 1) {
                $errors['dependents_count'] = 'Indique la cantidad de personas dependientes.';
            }
        }

        $selectedNeedIds = $this->selectedNeedIds($data);
        $validNeedIds = array_map('intval', array_column($needTypes, 'id'));
        $invalidNeedIds = array_diff($selectedNeedIds, $validNeedIds);
        if ($invalidNeedIds !== []) {
            $errors['need_ids'] = 'Hay necesidades inválidas seleccionadas.';
        }

        $others = is_array($data['need_other'] ?? null) ? $data['need_other'] : [];
        foreach ($needTypes as $type) {
            $typeId = (int) $type['id'];
            if (!in_array($typeId, $selectedNeedIds, true)) {
                continue;
            }

            if (($type['slug'] ?? '') !== 'otra') {
                continue;
            }

            $otherText = trim((string) ($others[$typeId] ?? $others[(string) $typeId] ?? ''));
            if ($otherText === '') {
                $errors['need_other_' . $typeId] = 'Especifique la otra necesidad.';
            } elseif (mb_strlen($otherText) > 180) {
                $errors['need_other_' . $typeId] = 'La especificación no puede superar 180 caracteres.';
            }
        }

        return $errors;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function protectiveMeasureRows(array $data): array
    {
        return $this->repeatRows($data['protective_measures'] ?? [], [
            'measure_type_id', 'institution', 'start_date', 'end_date', 'cause_number', 'notes',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function linkedMinorRows(array $data): array
    {
        return $this->repeatRows($data['linked_minors'] ?? [], [
            'age_range_id', 'gender', 'notes',
        ]);
    }

    /**
     * @param list<string> $fields
     * @return list<array<string, mixed>>
     */
    private function repeatRows(mixed $raw, array $fields): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $rows = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $hasContent = false;
            foreach ($fields as $field) {
                if (trim((string) ($row[$field] ?? '')) !== '') {
                    $hasContent = true;
                    break;
                }
            }

            if ($hasContent) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private function selectedNeedIds(array $data): array
    {
        $raw = $data['need_ids'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
