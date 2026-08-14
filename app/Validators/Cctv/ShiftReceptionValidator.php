<?php

declare(strict_types=1);

namespace App\Validators\Cctv;

use App\Models\Cctv\ShiftEquipmentCheck;
use App\Services\Cctv\EquipmentCheckCatalog;

final class ShiftReceptionValidator
{
    /**
     * @param list<array<string, mixed>> $equipmentItems
     */
    public function __construct(
        private readonly array $equipmentItems,
        private readonly string $generalNotesField = 'opening_notes',
        private readonly string $noEquipmentMessage = 'No hay equipos configurados para la recepción del puesto.'
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $errors = [];
        $equipmentInput = is_array($data['equipment'] ?? null) ? $data['equipment'] : [];

        foreach ($this->equipmentItems as $item) {
            $equipmentId = (int) ($item['id'] ?? 0);
            $equipmentName = (string) ($item['name'] ?? 'Equipo');
            $entry = is_array($equipmentInput[$equipmentId] ?? null)
                ? $equipmentInput[$equipmentId]
                : (is_array($equipmentInput[(string) $equipmentId] ?? null) ? $equipmentInput[(string) $equipmentId] : []);
            $status = trim((string) ($entry['status'] ?? ''));
            $observations = trim((string) ($entry['observations'] ?? ''));
            $statusKey = 'equipment.' . $equipmentId . '.status';
            $observationsKey = 'equipment.' . $equipmentId . '.observations';

            if ($status === '') {
                $errors[$statusKey] = 'Indique el estado de ' . $equipmentName . '.';
                continue;
            }

            if (!EquipmentCheckCatalog::isValidStatus($status)) {
                $errors[$statusKey] = 'Seleccione un estado válido para ' . $equipmentName . '.';
                continue;
            }

            if ($status === ShiftEquipmentCheck::STATUS_WITH_OBSERVATIONS && $observations === '') {
                $errors[$observationsKey] = 'Describa las observaciones de ' . $equipmentName . '.';
            }

            if ($status === ShiftEquipmentCheck::STATUS_NON_OPERATIONAL && $observations === '') {
                $errors[$observationsKey] = 'Indique el problema detectado en ' . $equipmentName . '.';
            }
        }

        if (count($this->equipmentItems) === 0) {
            $errors['equipment'] = $this->noEquipmentMessage;
        }

        $generalNotes = trim((string) ($data[$this->generalNotesField] ?? ''));
        if (mb_strlen($generalNotes) > 2000) {
            $errors[$this->generalNotesField] = 'Las observaciones generales no pueden superar 2000 caracteres.';
        }

        return $errors;
    }
}
