<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Models\Cctv\ShiftEquipmentCheck;

final class EquipmentCheckCatalog
{
    /**
     * @return list<array{value: string, label: string, tone: string}>
     */
    public static function statuses(): array
    {
        return [
            ['value' => ShiftEquipmentCheck::STATUS_OPERATIONAL, 'label' => 'Operativo', 'tone' => 'success'],
            ['value' => ShiftEquipmentCheck::STATUS_WITH_OBSERVATIONS, 'label' => 'Con observaciones', 'tone' => 'warning'],
            ['value' => ShiftEquipmentCheck::STATUS_NON_OPERATIONAL, 'label' => 'No operativo', 'tone' => 'danger'],
        ];
    }

    public static function isValidStatus(string $value): bool
    {
        return in_array($value, array_column(self::statuses(), 'value'), true);
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

    public static function phaseLabel(string $value): string
    {
        return match ($value) {
            ShiftEquipmentCheck::PHASE_OPENING => 'Recepción',
            ShiftEquipmentCheck::PHASE_CLOSING => 'Entrega',
            default => $value !== '' ? $value : '—',
        };
    }
}
