<?php

declare(strict_types=1);

namespace App\Services\Senda;

final class AssistedReferralCatalog
{
    public const REQUESTING_DEVICE = 'SENDA PREVIENE';

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function requestTypes(): array
    {
        return [
            ['value' => 'ingreso_tratamiento', 'label' => 'Ingreso a tratamiento'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function applicantKinds(): array
    {
        return [
            ['value' => 'familiar', 'label' => 'Algún familiar'],
            ['value' => 'institucional', 'label' => 'Algún representante institucional'],
            ['value' => 'persona_implicada', 'label' => 'La persona implicada directamente'],
        ];
    }

    public static function isAttendedPersonApplicant(string $kind): bool
    {
        return $kind === 'persona_implicada';
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function familyApplicantRelationships(): array
    {
        return [
            ['value' => 'mama', 'label' => 'Mama'],
            ['value' => 'papa', 'label' => 'Papa'],
            ['value' => 'hermano_a', 'label' => 'Hermano(a)'],
            ['value' => 'conyuge', 'label' => 'Conyuge'],
            ['value' => 'pareja', 'label' => 'Pareja'],
            ['value' => 'tio_a', 'label' => 'Tio(a)'],
            ['value' => 'otros', 'label' => 'Otros'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function institutionalApplicantRelationships(): array
    {
        return [
            ['value' => 'cesfam', 'label' => 'Cesfam'],
            ['value' => 'municipalidad', 'label' => 'Municipalidad'],
            ['value' => 'otros', 'label' => 'Otros'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function applicantRelationshipsForKind(string $kind): array
    {
        return match ($kind) {
            'familiar' => self::familyApplicantRelationships(),
            'institucional' => self::institutionalApplicantRelationships(),
            default => [],
        };
    }

    public static function isValidApplicantRelationship(string $kind, string $value): bool
    {
        foreach (self::applicantRelationshipsForKind($kind) as $option) {
            if ($option['value'] === $value) {
                return true;
            }
        }

        return false;
    }

    public static function applicantRelationshipLabel(mixed $value): string
    {
        $needle = trim((string) $value);

        if ($needle === '') {
            return '—';
        }

        foreach ([
            ...self::familyApplicantRelationships(),
            ...self::institutionalApplicantRelationships(),
        ] as $option) {
            if ($option['value'] === $needle) {
                return $option['label'];
            }
        }

        return match ($needle) {
            'familiar' => 'Familiar',
            'institucional' => 'Institucional',
            'otro' => 'Otro',
            default => $needle,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     * @deprecated Use applicantRelationshipsForKind() instead.
     */
    public static function applicantRelationships(): array
    {
        return [
            ...self::familyApplicantRelationships(),
            ...self::institutionalApplicantRelationships(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function genders(): array
    {
        return [
            ['value' => 'mujer', 'label' => 'Mujer'],
            ['value' => 'hombre', 'label' => 'Hombre'],
            ['value' => 'otro', 'label' => 'Otro'],
            ['value' => 'no_informa', 'label' => 'No informa'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function riskLevels(): array
    {
        return [
            ['value' => 'sin_riesgo', 'label' => 'Sin riesgo'],
            ['value' => 'bajo', 'label' => 'Bajo'],
            ['value' => 'medio', 'label' => 'Medio'],
            ['value' => 'alto', 'label' => 'Alto'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function frequencies(): array
    {
        return [
            ['value' => 'nunca', 'label' => 'Nunca'],
            ['value' => 'experimental', 'label' => 'Experimental / alguna vez'],
            ['value' => 'mensual', 'label' => 'Mensual'],
            ['value' => 'semanal', 'label' => 'Semanal'],
            ['value' => 'diario', 'label' => 'Diario o casi diario'],
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function consumptionSubstances(): array
    {
        return self::assistSubstances();
    }

    public static function isValidConsumptionSubstance(string $key): bool
    {
        foreach (self::consumptionSubstances() as $substance) {
            if ($substance['key'] === $key) {
                return true;
            }
        }

        return false;
    }

    public static function consumptionSubstanceLabel(string $key): string
    {
        foreach (self::consumptionSubstances() as $substance) {
            if ($substance['key'] === $key) {
                return $substance['label'];
            }
        }

        return $key;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function assistSubstances(): array
    {
        return [
            ['key' => 'tabaco', 'label' => 'Tabaco'],
            ['key' => 'alcohol', 'label' => 'Alcohol'],
            ['key' => 'marihuana', 'label' => 'Marihuana'],
            ['key' => 'cocaina', 'label' => 'Cocaína'],
            ['key' => 'anfetaminas', 'label' => 'Anfetaminas'],
            ['key' => 'inhalantes', 'label' => 'Inhalantes'],
            ['key' => 'sedantes', 'label' => 'Sedantes'],
            ['key' => 'alucinogenos', 'label' => 'Alucinógenos'],
            ['key' => 'opiaceos', 'label' => 'Opiáceos'],
            ['key' => 'otras_drogas', 'label' => 'Otras drogas'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function assistFrequencies(): array
    {
        return [
            ['value' => 'nunca', 'label' => 'Nunca'],
            ['value' => '1_2', 'label' => '1 o 2 veces'],
            ['value' => 'mensual', 'label' => 'Mensual'],
            ['value' => 'semanal', 'label' => 'Semanal'],
            ['value' => 'diario', 'label' => 'Diaria o casi diaria'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function treatmentModalities(): array
    {
        return [
            ['value' => 'ambulatorio_basico', 'label' => 'Ambulatorio básico'],
            ['value' => 'ambulatorio_intensivo', 'label' => 'Ambulatorio intensivo'],
            ['value' => 'residencial', 'label' => 'Residencial'],
            ['value' => 'comunidad_terapeutica', 'label' => 'Comunidad terapéutica'],
            ['value' => 'hospitalario', 'label' => 'Hospitalario'],
            ['value' => 'otra', 'label' => 'Otra'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function treatmentStayPeriods(): array
    {
        return [
            ['value' => 'menos_1_mes', 'label' => 'Menos de 1 mes'],
            ['value' => '1_3_meses', 'label' => '1 a 3 meses'],
            ['value' => '3_6_meses', 'label' => '3 a 6 meses'],
            ['value' => '6_12_meses', 'label' => '6 a 12 meses'],
            ['value' => 'mas_12_meses', 'label' => 'Más de 12 meses'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function destinationCenters(): array
    {
        return [
            ['value' => 'CTA HOSPITAL SANTA CRUZ', 'label' => 'CTA HOSPITAL SANTA CRUZ'],
            ['value' => 'CESFAM CHEPICA', 'label' => 'CESFAM CHEPICA'],
            ['value' => 'GEORGE WILLIAMS', 'label' => 'GEORGE WILLIAMS'],
            ['value' => 'otros', 'label' => 'OTROS'],
        ];
    }

    public static function isPresetDestinationCenter(string $value): bool
    {
        $needle = trim($value);

        if ($needle === '') {
            return false;
        }

        foreach (self::destinationCenters() as $option) {
            if ($option['value'] === 'otros') {
                continue;
            }

            if ($option['value'] === $needle) {
                return true;
            }
        }

        return false;
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

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function yesNoUnknown(): array
    {
        return [
            ['value' => '', 'label' => 'No informa'],
            ['value' => '1', 'label' => 'Sí'],
            ['value' => '0', 'label' => 'No'],
        ];
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

    /**
     * @return array<string, array{score: string, risk_level: string}>
     */
    public static function emptyAssist(): array
    {
        $items = [];

        foreach (self::assistSubstances() as $substance) {
            $items[$substance['key']] = [
                'score' => '',
                'risk_level' => '',
            ];
        }

        return $items;
    }
}
