<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Repositories\Cctv\EquipmentRepository;

final class EquipmentService
{
    public function __construct(
        private readonly EquipmentRepository $equipment = new EquipmentRepository()
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActive(): array
    {
        return $this->equipment->listActive();
    }
}
