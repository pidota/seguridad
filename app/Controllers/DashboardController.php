<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Auth;
use Core\Controller;
use Core\Request;
use Core\Response;

final class DashboardController extends Controller
{
    public function home(Request $request): void
    {
        if (Auth::check()) {
            Response::redirect(url('/dashboard'));
        }

        Response::redirect(url('/login'));
    }

    public function index(): void
    {
        $modules = array_values(array_filter(
            $this->modules(),
            static fn (array $module): bool => hasPermission($module['permission'])
        ));

        $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'user' => Auth::user(),
            'modules' => $modules,
        ]);
    }

    private function modules(): array
    {
        return [
            [
                'name' => 'Central de Cámaras',
                'description' => 'Monitoreo y registro de novedades de videovigilancia comunal.',
                'icon' => 'bi-camera-video',
                'route' => '/cctv',
                'permission' => 'cctv.access',
                'tone' => 'navy',
            ],
            [
                'name' => 'SENDA',
                'description' => 'Atenciones, derivaciones y seguimiento comunitario.',
                'icon' => 'bi-heart-pulse',
                'route' => '/senda',
                'permission' => 'senda.access',
                'tone' => 'teal',
            ],
            [
                'name' => 'Oficina de la Mujer',
                'description' => 'Orientación, registro y seguimiento de atenciones.',
                'icon' => 'bi-person-hearts',
                'route' => '/women',
                'permission' => 'women.access',
                'tone' => 'wine',
            ],
            [
                'name' => 'Guardias',
                'description' => 'Turnos, rondas y bitácora de novedades en terreno.',
                'icon' => 'bi-person-badge',
                'route' => '/guards',
                'permission' => 'guards.access',
                'tone' => 'gold',
            ],
        ];
    }
}
