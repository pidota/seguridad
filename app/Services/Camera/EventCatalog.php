<?php

declare(strict_types=1);

namespace App\Services\Camera;

final class EventCatalog
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function shifts(): array
    {
        return [
            ['value' => 'manana', 'label' => 'Mañana (07:00 – 15:00)'],
            ['value' => 'tarde', 'label' => 'Tarde (15:00 – 23:00)'],
            ['value' => 'noche', 'label' => 'Noche (23:00 – 07:00)'],
        ];
    }

    public static function isValidShift(string $value): bool
    {
        return in_array($value, array_column(self::shifts(), 'value'), true);
    }

    /**
     * @param list<array{value: string, label: string, tone?: string}> $options
     */
    public static function label(array $options, mixed $value): string
    {
        $needle = trim((string) $value);

        if ($needle === '') {
            return '—';
        }

        foreach ($options as $option) {
            if ($option['value'] === $needle) {
                return $option['label'];
            }
        }

        return $needle;
    }
}
