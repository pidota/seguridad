<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Models\Cctv\LogEntry;

final class PoliceArrivalCatalog
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => (string) LogEntry::POLICE_ARRIVED_YES, 'label' => 'Sí'],
            ['value' => (string) LogEntry::POLICE_ARRIVED_NO, 'label' => 'No'],
            ['value' => (string) LogEntry::POLICE_ARRIVED_NOT_APPLICABLE, 'label' => 'No aplica'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::options(), 'value');
    }

    public static function label(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ((int) $value) {
            LogEntry::POLICE_ARRIVED_YES => 'Sí',
            LogEntry::POLICE_ARRIVED_NO => 'No',
            LogEntry::POLICE_ARRIVED_NOT_APPLICABLE => 'No aplica',
            default => '—',
        };
    }

    public static function isYes(mixed $value): bool
    {
        return in_array($value, [LogEntry::POLICE_ARRIVED_YES, (string) LogEntry::POLICE_ARRIVED_YES], true);
    }

    public static function isValid(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return in_array((string) $value, self::values(), true);
    }
}
