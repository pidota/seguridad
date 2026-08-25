<?php

declare(strict_types=1);

namespace App\Models\Cctv;

final class LogHandover
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_NOT_CONTINUED = 'not_continued';
    public const STATUS_COMPLETED_BEFORE_REVIEW = 'completed_before_review';

    public const DECISION_CONTINUE = 'continue';
    public const DECISION_STOP = 'stop';

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, [
            self::STATUS_PENDING,
            self::STATUS_ACCEPTED,
            self::STATUS_NOT_CONTINUED,
            self::STATUS_COMPLETED_BEFORE_REVIEW,
        ], true);
    }

    public static function isPending(string $status): bool
    {
        return $status === self::STATUS_PENDING;
    }
}
