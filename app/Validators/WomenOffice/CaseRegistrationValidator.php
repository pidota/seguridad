<?php

declare(strict_types=1);

namespace App\Validators\WomenOffice;

use App\Repositories\WomenOffice\CatalogRepository;
use Core\Validator;

final class CaseRegistrationValidator
{
    public function validate(array $data, int $expectedPersonId): array
    {
        $channels = implode(',', array_column((new CatalogRepository())->reportChannels(), 'id'));

        $validator = new Validator();
        $validator->validate($data, [
            'affected_person_id' => 'required|integer',
            'reported_date' => 'required|date',
            'reported_time' => 'required|max:8',
            'report_channel_id' => 'required|integer|in:' . $channels,
            'report_channel_other' => 'nullable|max:180',
        ], [
            'affected_person_id' => 'persona afectada',
            'reported_date' => 'fecha de registro',
            'reported_time' => 'hora de registro',
            'report_channel_id' => 'canal de ingreso',
            'report_channel_other' => 'especifique canal',
        ]);

        $errors = $validator->firstErrors();

        if (!isset($errors['affected_person_id']) && (int) ($data['affected_person_id'] ?? 0) !== $expectedPersonId) {
            $errors['affected_person_id'] = 'La persona seleccionada no coincide con la sesión activa.';
        }

        $channelId = (int) ($data['report_channel_id'] ?? 0);
        $slug = (new CatalogRepository())->reportChannelSlug($channelId);

        if ($slug === 'otro' && trim((string) ($data['report_channel_other'] ?? '')) === '') {
            $errors['report_channel_other'] = 'Especifique el canal de ingreso.';
        }

        return $errors;
    }
}
