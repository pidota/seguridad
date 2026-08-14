<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

final class AuditLog extends Model
{
    protected string $table = 'audit_logs';
}
