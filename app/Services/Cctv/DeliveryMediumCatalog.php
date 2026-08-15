<?php

declare(strict_types=1);

namespace App\Services\Cctv;

final class DeliveryMediumCatalog
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => 'pendrive', 'label' => 'Pendrive'],
            ['value' => 'external_disk', 'label' => 'Disco externo'],
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
