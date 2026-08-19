<?php

declare(strict_types=1);

namespace App\Validators\WomenOffice;

use App\Repositories\WomenOffice\CatalogRepository;
use Core\Validator;

final class CaseBackgroundValidator
{
    /**
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $catalogs = new CatalogRepository();
        $institutionIds = implode(',', array_column($catalogs->formalReportInstitutions(), 'id'));

        $rules = [
            'is_first_occurrence' => 'nullable|in:yes,no,unknown',
            'occurrence_frequency' => 'nullable|max:120',
            'occurring_since' => 'nullable|max:120',
            'occurrence_notes' => 'nullable|max:5000',
            'has_previous_reports' => 'nullable|in:yes,no,unknown',
            'has_formal_current_report' => 'nullable|in:yes,no,unknown',
            'formal_report_institution_id' => 'nullable|integer',
            'formal_report_institution_other' => 'nullable|max:180',
            'formal_report_reference_number' => 'nullable|max:120',
            'formal_report_date' => 'nullable|date',
            'formal_report_notes' => 'nullable|max:5000',
        ];

        if ($institutionIds !== '') {
            $rules['formal_report_institution_id'] .= '|in:' . $institutionIds;
        }

        $validator = new Validator();
        $validator->validate($data, $rules, [
            'is_first_occurrence' => 'primera vez',
            'occurrence_frequency' => 'frecuencia aproximada',
            'occurring_since' => 'desde cuándo ocurre',
            'occurrence_notes' => 'observaciones de ocurrencia',
            'has_previous_reports' => 'denuncias anteriores',
            'has_formal_current_report' => 'denuncia formal actual',
            'formal_report_institution_id' => 'institución de denuncia',
            'formal_report_institution_other' => 'especifique institución',
            'formal_report_reference_number' => 'n.º denuncia o parte',
            'formal_report_date' => 'fecha de denuncia',
            'formal_report_notes' => 'observaciones de denuncia',
        ]);

        $errors = $validator->firstErrors();
        $previousRows = $this->previousReportRows($data);

        if (($data['has_previous_reports'] ?? '') === 'yes' && $previousRows === []) {
            $errors['previous_reports'] = 'Agregue al menos un antecedente de denuncia anterior.';
        }

        foreach ($previousRows as $index => $row) {
            if (trim((string) ($row['institution_name'] ?? '')) === '') {
                $errors['previous_reports_' . $index . '_institution'] = 'Indique la institución del antecedente.';
            }
        }

        if (($data['has_formal_current_report'] ?? '') === 'yes') {
            $institutionId = (int) ($data['formal_report_institution_id'] ?? 0);
            if ($institutionId < 1) {
                $errors['formal_report_institution_id'] = 'Seleccione la institución de la denuncia formal.';
            } else {
                $slug = $catalogs->formalReportInstitutionSlug($institutionId);
                if ($slug === 'otra' && trim((string) ($data['formal_report_institution_other'] ?? '')) === '') {
                    $errors['formal_report_institution_other'] = 'Especifique la institución de la denuncia.';
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function previousReportRows(array $data): array
    {
        $raw = $data['previous_reports'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $rows = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $institution = trim((string) ($row['institution_name'] ?? ''));
            $reference = trim((string) ($row['reference_number'] ?? ''));
            $notes = trim((string) ($row['notes'] ?? ''));
            $date = trim((string) ($row['report_date'] ?? ''));

            if ($institution === '' && $reference === '' && $notes === '' && $date === '') {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
