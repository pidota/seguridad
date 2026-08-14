<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Models\Cctv\LogEntry;

final class LogEntryCatalog
{
    /**
     * @return list<array{value: string, label: string, tone: string}>
     */
    public static function statuses(): array
    {
        return [
            ['value' => LogEntry::STATUS_REGISTERED, 'label' => 'Registrado', 'tone' => 'other'],
            ['value' => LogEntry::STATUS_IN_PROGRESS, 'label' => 'En desarrollo', 'tone' => 'warning'],
            ['value' => LogEntry::STATUS_FINISHED, 'label' => 'Finalizado', 'tone' => 'success'],
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

    /**
     * @return list<array{value: string, label: string, tone: string}>
     */
    public static function filterStatuses(): array
    {
        $options = self::statuses();
        $known = array_column($options, 'value');

        foreach (TechnicalEntryCatalog::statuses() as $technical) {
            if (!in_array($technical['value'], $known, true)) {
                $options[] = $technical;
                $known[] = $technical['value'];
            }
        }

        return $options;
    }

    public static function isValidFilterStatus(string $value): bool
    {
        return LogEntry::isValidStatus($value) || TechnicalEntryCatalog::isValidStatus($value);
    }
}
