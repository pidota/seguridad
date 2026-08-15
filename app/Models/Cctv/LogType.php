<?php

declare(strict_types=1);

namespace App\Models\Cctv;

use Core\Model;

final class LogType extends Model
{
    public const SLUG_OTHER = 'otro';
    public const SLUG_INCIDENT = 'incidente';
    public const SLUG_TECHNICAL = 'novedad_tecnica';
    public const SLUG_OFFICE_VISIT = 'visita_oficina';
    public const SLUG_RECORDING_REQUEST = 'solicitud_grabacion';

    protected string $table = 'cctv_log_types';
}
