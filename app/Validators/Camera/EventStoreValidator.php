<?php

declare(strict_types=1);

namespace App\Validators\Camera;

use App\Services\Camera\EventCatalog;
use App\Services\Cctv\CatalogService;
use Core\Validator;

class EventStoreValidator
{
    public function __construct(
        private readonly CatalogService $catalogs = new CatalogService()
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $shifts = implode(',', array_column(EventCatalog::shifts(), 'value'));
        $classifications = implode(',', array_column($this->catalogs->incidentTypeOptions(), 'value'));
        $classification = trim((string) ($data['classification'] ?? ''));

        $rules = [
            'event_date' => 'required|date',
            'event_time' => 'nullable|time',
            'shift' => 'required|in:' . $shifts,
            'classification' => 'required|in:' . $classifications,
            'location' => 'required|min:2|max:180',
            'description' => 'required|min:5|max:4000',
            'actions_taken' => 'nullable|max:4000',
        ];

        if ($this->catalogs->isOtherIncidentType($classification)) {
            $rules['classification_other'] = 'required|min:2|max:180';
        }

        $validator = new Validator();
        $validator->validate($data, $rules, [
            'event_date' => 'fecha del evento',
            'event_time' => 'hora del evento',
            'shift' => 'turno',
            'classification' => 'clasificación',
            'classification_other' => 'especifique la clasificación',
            'location' => 'ubicación / cámara',
            'description' => 'descripción de la novedad',
            'actions_taken' => 'acciones realizadas',
        ]);

        return $validator->firstErrors();
    }
}
