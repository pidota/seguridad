<?php

declare(strict_types=1);

namespace App\Validators\Senda;

use App\Services\Senda\EntryType;
use App\Services\Senda\ReferralInstitutionType;
use Core\Validator;

final class AttentionStoreValidator
{
    public function validate(array $data): array
    {
        $entryType = (string) ($data['entry_type'] ?? '');
        $isReferral = $entryType === EntryType::DERIVACION;

        $rules = [
            'entry_type' => 'required|in:derivacion,demanda_espontanea',
            'senda_person_id' => 'required|integer',
            'attention_date' => 'required|date',
            'attention_time' => 'required|time',
            'summary' => 'nullable|max:2000',
        ];
        $labels = [
            'entry_type' => 'tipo de ingreso',
            'senda_person_id' => 'persona',
            'attention_date' => 'fecha de atención',
            'attention_time' => 'hora de atención',
            'summary' => 'observaciones',
        ];

        if ($isReferral) {
            $rules['referral_institution_type'] = 'required|in:' . implode(',', ReferralInstitutionType::values());
            $rules['referral_institution_name'] = 'required|min:2|max:180';
            $rules['referral_person'] = 'required|min:2|max:180';
            $rules['referral_phone'] = 'nullable|max:30';
            $rules['referral_email'] = 'nullable|email|max:150';
            $rules['referral_notes'] = 'nullable|max:2000';
            $labels['referral_institution_type'] = 'tipo de institución';
            $labels['referral_institution_name'] = 'nombre de institución';
            $labels['referral_person'] = 'persona o profesional que deriva';
            $labels['referral_phone'] = 'teléfono';
            $labels['referral_email'] = 'correo';
            $labels['referral_notes'] = 'observaciones';
        }

        $validator = new Validator();
        $validator->validate($data, $rules, $labels);

        return $validator->firstErrors();
    }
}
