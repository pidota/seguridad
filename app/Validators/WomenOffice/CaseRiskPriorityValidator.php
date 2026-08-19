<?php

declare(strict_types=1);

namespace App\Validators\WomenOffice;

use App\Repositories\WomenOffice\CatalogRepository;
use Core\Validator;

final class CaseRiskPriorityValidator
{
    /**
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $catalogs = new CatalogRepository();
        $riskTypes = $catalogs->riskFactors();

        $validator = new Validator();
        $validator->validate($data, [
            'priority' => 'nullable|in:low,medium,high,urgent',
        ], [
            'priority' => 'prioridad de atención',
        ]);

        $errors = $validator->firstErrors();
        $selectedIds = $this->selectedRiskIds($data);
        $validIds = array_map('intval', array_column($riskTypes, 'id'));

        $invalidIds = array_diff($selectedIds, $validIds);
        if ($invalidIds !== []) {
            $errors['risk_factor_ids'] = 'Hay factores de riesgo inválidos seleccionados.';
        }

        $others = is_array($data['risk_other'] ?? null) ? $data['risk_other'] : [];
        foreach ($riskTypes as $type) {
            $typeId = (int) $type['id'];
            if (!in_array($typeId, $selectedIds, true)) {
                continue;
            }

            if (($type['slug'] ?? '') !== 'otro') {
                continue;
            }

            $otherText = trim((string) ($others[$typeId] ?? $others[(string) $typeId] ?? ''));
            if ($otherText === '') {
                $errors['risk_other_' . $typeId] = 'Especifique el otro factor de riesgo.';
            } elseif (mb_strlen($otherText) > 180) {
                $errors['risk_other_' . $typeId] = 'La especificación no puede superar 180 caracteres.';
            }
        }

        return $errors;
    }

    /**
     * @return list<int>
     */
    private function selectedRiskIds(array $data): array
    {
        $raw = $data['risk_factor_ids'] ?? [];
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
