<?php

declare(strict_types=1);

namespace App\Services\Cctv;

final class VisitReasonCatalog
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => 'consulta', 'label' => 'Consulta'],
            ['value' => 'reunion', 'label' => 'Reunión'],
            ['value' => 'mantencion_tecnica', 'label' => 'Mantención técnica'],
            ['value' => 'proveedor', 'label' => 'Proveedor'],
            ['value' => 'funcionario_municipal', 'label' => 'Funcionario municipal'],
            ['value' => 'institucion_externa', 'label' => 'Institución externa'],
            ['value' => 'entrega_documento', 'label' => 'Entrega de documento'],
            ['value' => 'other', 'label' => 'Otro'],
        ];
    }

    public static function isValid(?string $value): bool
    {
        return $value !== null && $value !== '' && in_array($value, array_column(self::options(), 'value'), true);
    }

    public static function label(?string $value): string
    {
        foreach (self::options() as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return $value ?? '—';
    }

    public static function isOther(?string $value): bool
    {
        return $value === 'other';
    }
}
