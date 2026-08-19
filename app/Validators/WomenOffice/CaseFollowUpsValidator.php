<?php

declare(strict_types=1);

namespace App\Validators\WomenOffice;

use App\Repositories\WomenOffice\CatalogRepository;

final class CaseFollowUpsValidator
{
    /**
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $catalogs = new CatalogRepository();
        $contactTypes = $catalogs->followUpContactTypes();
        $results = $catalogs->followUpResults();
        $validContactIds = array_map('intval', array_column($contactTypes, 'id'));
        $validResultIds = array_map('intval', array_column($results, 'id'));
        $contactSlugById = [];
        $resultSlugById = [];
        foreach ($contactTypes as $type) {
            $contactSlugById[(int) $type['id']] = (string) ($type['slug'] ?? '');
        }
        foreach ($results as $result) {
            $resultSlugById[(int) $result['id']] = (string) ($result['slug'] ?? '');
        }

        $errors = [];
        $rows = $this->followUpRows($data);

        foreach ($rows as $index => $row) {
            $date = trim((string) ($row['follow_up_date'] ?? ''));
            $time = trim((string) ($row['follow_up_time'] ?? ''));
            $contactTypeId = (int) ($row['contact_type_id'] ?? 0);
            $contactOther = trim((string) ($row['contact_type_other'] ?? ''));
            $resultId = (int) ($row['result_id'] ?? 0);
            $resultOther = trim((string) ($row['result_other'] ?? ''));
            $requires = trim((string) ($row['requires_follow_up'] ?? ''));
            $nextDate = trim((string) ($row['next_follow_up_date'] ?? ''));

            if ($date === '') {
                $errors['followups_' . $index . '_date'] = 'Indique la fecha del seguimiento.';
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                $errors['followups_' . $index . '_date'] = 'La fecha del seguimiento no es válida.';
            }

            if ($time !== '' && preg_match('/^\d{2}:\d{2}$/', $time) !== 1) {
                $errors['followups_' . $index . '_time'] = 'La hora del seguimiento no es válida.';
            }

            if ($contactTypeId > 0 && !in_array($contactTypeId, $validContactIds, true)) {
                $errors['followups_' . $index . '_contact'] = 'Seleccione un tipo de contacto válido.';
            }

            if ($contactTypeId > 0 && ($contactSlugById[$contactTypeId] ?? '') === 'otro' && $contactOther === '') {
                $errors['followups_' . $index . '_contact_other'] = 'Especifique el tipo de contacto.';
            }

            if ($resultId > 0 && !in_array($resultId, $validResultIds, true)) {
                $errors['followups_' . $index . '_result'] = 'Seleccione un resultado válido.';
            }

            if ($resultId > 0 && ($resultSlugById[$resultId] ?? '') === 'otro' && $resultOther === '') {
                $errors['followups_' . $index . '_result_other'] = 'Especifique el resultado.';
            }

            if ($requires !== '' && !in_array($requires, ['yes', 'no'], true)) {
                $errors['followups_' . $index . '_requires'] = 'Indique si requiere nuevo seguimiento.';
            }

            if ($requires === 'yes' && $nextDate === '') {
                $errors['followups_' . $index . '_next_date'] = 'Indique la fecha del próximo seguimiento.';
            } elseif ($nextDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextDate) !== 1) {
                $errors['followups_' . $index . '_next_date'] = 'La fecha del próximo seguimiento no es válida.';
            }
        }

        return $errors;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function followUpRows(array $data): array
    {
        $raw = $data['followups'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $rows = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $hasContent = false;
            foreach ([
                'follow_up_date', 'follow_up_time', 'contact_type_id', 'contact_type_other',
                'result_id', 'result_other', 'notes', 'requires_follow_up', 'next_follow_up_date',
            ] as $field) {
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
}
