<?php

declare(strict_types=1);

namespace App\Controllers\WomenOffice;

use App\Services\WomenOffice\WomenDashboardService;
use App\Services\WomenOffice\WomenFollowUpService;
use Core\Request;

final class DashboardController extends WomenOfficeController
{
    public function __construct(
        private readonly WomenDashboardService $dashboard = new WomenDashboardService()
    ) {
    }

    public function index(Request $request): void
    {
        $this->womenView('dashboard/index', [
            'title' => 'Oficina de la Mujer',
            'cards' => $this->cards(),
            'metrics' => hasPermission('women.statistics.view')
                ? $this->dashboard->metrics()
                : $this->dashboard->summaryMetrics(),
            'alerts' => $this->dashboard->alertCards(),
        ]);
    }

    /**
     * @return list<array{label: string, description: string, path: string, permission: string, icon: string}>
     */
    private function cards(): array
    {
        $items = [
            [
                'label' => 'Nueva denuncia / caso',
                'description' => 'Registrar un nuevo caso de violencia de género.',
                'path' => '/women/cases/create',
                'permission' => 'women.cases.create',
                'icon' => 'bi-plus-circle',
            ],
            [
                'label' => 'Casos registrados',
                'description' => 'Consultar y dar seguimiento a casos históricos.',
                'path' => '/women/cases',
                'permission' => 'women.cases.view',
                'icon' => 'bi-folder2-open',
            ],
            [
                'label' => 'Seguimientos',
                'description' => 'Revisar seguimientos pendientes y realizados.',
                'path' => '/women/follow-ups',
                'permission' => 'women.followups.view',
                'icon' => 'bi-arrow-repeat',
            ],
            [
                'label' => 'Estadísticas',
                'description' => 'Indicadores agregados del módulo.',
                'path' => '/women/statistics',
                'permission' => 'women.statistics.view',
                'icon' => 'bi-graph-up',
            ],
        ];

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => hasPermission($item['permission'])
        ));
    }
}
