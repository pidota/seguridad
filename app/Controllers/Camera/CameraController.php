<?php

declare(strict_types=1);

namespace App\Controllers\Camera;

use Core\Auth;
use Core\Controller;

abstract class CameraController extends Controller
{
    /**
     * @param array<string, mixed> $data
     */
    protected function cameraView(string $view, array $data = []): void
    {
        $this->view('camera/' . $view, array_merge([
            'user' => Auth::user(),
            'camerasNav' => $this->navigation(),
            'moduleScripts' => [],
        ], $data));
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    protected function cctvScripts(string ...$files): array
    {
        return array_map(
            static fn (string $file): string => asset('js/modules/cctv/' . $file),
            $files
        );
    }

    /**
     * @return list<array{label: string, path: string, permission: string, icon: string, exact?: bool}>
     */
    protected function navigation(): array
    {
        $items = [
            ['label' => 'Inicio', 'path' => '/cctv', 'permission' => 'cctv.dashboard.view', 'icon' => 'bi-speedometer2', 'exact' => true],
            ['label' => 'Turnos', 'path' => '/cctv/shifts', 'permission' => 'cctv.shifts.view', 'icon' => 'bi-clock-history'],
            ['label' => 'Bitácora', 'path' => '/cctv/log', 'permission' => 'cctv.log.view', 'icon' => 'bi-journal-text'],
            ['label' => 'Cámaras', 'path' => '/cctv/cameras', 'permission' => 'cctv.cameras.view', 'icon' => 'bi-camera-reels'],
        ];

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => hasPermission($item['permission'])
        ));
    }
}
