<?php

declare(strict_types=1);

namespace App\Validators\WomenOffice;

use App\Repositories\WomenOffice\CatalogRepository;
use Core\Validator;

final class CaseFactsValidator
{
    /**
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $catalogs = new CatalogRepository();
        $violenceTypes = $catalogs->violenceTypes();
        $violenceIds = array_column($violenceTypes, 'id');
        $sectorOptions = (new \App\Repositories\SectorRepository())->options();
        $sectorIds = implode(',', array_column($sectorOptions, 'id'));

        $rules = [
            'incident_date_precision' => 'required|in:exact,approximate,undetermined',
            'incident_date' => 'nullable|date',
            'incident_time' => 'nullable|max:8',
            'incident_time_notes' => 'nullable|max:120',
            'incident_place' => 'nullable|max:180',
            'incident_sector_id' => 'nullable|integer',
            'incident_address' => 'nullable|max:255',
            'description' => 'required|min:10',
        ];

        if ($sectorIds !== '') {
            $rules['incident_sector_id'] .= '|in:' . $sectorIds;
        }

        $validator = new Validator();
        $validator->validate($data, $rules, [
            'incident_date_precision' => 'precisión de la fecha',
            'incident_date' => 'fecha del hecho',
            'incident_time' => 'hora del hecho',
            'incident_time_notes' => 'referencia horaria',
            'incident_place' => 'lugar',
            'incident_sector_id' => 'sector',
            'incident_address' => 'dirección o referencia',
            'description' => 'descripción de los hechos',
        ]);

        $errors = $validator->firstErrors();
        $precision = trim((string) ($data['incident_date_precision'] ?? ''));

        if ($precision !== 'undetermined' && trim((string) ($data['incident_date'] ?? '')) === '') {
            $errors['incident_date'] = 'Indique la fecha del hecho o seleccione «No determinada».';
        }

        if ($precision === 'undetermined' && trim((string) ($data['incident_time'] ?? '')) !== '') {
            $errors['incident_time'] = 'No registre hora exacta si la fecha no está determinada.';
        }

        $selectedIds = $this->selectedViolenceIds($data);
        if ($selectedIds === []) {
            $errors['violence_type_ids'] = 'Seleccione al menos un tipo de violencia.';
        }

        $invalidIds = array_diff($selectedIds, array_map('intval', $violenceIds));
        if ($invalidIds !== []) {
            $errors['violence_type_ids'] = 'Hay tipos de violencia inválidos seleccionados.';
        }

        $others = is_array($data['violence_other'] ?? null) ? $data['violence_other'] : [];
        foreach ($violenceTypes as $type) {
            $typeId = (int) $type['id'];
            if (!in_array($typeId, $selectedIds, true)) {
                continue;
            }

            if (($type['slug'] ?? '') !== 'otra') {
                continue;
            }

            $otherText = trim((string) ($others[$typeId] ?? $others[(string) $typeId] ?? ''));
            if ($otherText === '') {
                $errors['violence_other_' . $typeId] = 'Especifique el otro tipo de violencia.';
            } elseif (mb_strlen($otherText) > 180) {
                $errors['violence_other_' . $typeId] = 'La especificación no puede superar 180 caracteres.';
            }
        }

        return $errors;
    }

    /**
     * @return list<int>
     */
    private function selectedViolenceIds(array $data): array
    {
        $raw = $data['violence_type_ids'] ?? [];
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
