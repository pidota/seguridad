<?php

declare(strict_types=1);

namespace App\Services\Cctv;

final class RejectionReasonCatalog
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => 'not_municipal', 'label' => 'No corresponde a cámaras municipales'],
            ['value' => 'no_coverage', 'label' => 'No existe cobertura del lugar'],
            ['value' => 'date_unavailable', 'label' => 'Fecha fuera de disponibilidad'],
            ['value' => 'insufficient_docs', 'label' => 'Antecedentes insuficientes'],
            ['value' => 'requirements_not_met', 'label' => 'Solicitud no cumple requisitos'],
            ['value' => 'other', 'label' => 'Otro'],
        ];
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, array_column(self::options(), 'value'), true);
    }

    public static function label(string $value): string
    {
        foreach (self::options() as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return $value;
    }
}
