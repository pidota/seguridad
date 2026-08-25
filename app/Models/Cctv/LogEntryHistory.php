<?php

declare(strict_types=1);

namespace App\Models\Cctv;

final class LogEntryHistory
{
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_COORDINATION = 'coordination';
    public const ACTION_HANDOVER = 'handover';
    public const ACTION_HANDOVER_ACCEPTED = 'handover_accepted';
    public const ACTION_HANDOVER_NOT_CONTINUED = 'handover_not_continued';
    public const ACTION_STATUS_CHANGE = 'status_change';
    public const ACTION_COMPLETED = 'completed';
}
