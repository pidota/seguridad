<?php

declare(strict_types=1);

namespace App\Models\Cctv;

use Core\Model;

final class LogEntry extends Model
{
    public const STATUS_REGISTERED = 'registrado';
    public const STATUS_IN_PROGRESS = 'en_desarrollo';
    public const STATUS_FINISHED = 'finalizado';

    public const POLICE_ARRIVED_NO = 0;
    public const POLICE_ARRIVED_YES = 1;
    public const POLICE_ARRIVED_NOT_APPLICABLE = 2;

    /** @deprecated Use STATUS_REGISTERED */
    public const STATUS_RECORDED = self::STATUS_REGISTERED;

    protected string $table = 'cctv_log_entries';

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
