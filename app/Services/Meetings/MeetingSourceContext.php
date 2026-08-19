<?php

declare(strict_types=1);

namespace App\Services\Meetings;

final class MeetingSourceContext
{
    private static ?string $module = null;

    public static function set(?string $module): void
    {
        self::$module = $module;
    }

    public static function get(): ?string
    {
        return self::$module;
    }

    public static function forget(): void
    {
        self::$module = null;
    }
}
