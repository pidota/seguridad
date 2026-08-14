<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Models\Cctv\LogEntry;

final class TechnicalEntryCatalog
{
    public const STATUS_DETECTED = 'detectado';
    public const STATUS_PENDING = 'pendiente';
    public const STATUS_OPERATIONAL_AGAIN = 'operativo_nuevamente';

    /**
     * @return list<array{value: string, label: string, tone: string}>
     */
    public static function statuses(): array
    {
        return [
            ['value' => self::STATUS_DETECTED, 'label' => 'Detectado', 'tone' => 'warning'],
            ['value' => self::STATUS_PENDING, 'label' => 'Pendiente', 'tone' => 'alert'],
            ['value' => self::STATUS_OPERATIONAL_AGAIN, 'label' => 'Operativo nuevamente', 'tone' => 'success'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::statuses(), 'value');
    }

    public static function isValidStatus(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    public static function statusLabel(string $value): string
    {
        foreach (self::statuses() as $option) {
            if ($option['value'] === $value) {
                return (string) $option['label'];
            }
        }

        return $value !== '' ? $value : '—';
    }

    public static function statusMeta(string $value): array
    {
        foreach (self::statuses() as $option) {
            if ($option['value'] === $value) {
                return $option;
            }
        }

        return ['value' => $value, 'label' => $value, 'tone' => 'other'];
    }
}
