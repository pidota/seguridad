<?php

declare(strict_types=1);

namespace App\Services\Cctv;

final class ComplaintInstitutionCatalog
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => 'carabineros', 'label' => 'Carabineros de Chile'],
            ['value' => 'pdi', 'label' => 'Policía de Investigaciones'],
            ['value' => 'fiscalia', 'label' => 'Fiscalía'],
            ['value' => 'other', 'label' => 'Otra'],
        ];
    }

    public static function isValid(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return in_array($value, array_column(self::options(), 'value'), true);
    }

    public static function label(?string $value): string
    {
        foreach (self::options() as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return $value ?? '—';
    }
}
