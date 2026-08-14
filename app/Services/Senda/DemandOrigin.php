<?php

declare(strict_types=1);

namespace App\Services\Senda;

final class DemandOrigin
{
    public const ESPONTANEA = 'espontanea';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_merge([self::ESPONTANEA], ReferralInstitutionType::values());
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    public static function label(string $value): string
    {
        return match ($value) {
            self::ESPONTANEA => 'Espontánea',
            default => ReferralInstitutionType::isValid($value)
                ? ReferralInstitutionType::label($value)
                : 'Sin definir',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        $items = [
            ['value' => self::ESPONTANEA, 'label' => self::label(self::ESPONTANEA)],
        ];

        return array_merge($items, ReferralInstitutionType::options());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function optionsForEntryType(string $entryType): array
    {
        if ($entryType === EntryType::DEMANDA_ESPONTANEA) {
            return [
                ['value' => self::ESPONTANEA, 'label' => self::label(self::ESPONTANEA)],
            ];
        }

        return ReferralInstitutionType::options();
    }

    public static function isLocked(string $entryType): bool
    {
        return $entryType === EntryType::DEMANDA_ESPONTANEA;
    }

    /**
     * @param array<string, mixed> $attention
     */
    public static function fromAttention(array $attention): string
    {
        $entryType = (string) ($attention['entry_type'] ?? '');

        if ($entryType === EntryType::DEMANDA_ESPONTANEA) {
            return self::ESPONTANEA;
        }

        if ($entryType === EntryType::DERIVACION) {
            $type = trim((string) ($attention['referral_institution_type'] ?? ''));

            return ReferralInstitutionType::isValid($type) ? $type : '';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $attention
     */
    public static function resolve(array $attention, mixed $submitted): string
    {
        $entryType = (string) ($attention['entry_type'] ?? '');

        if ($entryType === EntryType::DEMANDA_ESPONTANEA) {
            return self::ESPONTANEA;
        }

        $value = trim((string) $submitted);

        if (ReferralInstitutionType::isValid($value)) {
            return $value;
        }

        return self::fromAttention($attention);
    }
}
