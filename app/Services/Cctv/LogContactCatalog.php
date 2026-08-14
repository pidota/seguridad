<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Models\Cctv\LogContact;

final class LogContactCatalog
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function types(): array
    {
        return [
            ['value' => LogContact::TYPE_CARABINEROS, 'label' => 'Carabineros'],
            ['value' => LogContact::TYPE_SEGURIDAD_MUNICIPAL, 'label' => 'Seguridad Municipal'],
            ['value' => LogContact::TYPE_GUARDIAS_MUNICIPALES, 'label' => 'Guardias Municipales'],
            ['value' => LogContact::TYPE_BOMBEROS, 'label' => 'Bomberos'],
            ['value' => LogContact::TYPE_SAMU, 'label' => 'SAMU'],
            ['value' => LogContact::TYPE_PDI, 'label' => 'PDI'],
            ['value' => LogContact::TYPE_OTHER, 'label' => 'Otro'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::types(), 'value');
    }

    public static function label(string $value): string
    {
        foreach (self::types() as $option) {
            if ($option['value'] === $value) {
                return (string) $option['label'];
            }
        }

        return $value !== '' ? $value : '—';
    }

    public static function isValidType(string $value): bool
    {
        return LogContact::isValidType($value);
    }
}
