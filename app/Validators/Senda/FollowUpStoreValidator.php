<?php

declare(strict_types=1);

namespace App\Validators\Senda;

use App\Services\Senda\FollowUpCatalog;
use Core\Validator;

final class FollowUpStoreValidator
{
    public function validate(array $data): array
    {
        $contacts = implode(',', array_column(FollowUpCatalog::contactTypes(), 'value'));
        $results = implode(',', array_column(FollowUpCatalog::results(), 'value'));
        $contactType = trim((string) ($data['contact_type'] ?? ''));
        $result = trim((string) ($data['result'] ?? ''));
        $requires = trim((string) ($data['requires_follow_up'] ?? ''));

        $rules = [
            'senda_attention_id' => 'required|integer',
            'follow_up_date' => 'required|date',
            'follow_up_time' => 'nullable|time',
            'contact_type' => 'required|in:' . $contacts,
            'result' => 'required|in:' . $results,
            'notes' => 'nullable|max:4000',
            'requires_follow_up' => 'required|in:si,no',
        ];

        if (FollowUpCatalog::isOther($contactType)) {
            $rules['contact_type_other'] = 'required|min:2|max:180';
        }

        if (FollowUpCatalog::isOther($result)) {
            $rules['result_other'] = 'required|min:2|max:180';
        }

        if ($requires === 'si') {
            $rules['next_follow_up_date'] = 'required|date';
        }

        $validator = new Validator();
        $validator->validate($data, $rules, [
            'senda_attention_id' => 'atención',
            'follow_up_date' => 'fecha de seguimiento',
            'follow_up_time' => 'hora de seguimiento',
            'contact_type' => 'tipo de contacto',
            'contact_type_other' => 'especifique el tipo de contacto',
            'result' => 'resultado',
            'result_other' => 'especifique el resultado',
            'notes' => 'observaciones del seguimiento',
            'requires_follow_up' => 'requiere nuevo seguimiento',
            'next_follow_up_date' => 'fecha del próximo seguimiento',
        ]);

        return $validator->firstErrors();
    }
}
