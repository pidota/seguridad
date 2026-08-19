<?php

declare(strict_types=1);

namespace App\Validators\WomenOffice;

use App\Repositories\WomenOffice\CatalogRepository;

final class CaseActionsValidator
{
    /**
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $catalogs = new CatalogRepository();
        $actionTypes = $catalogs->actionTypes();
        $validIds = array_map('intval', array_column($actionTypes, 'id'));
        $slugById = [];
        foreach ($actionTypes as $type) {
            $slugById[(int) $type['id']] = (string) ($type['slug'] ?? '');
        }

        $errors = [];
        $rows = $this->actionRows($data);

        foreach ($rows as $index => $row) {
            $typeId = (int) ($row['action_type_id'] ?? 0);
            $date = trim((string) ($row['action_date'] ?? ''));
            $time = trim((string) ($row['action_time'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            $institution = trim((string) ($row['institution'] ?? ''));

            if ($date === '') {
                $errors['actions_' . $index . '_date'] = 'Indique la fecha de la acción.';
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                $errors['actions_' . $index . '_date'] = 'La fecha de la acción no es válida.';
            }

            if ($typeId < 1 || !in_array($typeId, $validIds, true)) {
                $errors['actions_' . $index . '_type'] = 'Seleccione un tipo de acción válido.';
            }

            if ($time !== '' && preg_match('/^\d{2}:\d{2}$/', $time) !== 1) {
                $errors['actions_' . $index . '_time'] = 'La hora de la acción no es válida.';
            }

            if ($typeId > 0 && ($slugById[$typeId] ?? '') === 'otra' && $description === '') {
                $errors['actions_' . $index . '_description'] = 'Especifique la acción en la descripción.';
            }

            if (mb_strlen($description) > 5000) {
                $errors['actions_' . $index . '_description'] = 'La descripción no puede superar 5000 caracteres.';
            }

            if (mb_strlen($institution) > 180) {
                $errors['actions_' . $index . '_institution'] = 'La institución no puede superar 180 caracteres.';
            }
        }

        return $errors;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function actionRows(array $data): array
    {
        $raw = $data['actions'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $rows = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $hasContent = false;
            foreach (['action_date', 'action_time', 'action_type_id', 'description', 'institution'] as $field) {
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
