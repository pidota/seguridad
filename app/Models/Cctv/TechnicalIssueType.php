<?php

declare(strict_types=1);

namespace App\Models\Cctv;

use Core\Model;

final class TechnicalIssueType extends Model
{
    public const SLUG_OTHER = 'otro';

    protected string $table = 'cctv_technical_issue_types';
}
