<?php

declare(strict_types=1);

namespace App\Models\Cctv;

use Core\Model;

final class LogType extends Model
{
    public const SLUG_OTHER = 'otro';
    public const SLUG_INCIDENT = 'incidente';
    public const SLUG_TECHNICAL = 'novedad_tecnica';

    protected string $table = 'cctv_log_types';
}
