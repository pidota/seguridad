<?php

declare(strict_types=1);

namespace App\Services\Senda;

final class EntryType
{
    public const DERIVACION = 'derivacion';
    public const DEMANDA_ESPONTANEA = 'demanda_espontanea';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::DERIVACION,
            self::DEMANDA_ESPONTANEA,
        ];
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    public static function label(string $value): string
    {
        return match ($value) {
            self::DERIVACION => 'Derivación',
            self::DEMANDA_ESPONTANEA => 'Demanda Espontánea',
            default => 'Sin definir',
        };
    }

    public static function description(string $value): string
    {
        return match ($value) {
            self::DERIVACION => 'Persona que llega derivada desde otra institución, programa, establecimiento o profesional.',
            self::DEMANDA_ESPONTANEA => 'Persona que acude directamente a SENDA.',
            default => '',
        };
    }

    /**
     * @return array{value: string, label: string, description: string, icon: string, tone: string}
     */
    public static function meta(string $value): array
    {
        return [
            'value' => $value,
            'label' => self::label($value),
            'description' => self::description($value),
            'icon' => $value === self::DERIVACION ? 'bi-signpost-split' : 'bi-person-walking',
            'tone' => $value === self::DERIVACION ? 'referral' : 'spontaneous',
        ];
    }

    /**
     * @return list<array{value: string, label: string, description: string, icon: string, tone: string}>
     */
    public static function options(): array
    {
        $items = [];

        foreach (self::values() as $value) {
            $items[] = self::meta($value);
        }

        return $items;
    }
}
