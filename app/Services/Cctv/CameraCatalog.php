<?php

declare(strict_types=1);

namespace App\Services\Cctv;

final class CameraCatalog
{
    public const TYPE_PTZ = 'ptz';
    public const TYPE_FIXED = 'fija';
    public const TYPE_OTHER = 'otra';

    public const STATUS_OPERATIONAL = 'operativa';
    public const STATUS_ISSUES = 'con_problemas';
    public const STATUS_OUT_OF_SERVICE = 'fuera_de_servicio';

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function types(): array
    {
        return [
            ['value' => self::TYPE_PTZ, 'label' => 'PTZ'],
            ['value' => self::TYPE_FIXED, 'label' => 'Fija'],
            ['value' => self::TYPE_OTHER, 'label' => 'Otra'],
        ];
    }

    /**
     * @return list<array{value: string, label: string, tone: string}>
     */
    public static function statuses(): array
    {
        return [
            ['value' => self::STATUS_OPERATIONAL, 'label' => 'Operativa', 'tone' => 'success'],
            ['value' => self::STATUS_ISSUES, 'label' => 'Con problemas', 'tone' => 'warning'],
            ['value' => self::STATUS_OUT_OF_SERVICE, 'label' => 'Fuera de servicio', 'tone' => 'danger'],
        ];
    }

    public static function isValidType(string $value): bool
    {
        return in_array($value, array_column(self::types(), 'value'), true);
    }

    public static function isValidStatus(string $value): bool
    {
        return in_array($value, array_column(self::statuses(), 'value'), true);
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
            if (($option['value'] ?? '') === $needle) {
                return (string) ($option['label'] ?? $needle);
            }
        }

        return $needle;
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
