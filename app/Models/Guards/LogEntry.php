<?php

declare(strict_types=1);

namespace App\Models\Guards;

use Core\Model;

final class LogEntry extends Model
{
    public const STATUS_REGISTERED = 'registrado';
    public const STATUS_IN_PROGRESS = 'en_desarrollo';
    public const STATUS_FINISHED = 'finalizado';

    public const RELATED_ENTITY_TYPE = 'guard_log_entry';

    protected string $table = 'guards_log_entries';

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_REGISTERED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_FINISHED,
        ];
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::statuses(), true);
    }
}
