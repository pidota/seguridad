<?php

declare(strict_types=1);

namespace App\Repositories\Cctv;

final class IncidentTypeRepository extends CatalogRepository
{
    protected function table(): string
    {
        return 'cctv_incident_types';
    }
}
