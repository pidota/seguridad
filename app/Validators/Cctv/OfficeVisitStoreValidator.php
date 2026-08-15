<?php

declare(strict_types=1);

namespace App\Validators\Cctv;

use App\Services\Cctv\VisitorTypeCatalog;
use Core\Validator;

final class OfficeVisitStoreValidator
{
    public function validate(array $data): array
    {
        $validator = new Validator();
        $visitorType = (string) ($data['visitor_type'] ?? '');

        $rules = [
            'visitor_type' => 'required|in:' . VisitorTypeCatalog::GENERAL . ',' . VisitorTypeCatalog::RECORDING,
            'visit_date' => 'required|date',
            'arrival_time' => 'required|time',
            'requester_name' => 'required|min:3|max:180',
            'requester_rut' => 'nullable|max:20',
            'requester_phone' => 'nullable|max:40',
            'requester_email' => 'nullable|max:180|email',
            'organization' => 'nullable|max:180',
            'reason' => 'required|min:5|max:5000',
            'departure_time' => 'nullable|time',
            'visit_reason' => 'nullable|max:40',
            'visit_reason_other' => 'nullable|max:180',
        ];

        if ($visitorType === VisitorTypeCatalog::RECORDING) {
            $rules = array_merge($rules, [
                'incident_date' => 'required|date',
                'time_from' => 'required|time',
                'time_to' => 'required|time',
                'incident_description' => 'required|min:5|max:5000',
                'sector_id' => 'nullable|integer',
                'camera_id' => 'nullable|integer',
                'has_complaint' => 'nullable|in:0,1',
                'complaint_institution' => 'nullable|max:80',
                'complaint_number' => 'nullable|max:120',
                'complaint_date' => 'nullable|date',
                'complaint_observations' => 'nullable|max:5000',
            ]);
        }

        $validator->validate($data, $rules, [
            'requester_name' => 'nombre',
            'requester_rut' => 'RUT',
            'requester_phone' => 'teléfono',
            'requester_email' => 'correo electrónico',
            'arrival_time' => 'hora de ingreso',
            'incident_date' => 'fecha del hecho',
            'time_from' => 'hora desde',
            'time_to' => 'hora hasta',
            'incident_description' => 'descripción del hecho',
        ]);

        return $validator->firstErrors();
    }
}
