<?php

declare(strict_types=1);

namespace App\Services\Senda;

final class ReferralInstitutionType
{
    public const CENTRO_SALUD = 'centro_salud';
    public const CENTRO_CONVENIO = 'centro_convenio';
    public const OTRA_INSTITUCION = 'otra_institucion';
    public const OTRAS = 'otras';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::CENTRO_SALUD,
            self::CENTRO_CONVENIO,
            self::OTRA_INSTITUCION,
            self::OTRAS,
        ];
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    public static function label(string $value): string
    {
        return match ($value) {
            self::CENTRO_SALUD => 'Centro de Salud',
            self::CENTRO_CONVENIO => 'Centro en convenio',
            self::OTRA_INSTITUCION => 'Otra Institución',
            self::OTRAS => 'Otras',
            default => 'Sin definir',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        $items = [];

        foreach (self::values() as $value) {
            $items[] = [
                'value' => $value,
                'label' => self::label($value),
            ];
        }

        return $items;
    }
}
