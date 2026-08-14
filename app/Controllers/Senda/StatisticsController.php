<?php

declare(strict_types=1);

namespace App\Controllers\Senda;

use App\Services\Senda\StatisticsService;

final class StatisticsController extends SendaController
{
    public function __construct(
        private readonly StatisticsService $statistics = new StatisticsService()
    ) {
    }

    public function index(): void
    {
        $this->sendaView('statistics/index', [
            'title' => 'Estadísticas — SENDA',
            'tables' => $this->statistics->tables(),
        ]);
    }
}
