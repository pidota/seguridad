<?php

declare(strict_types=1);

namespace App\Validators\Cctv;

use App\Models\Cctv\IncidentType;
use App\Models\Cctv\LogContact;
use App\Models\Cctv\LogEntry;
use App\Services\Cctv\PoliceArrivalCatalog;
use App\Services\Cctv\LogContactCatalog;
use App\Services\Cctv\LogEntryCatalog;
use Core\Validator;

final class IncidentStoreValidator
{
    /**
     * @param list<array{id: int, allows_other: bool}> $incidentTypes
     */
    public function __construct(
        private readonly array $incidentTypes
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $allowedIncidentIds = implode(',', array_map(
            static fn (array $row): string => (string) ($row['id'] ?? ''),
            $this->incidentTypes
        ));
        $allowedStatuses = implode(',', array_column(LogEntryCatalog::statuses(), 'value'));

        $validator = new Validator();
        $validator->validate($data, [
            'event_date' => 'required',
            'event_time' => 'required',
            'incident_type_id' => 'required|in:' . $allowedIncidentIds,
            'sector_id' => 'nullable|integer',
            'camera_id' => 'nullable|integer',
            'observations' => 'required|min:5|max:5000',
            'coordination_notified' => 'nullable|in:0,1',
            'police_arrived' => 'required|in:0,1,2',
            'police_arrival_time' => 'nullable',
            'status' => 'required|in:' . $allowedStatuses,
        ], [
            'event_date' => 'fecha',
            'event_time' => 'hora del suceso',
            'incident_type_id' => 'tipo de incidente',
            'sector_id' => 'sector',
            'camera_id' => 'cámara',
            'observations' => 'observaciones',
            'coordination_notified' => 'aviso o coordinación',
            'police_arrived' => 'llegada de Carabineros',
            'police_arrival_time' => 'hora de llegada de Carabineros',
            'status' => 'estado',
        ]);

        $errors = $validator->firstErrors();

        if ($errors === [] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) ($data['event_date'] ?? '')))) {
            $errors['event_date'] = 'Indique una fecha válida.';
        }

        if ($errors === [] && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', trim((string) ($data['event_time'] ?? '')))) {
            $errors['event_time'] = 'Indique una hora válida.';
        }

        $incidentTypeId = (int) ($data['incident_type_id'] ?? 0);
        if ($errors === [] && $this->requiresOtherDetail($incidentTypeId)) {
            $other = trim((string) ($data['incident_type_other'] ?? ''));
            if ($other === '') {
                $errors['incident_type_other'] = 'Especifique el tipo de incidente.';
            } elseif (mb_strlen($other) > 180) {
                $errors['incident_type_other'] = 'La especificación no puede superar 180 caracteres.';
            }
        }

        if ($errors === []) {
            $errors = array_merge($errors, $this->validatePoliceArrival($data));
        }

        if ($errors === [] && $this->isAffirmative($data['coordination_notified'] ?? null)) {
            $errors = array_merge($errors, $this->validateContacts($data));
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function validatePoliceArrival(array $data): array
    {
        $errors = [];
        $policeArrived = (string) ($data['police_arrived'] ?? '');
        $policeTime = trim((string) ($data['police_arrival_time'] ?? ''));

        if (!PoliceArrivalCatalog::isValid($policeArrived)) {
            $errors['police_arrived'] = 'Indique si llegó Carabineros.';

            return $errors;
        }

        if (PoliceArrivalCatalog::isYes($policeArrived)) {
            if ($policeTime === '') {
                $errors['police_arrival_time'] = 'Indique la hora de llegada de Carabineros.';
            } elseif (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $policeTime)) {
                $errors['police_arrival_time'] = 'Indique una hora válida.';
            }

            return $errors;
        }

        if ($policeTime !== '') {
            $errors['police_arrival_time'] = 'No debe indicar hora si Carabineros no llegó o no aplica.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function validateContacts(array $data): array
    {
        $errors = [];
        $rawContacts = $data['contacts'] ?? [];
        $contacts = is_array($rawContacts) ? $rawContacts : [];
        $allowedTypes = implode(',', LogContactCatalog::values());
        $validCount = 0;

        foreach ($contacts as $index => $contact) {
            if (!is_array($contact)) {
                continue;
            }

            $prefix = 'contacts.' . $index;
            $type = trim((string) ($contact['contact_type'] ?? ''));
            $name = trim((string) ($contact['contact_name'] ?? ''));
            $time = trim((string) ($contact['contacted_at'] ?? ''));
            $notes = trim((string) ($contact['notes'] ?? ''));

            if ($type === '' && $time === '' && $name === '' && $notes === '') {
                continue;
            }

            if ($type === '') {
                $errors[$prefix . '.contact_type'] = 'Seleccione el tipo de contacto.';
            } elseif (!LogContact::isValidType($type)) {
                $errors[$prefix . '.contact_type'] = 'El tipo de contacto no es válido.';
            }

            if ($time === '') {
                $errors[$prefix . '.contacted_at'] = 'Indique la hora del aviso.';
            } elseif (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
                $errors[$prefix . '.contacted_at'] = 'Indique una hora válida.';
            }

            if ($type === LogContact::TYPE_OTHER && $name === '') {
                $errors[$prefix . '.contact_name'] = 'Especifique el contacto.';
            } elseif ($name !== '' && mb_strlen($name) > 150) {
                $errors[$prefix . '.contact_name'] = 'El nombre no puede superar 150 caracteres.';
            }

            if ($notes !== '' && mb_strlen($notes) > 2000) {
                $errors[$prefix . '.notes'] = 'Las notas no pueden superar 2000 caracteres.';
            }

            if ($type !== '' && $time !== '' && !isset($errors[$prefix . '.contact_type']) && !isset($errors[$prefix . '.contacted_at'])) {
                if ($type !== LogContact::TYPE_OTHER || $name !== '') {
                    $validCount++;
                }
            }
        }

        if ($validCount === 0) {
            $errors['contacts'] = 'Agregue al menos un aviso o coordinación.';
        }

        return $errors;
    }

    private function requiresOtherDetail(int $incidentTypeId): bool
    {
        foreach ($this->incidentTypes as $type) {
            if ((int) ($type['id'] ?? 0) === $incidentTypeId) {
                return !empty($type['allows_other']);
            }
        }

        return false;
    }

    private function isAffirmative(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true', 'yes', 'si', 'sí'], true);
    }
}
