<?php

declare(strict_types=1);

namespace App\Validators\Cctv;

use App\Models\Cctv\TechnicalIssueType;
use App\Services\Cctv\CameraCatalog;
use App\Services\Cctv\TechnicalEntryCatalog;
use Core\Validator;

final class TechnicalStoreValidator
{
    /**
     * @param list<array{id: int, allows_other: bool}> $technicalIssueTypes
     */
    public function __construct(
        private readonly array $technicalIssueTypes
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $allowedIssueIds = implode(',', array_map(
            static fn (array $row): string => (string) ($row['id'] ?? ''),
            $this->technicalIssueTypes
        ));
        $allowedStatuses = implode(',', TechnicalEntryCatalog::values());
        $allowedCameraStatuses = implode(',', array_column(CameraCatalog::statuses(), 'value'));

        $validator = new Validator();
        $validator->validate($data, [
            'event_date' => 'required',
            'event_time' => 'required',
            'target_type' => 'required|in:camera,equipment',
            'camera_id' => 'nullable|integer',
            'equipment_id' => 'nullable|integer',
            'technical_issue_type_id' => 'required|in:' . $allowedIssueIds,
            'technical_issue_other' => 'nullable',
            'observations' => 'required|min:5|max:5000',
            'status' => 'required|in:' . $allowedStatuses,
            'camera_status' => 'nullable|in:' . $allowedCameraStatuses,
        ], [
            'event_date' => 'fecha',
            'event_time' => 'hora',
            'target_type' => 'elemento afectado',
            'camera_id' => 'cámara',
            'equipment_id' => 'equipo',
            'technical_issue_type_id' => 'tipo de problema',
            'technical_issue_other' => 'especificación del problema',
            'observations' => 'descripción',
            'status' => 'estado',
            'camera_status' => 'estado de la cámara',
        ]);

        $errors = $validator->firstErrors();

        if ($errors === [] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) ($data['event_date'] ?? '')))) {
            $errors['event_date'] = 'Indique una fecha válida.';
        }

        if ($errors === [] && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', trim((string) ($data['event_time'] ?? '')))) {
            $errors['event_time'] = 'Indique una hora válida.';
        }

        if ($errors === []) {
            $errors = array_merge($errors, $this->validateTarget($data));
        }

        $issueTypeId = (int) ($data['technical_issue_type_id'] ?? 0);
        if ($errors === [] && $this->requiresOtherDetail($issueTypeId)) {
            $other = trim((string) ($data['technical_issue_other'] ?? ''));
            if ($other === '') {
                $errors['technical_issue_other'] = 'Especifique el tipo de problema.';
            } elseif (mb_strlen($other) > 180) {
                $errors['technical_issue_other'] = 'La especificación no puede superar 180 caracteres.';
            }
        }

        if ($errors === []) {
            $errors = array_merge($errors, $this->validateCameraStatus($data));
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function validateTarget(array $data): array
    {
        $errors = [];
        $targetType = trim((string) ($data['target_type'] ?? ''));
        $cameraId = trim((string) ($data['camera_id'] ?? ''));
        $equipmentId = trim((string) ($data['equipment_id'] ?? ''));

        if ($targetType === 'camera') {
            if ($cameraId === '') {
                $errors['camera_id'] = 'Seleccione la cámara afectada.';
            }

            if ($equipmentId !== '') {
                $errors['equipment_id'] = 'No debe indicar equipo cuando seleccionó cámara.';
            }

            return $errors;
        }

        if ($targetType === 'equipment') {
            if ($equipmentId === '') {
                $errors['equipment_id'] = 'Seleccione el equipo afectado.';
            }

            if ($cameraId !== '') {
                $errors['camera_id'] = 'No debe indicar cámara cuando seleccionó equipo.';
            }

            return $errors;
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function validateCameraStatus(array $data): array
    {
        $errors = [];
        $cameraStatus = trim((string) ($data['camera_status'] ?? ''));
        $cameraId = trim((string) ($data['camera_id'] ?? ''));
        $targetType = trim((string) ($data['target_type'] ?? ''));

        if ($cameraStatus === '') {
            return $errors;
        }

        if ($targetType !== 'camera' || $cameraId === '') {
            $errors['camera_status'] = 'Solo puede actualizar el estado cuando selecciona una cámara.';

            return $errors;
        }

        if (!CameraCatalog::isValidStatus($cameraStatus)) {
            $errors['camera_status'] = 'Seleccione un estado de cámara válido.';
        }

        return $errors;
    }

    private function requiresOtherDetail(int $issueTypeId): bool
    {
        foreach ($this->technicalIssueTypes as $type) {
            if ((int) ($type['id'] ?? 0) === $issueTypeId) {
                return !empty($type['allows_other']);
            }
        }

        return false;
    }
}
