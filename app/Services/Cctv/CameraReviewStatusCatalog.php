<?php

declare(strict_types=1);

namespace App\Services\Cctv;

final class CameraReviewStatusCatalog
{
    public const PENDING = 'pending';
    public const REVIEWED = 'reviewed';
    public const FOUND = 'recording_found';
    public const NO_USEFUL = 'no_useful_recording';
    public const UNAVAILABLE = 'not_available';
    public const NOT_REQUIRED = 'not_required';

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::PENDING, 'label' => 'Pendiente'],
            ['value' => self::REVIEWED, 'label' => 'Revisada'],
            ['value' => self::FOUND, 'label' => 'Grabación encontrada'],
            ['value' => self::NO_USEFUL, 'label' => 'Sin registro útil'],
            ['value' => self::UNAVAILABLE, 'label' => 'No disponible'],
            ['value' => self::NOT_REQUIRED, 'label' => 'No requerida'],
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
