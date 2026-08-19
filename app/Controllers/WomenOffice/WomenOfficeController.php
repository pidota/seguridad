<?php

declare(strict_types=1);

namespace App\Controllers\WomenOffice;

use Core\Auth;
use Core\Controller;
use Core\Session;

abstract class WomenOfficeController extends Controller
{
    /**
     * @param array<string, mixed> $data
     */
    protected function womenView(string $view, array $data = []): void
    {
        $this->view('women-office/' . $view, array_merge($data, [
            'user' => Auth::user(),
            'womenNav' => $this->navigation(),
            'moduleScripts' => [
                asset('js/modules/women-office/people.js'),
                asset('js/modules/women-office/case.js'),
            ],
        ]));
    }

    protected function notReady(string $section): never
    {
        Session::flashAlert(
            'info',
            'Oficina de la Mujer',
            'La sección «' . $section . '» estará disponible en la siguiente etapa de implementación.'
        );
        $this->redirect(url('/women'));
    }

    /**
     * @return list<array{label: string, path: string, permission: string, icon: string, exact?: bool}>
     */
    protected function navigation(): array
    {
        $items = [
            ['label' => 'Nueva denuncia', 'path' => '/women/cases/create', 'permission' => 'women.cases.create', 'icon' => 'bi-plus-circle'],
            ['label' => 'Casos registrados', 'path' => '/women/cases', 'permission' => 'women.cases.view', 'icon' => 'bi-folder2-open'],
            ['label' => 'Seguimientos', 'path' => '/women/follow-ups', 'permission' => 'women.followups.view', 'icon' => 'bi-arrow-repeat'],
            ['label' => 'Estadísticas', 'path' => '/women/statistics', 'permission' => 'women.statistics.view', 'icon' => 'bi-graph-up'],
        ];

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => hasPermission($item['permission'])
        ));
    }
}
