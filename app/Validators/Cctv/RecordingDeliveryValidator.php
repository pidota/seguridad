<?php

declare(strict_types=1);

namespace App\Validators\Cctv;

use App\Services\Cctv\DeliveryMediumCatalog;
use Core\Validator;

final class RecordingDeliveryValidator
{
    public function validate(array $data): array
    {
        $mediums = implode(',', array_column(DeliveryMediumCatalog::options(), 'value'));
        $relationships = implode(',', array_column(\App\Services\Cctv\ReceiverRelationshipCatalog::options(), 'value'));
        $validator = new Validator();
        $validator->validate($data, [
            'receiver_name' => 'required|min:3|max:180',
            'receiver_rut' => 'required|max:20',
            'receiver_relationship' => 'required|in:' . $relationships,
            'authorization_document' => 'nullable|max:255',
            'delivery_medium' => 'required|in:' . $mediums,
            'delivery_notes' => 'nullable|max:5000',
            'public_notes' => 'nullable|max:5000',
            'internal_notes' => 'nullable|max:5000',
            'file_internal_name' => 'nullable|max:180',
        ], [
            'receiver_name' => 'persona que recibe',
            'receiver_rut' => 'RUT de quien recibe',
            'receiver_relationship' => 'relación con el solicitante',
            'delivery_medium' => 'medio de entrega',
        ]);

        return $validator->firstErrors();
    }
}
