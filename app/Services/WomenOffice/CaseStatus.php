<?php

declare(strict_types=1);

namespace App\Services\WomenOffice;

final class CaseStatus
{
    public const REGISTERED = 'registered';
    public const ACTIVE = 'active';
    public const FOLLOW_UP = 'follow_up';
    public const REFERRED = 'referred';
    public const CLOSED = 'closed';
    public const CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function activeSlugs(): array
    {
        return [
            self::REGISTERED,
            self::ACTIVE,
            self::FOLLOW_UP,
            self::REFERRED,
        ];
    }

    public static function isClosedSlug(string $slug): bool
    {
        return in_array($slug, [self::CLOSED, self::CANCELLED], true);
    }
}
