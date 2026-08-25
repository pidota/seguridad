<?php

declare(strict_types=1);

namespace App\Services\Guards;

final class LogEntryCatalog
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function statusOptions(): array
    {
        return [
            ['value' => \App\Models\Guards\LogEntry::STATUS_REGISTERED, 'label' => 'Registrado'],
            ['value' => \App\Models\Guards\LogEntry::STATUS_IN_PROGRESS, 'label' => 'En desarrollo'],
            ['value' => \App\Models\Guards\LogEntry::STATUS_FINISHED, 'label' => 'Finalizado'],
        ];
    }

    public static function statusLabel(string $status): string
    {
        foreach (self::statusOptions() as $option) {
            if ($option['value'] === $status) {
                return $option['label'];
            }
        }

        return $status !== '' ? $status : '—';
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            \App\Models\Guards\LogEntry::STATUS_FINISHED => 'success',
            \App\Models\Guards\LogEntry::STATUS_IN_PROGRESS => 'warning',
            default => 'other',
        };
    }
}
