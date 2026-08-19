<?php

declare(strict_types=1);

namespace App\Validators\WomenOffice;

use App\Repositories\WomenOffice\CatalogRepository;

final class CaseReferralsValidator
{
    /**
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $catalogs = new CatalogRepository();
        $institutions = $catalogs->referralInstitutions();
        $statuses = $catalogs->referralStatuses();
        $validInstitutionIds = array_map('intval', array_column($institutions, 'id'));
        $validStatusIds = array_map('intval', array_column($statuses, 'id'));
        $slugByInstitutionId = [];
        foreach ($institutions as $institution) {
            $slugByInstitutionId[(int) $institution['id']] = (string) ($institution['slug'] ?? '');
        }

        $errors = [];
        $rows = $this->referralRows($data);

        foreach ($rows as $index => $row) {
            $date = trim((string) ($row['referral_date'] ?? ''));
            $institutionId = (int) ($row['institution_id'] ?? 0);
            $programArea = trim((string) ($row['program_area'] ?? ''));
            $statusId = (int) ($row['referral_status_id'] ?? 0);
            $reason = trim((string) ($row['reason'] ?? ''));
            $contact = trim((string) ($row['contact_person'] ?? ''));
            $notes = trim((string) ($row['notes'] ?? ''));

            if ($date === '') {
                $errors['referrals_' . $index . '_date'] = 'Indique la fecha de la derivación.';
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                $errors['referrals_' . $index . '_date'] = 'La fecha de la derivación no es válida.';
            }

            if ($statusId < 1 || !in_array($statusId, $validStatusIds, true)) {
                $errors['referrals_' . $index . '_status'] = 'Seleccione un estado de derivación válido.';
            }

            if ($institutionId > 0 && !in_array($institutionId, $validInstitutionIds, true)) {
                $errors['referrals_' . $index . '_institution'] = 'Seleccione una institución válida.';
            }

            if ($institutionId < 1 && $programArea === '') {
                $errors['referrals_' . $index . '_destination'] = 'Indique la institución o el área/programa de destino.';
            }

            if ($institutionId > 0 && ($slugByInstitutionId[$institutionId] ?? '') === 'otra' && $programArea === '') {
                $errors['referrals_' . $index . '_program_area'] = 'Especifique la institución de destino.';
            }

            if (mb_strlen($programArea) > 180) {
                $errors['referrals_' . $index . '_program_area'] = 'El área o programa no puede superar 180 caracteres.';
            }

            if (mb_strlen($reason) > 5000) {
                $errors['referrals_' . $index . '_reason'] = 'El motivo no puede superar 5000 caracteres.';
            }

            if (mb_strlen($contact) > 180) {
                $errors['referrals_' . $index . '_contact'] = 'El contacto no puede superar 180 caracteres.';
            }

            if (mb_strlen($notes) > 5000) {
                $errors['referrals_' . $index . '_notes'] = 'Las observaciones no pueden superar 5000 caracteres.';
            }
        }

        return $errors;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function referralRows(array $data): array
    {
        $raw = $data['referrals'] ?? [];
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
                'referral_date', 'institution_id', 'program_area', 'reason',
                'contact_person', 'referral_status_id', 'notes',
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
