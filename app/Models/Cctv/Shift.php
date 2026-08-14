<?php

declare(strict_types=1);

namespace App\Models\Cctv;

use Core\Model;

final class Shift extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected string $table = 'cctv_shifts';

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_CLOSED,
        ];
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::statuses(), true);
    }

    public static function isOpen(?string $status): bool
    {
        return $status === self::STATUS_OPEN;
    }

    public static function isClosed(?string $status): bool
    {
        return $status === self::STATUS_CLOSED;
    }
}
