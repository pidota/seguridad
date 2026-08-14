<?php

declare(strict_types=1);

namespace App\Services\Senda;

final class FollowUpCatalog
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function contactTypes(): array
    {
        return [
            ['value' => 'telefonico', 'label' => 'Telefónico'],
            ['value' => 'presencial', 'label' => 'Presencial'],
            ['value' => 'correo_electronico', 'label' => 'Correo electrónico'],
            ['value' => 'visita_domiciliaria', 'label' => 'Visita domiciliaria'],
            ['value' => 'otro', 'label' => 'Otro'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function results(): array
    {
        return [
            ['value' => 'contacto_exitoso', 'label' => 'Contacto exitoso'],
            ['value' => 'no_contesta', 'label' => 'No contesta'],
            ['value' => 'numero_no_disponible', 'label' => 'Número no disponible'],
            ['value' => 'persona_no_localizada', 'label' => 'Persona no localizada'],
            ['value' => 'reagenda_atencion', 'label' => 'Reagenda atención'],
            ['value' => 'continua_en_seguimiento', 'label' => 'Continúa en seguimiento'],
            ['value' => 'seguimiento_finalizado', 'label' => 'Seguimiento finalizado'],
            ['value' => 'otro', 'label' => 'Otro'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function yesNo(): array
    {
        return [
            ['value' => 'si', 'label' => 'Sí'],
            ['value' => 'no', 'label' => 'No'],
        ];
    }

    public static function isOther(string $value): bool
    {
        return $value === 'otro';
    }

    /**
     * @param list<array{value: string, label: string}> $options
     */
    public static function optionLabel(array $options, mixed $value): string
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
