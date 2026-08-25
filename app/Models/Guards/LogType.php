<?php

declare(strict_types=1);

namespace App\Models\Guards;

use Core\Model;

final class LogType extends Model
{
    public const SLUG_NOVEDAD = 'novedad';
    public const SLUG_RONDA = 'ronda';
    public const SLUG_INCIDENTE = 'incidencia';
    public const SLUG_COORDINACION = 'coordinacion';

    protected string $table = 'guards_log_types';
}
