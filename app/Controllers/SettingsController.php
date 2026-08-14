<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Auth;
use Core\Controller;

final class SettingsController extends Controller
{
    public function index(): void
    {
        $this->view('settings/index', [
            'title' => 'Configuración',
            'user' => Auth::user(),
            'appName' => (string) config('app.name'),
            'environment' => (string) config('app.env'),
            'timezone' => (string) config('app.timezone'),
            'links' => $this->links(),
        ]);
    }

    private function links(): array
    {
        $items = [
            [
                'label' => 'Usuarios',
                'description' => 'Cuentas institucionales y asignación de roles.',
                'icon' => 'bi-people',
                'route' => '/users',
                'permission' => 'users.view',
            ],
            [
                'label' => 'Roles',
                'description' => 'Perfiles de acceso y permisos asociados.',
                'icon' => 'bi-shield-lock',
                'route' => '/roles',
                'permission' => 'roles.view',
            ],
            [
                'label' => 'Permisos',
                'description' => 'Catálogo de permisos del sistema.',
                'icon' => 'bi-key',
                'route' => '/permissions',
                'permission' => 'permissions.view',
            ],
            [
                'label' => 'Sectores',
                'description' => 'Sectores territoriales usados en CCTV y otros módulos.',
                'icon' => 'bi-geo-alt',
                'route' => '/sectors',
                'permission' => 'sectors.view',
            ],
            [
                'label' => 'Auditoría',
                'description' => 'Bitácora inmutable de acciones.',
                'icon' => 'bi-journal-text',
                'route' => '/audit',
                'permission' => 'audit.view',
            ],
        ];

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => hasPermission($item['permission'])
        ));
    }
}
