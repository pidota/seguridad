<?php

declare(strict_types=1);

namespace App\Services\Cctv;

final class NotFoundReasonCatalog
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => 'no_coverage', 'label' => 'Cámara no cubre el lugar'],
            ['value' => 'no_recording_period', 'label' => 'Sin grabación para ese período'],
            ['value' => 'camera_offline', 'label' => 'Cámara fuera de servicio'],
            ['value' => 'date_unavailable', 'label' => 'Fecha fuera del período disponible'],
            ['value' => 'incident_not_identified', 'label' => 'No se identifica el hecho'],
            ['value' => 'other', 'label' => 'Otro'],
        ];
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, array_column(self::options(), 'value'), true);
    }

    public static function label(string $value): string
    {
        foreach (self::options() as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return $value;
    }
}
