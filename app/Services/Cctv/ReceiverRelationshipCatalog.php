<?php

declare(strict_types=1);

namespace App\Services\Cctv;

final class ReceiverRelationshipCatalog
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => 'solicitante', 'label' => 'Es el solicitante'],
            ['value' => 'abogado', 'label' => 'Abogado / mandatario'],
            ['value' => 'familiar', 'label' => 'Familiar'],
            ['value' => 'institucion', 'label' => 'Representante institucional'],
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
