<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

final class TechnicalIssueTypeRepository extends CatalogRepository
{
    protected function table(): string
    {
        return 'cctv_technical_issue_types';
    }
}
