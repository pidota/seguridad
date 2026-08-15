<?php

declare(strict_types=1);

namespace App\Services\Cctv;

final class VisitorTypeCatalog
{
    public const GENERAL = 'general_visit';
    public const RECORDING = 'recording_request';

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::GENERAL, 'label' => 'Visita General'],
            ['value' => self::RECORDING, 'label' => 'Solicitud de Grabación CCTV'],
        ];
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, [self::GENERAL, self::RECORDING], true);
    }

    public static function label(string $value): string
    {
        return match ($value) {
            self::GENERAL => 'Visita',
            self::RECORDING => 'Solicitud de Grabación',
            default => $value,
        };
    }
}
