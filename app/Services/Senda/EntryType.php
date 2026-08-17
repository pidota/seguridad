<?php

declare(strict_types=1);

namespace App\Services\Senda;

final class EntryType
{
    public const DERIVACION = 'derivacion';
    public const DEMANDA_ESPONTANEA = 'demanda_espontanea';
    public const MENU_SEGUIMIENTO = 'seguimiento';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::DERIVACION,
            self::DEMANDA_ESPONTANEA,
        ];
    }

    public static function isMenuOption(string $value): bool
    {
        return self::isValid($value) || $value === self::MENU_SEGUIMIENTO;
    }

    public static function isFollowUpMenuOption(string $value): bool
    {
        return $value === self::MENU_SEGUIMIENTO;
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    public static function label(string $value): string
    {
        return match ($value) {
            self::DERIVACION => 'Derivación',
            self::DEMANDA_ESPONTANEA => 'Demanda Espontánea',
            default => 'Sin definir',
        };
    }

    public static function description(string $value): string
    {
        return match ($value) {
            self::DERIVACION => 'Persona que llega derivada desde otra institución, programa, establecimiento o profesional.',
            self::DEMANDA_ESPONTANEA => 'Persona que acude directamente a SENDA.',
            default => '',
        };
    }

    /**
     * @return array{value: string, label: string, description: string, icon: string, tone: string}
     */
    public static function meta(string $value): array
    {
        return [
            'value' => $value,
            'label' => self::label($value),
            'description' => self::description($value),
            'icon' => $value === self::DERIVACION ? 'bi-signpost-split' : 'bi-person-walking',
            'tone' => $value === self::DERIVACION ? 'referral' : 'spontaneous',
        ];
    }

    /**
     * @return list<array{value: string, label: string, description: string, icon: string, tone: string}>
     */
    public static function options(): array
    {
        $items = [];

        foreach (self::values() as $value) {
            $items[] = self::meta($value);
        }

        return $items;
    }

    /**
     * Opciones del menú Atención (tipos de ingreso + seguimiento).
     *
     * @return list<array{value: string, label: string, description: string, icon: string, tone: string, menu_action?: string}>
     */
    public static function attentionMenuOptions(bool $includeFollowUp = false): array
    {
        $items = self::options();

        if ($includeFollowUp) {
            $items[] = [
                'value' => self::MENU_SEGUIMIENTO,
                'label' => 'Seguimiento',
                'description' => 'Buscar una persona atendida previamente y registrar o consultar su seguimiento.',
                'icon' => 'bi-arrow-repeat',
                'tone' => 'followup',
                'menu_action' => 'followup',
            ];
        }

        return $items;
    }
}
