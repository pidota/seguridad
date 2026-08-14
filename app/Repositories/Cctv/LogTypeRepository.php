<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

final class LogTypeRepository extends CatalogRepository
{
    protected function table(): string
    {
        return 'cctv_log_types';
    }
}
