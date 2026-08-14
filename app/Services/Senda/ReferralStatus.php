<?php

declare(strict_types=1);

namespace App\Services\Senda;

final class ReferralStatus
{
    public const DRAFT = 'draft';
    public const COMPLETED = 'completed';

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::DRAFT, 'label' => 'Borrador'],
            ['value' => self::COMPLETED, 'label' => 'Finalizada'],
        ];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::COMPLETED => 'Finalizada',
            default => 'Borrador',
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): string
    {
        $status = trim((string) ($row['status'] ?? ''));

        if ($status === self::COMPLETED || $status === self::DRAFT) {
            return $status;
        }

        return !empty($row['is_completed']) ? self::COMPLETED : self::DRAFT;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $current
     */
    public static function resolve(array $data, ?array $current = null): string
    {
        if ($current !== null && self::fromRow($current) === self::COMPLETED) {
            return self::COMPLETED;
        }

        if (!empty($data['finalize_referral'])) {
            return self::COMPLETED;
        }

        return self::DRAFT;
    }

    public static function isCompleted(string|array $value): bool
    {
        $status = is_array($value) ? self::fromRow($value) : $value;

        return $status === self::COMPLETED;
    }

    public static function isDraft(string|array $value): bool
    {
        return !self::isCompleted($value);
    }
}
