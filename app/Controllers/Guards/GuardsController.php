<?php

declare(strict_types=1);

namespace App\Controllers\Guards;

use Core\Auth;
use Core\Controller;

abstract class GuardsController extends Controller
{
    /**
     * @param array<string, mixed> $data
     */
    protected function guardsView(string $view, array $data = []): void
    {
        $this->view('guards/' . $view, array_merge([
            'user' => Auth::user(),
            'guardsNav' => $this->navigation(),
            'moduleScripts' => [],
        ], $data));
    }

    /**
     * @return list<array{label: string, path: string, permission: string, icon: string, exact?: bool}>
     */
    protected function navigation(): array
    {
        $items = [
            ['label' => 'Inicio', 'path' => '/guards', 'permission' => 'guards.access', 'icon' => 'bi-speedometer2', 'exact' => true],
            ['label' => 'Turnos', 'path' => '/guards/shifts', 'permission' => 'guards.shifts.view', 'icon' => 'bi-clock-history'],
            ['label' => 'Bitácora', 'path' => '/guards/log', 'permission' => 'guards.log.view', 'icon' => 'bi-journal-text'],
            ['label' => 'Coordinaciones CCTV', 'path' => '/guards/cctv-coordinations', 'permission' => 'guards.log.view', 'icon' => 'bi-camera-reels'],
        ];

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => hasPermission($item['permission'])
        ));
    }
}
