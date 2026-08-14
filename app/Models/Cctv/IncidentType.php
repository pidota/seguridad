<?php

declare(strict_types=1);

namespace App\Models\Cctv;

use Core\Model;

final class IncidentType extends Model
{
    public const SLUG_OTHER = 'otro';

    protected string $table = 'cctv_incident_types';
}
