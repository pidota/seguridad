<?php

declare(strict_types=1);

namespace App\Validators\Cctv;

use App\Services\Cctv\CameraCatalog;
use Core\Validator;

class CameraStoreValidator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $types = implode(',', array_column(CameraCatalog::types(), 'value'));
        $statuses = implode(',', array_column(CameraCatalog::statuses(), 'value'));

        $validator = new Validator();
        $validator->validate($data, [
            'code' => 'required|min:2|max:40',
            'name' => 'required|min:2|max:180',
            'sector_id' => 'nullable|integer',
            'location' => 'nullable|max:255',
            'camera_type' => 'required|in:' . $types,
            'status' => 'required|in:' . $statuses,
            'active' => 'nullable|in:0,1',
        ], [
            'code' => 'código',
            'name' => 'nombre',
            'sector_id' => 'sector',
            'location' => 'ubicación',
            'camera_type' => 'tipo de cámara',
            'status' => 'estado',
            'active' => 'activa',
        ]);

        return $validator->firstErrors();
    }
}
