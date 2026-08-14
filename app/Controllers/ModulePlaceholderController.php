<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Auth;
use Core\Controller;

final class ModulePlaceholderController extends Controller
{
    public function cameras(): void
    {
        $this->landing('cameras', [
            'title' => 'Central de Cámaras',
            'kicker' => 'Módulo operativo',
            'icon' => 'bi-camera-video',
            'lead' => 'Monitoreo y bitácora de novedades de videovigilancia municipal.',
            'message' => 'El registro de eventos se desarrollará sobre esta estructura, con acceso restringido a operadores autorizados.',
            'features' => [
                'Novedades de turno en central',
                'Clasificación de incidentes',
                'Consulta histórica de eventos',
            ],
        ]);
    }

    public function women(): void
    {
        $this->landing('women', [
            'title' => 'Oficina de la Mujer',
            'kicker' => 'Módulo operativo',
            'icon' => 'bi-person-hearts',
            'lead' => 'Orientación, registro y seguimiento de atenciones con resguardo de la información.',
            'message' => 'La gestión de casos se incorporará de forma progresiva, manteniendo el control de permisos desde esta base.',
            'features' => [
                'Ficha de atención',
                'Derivaciones internas',
                'Seguimiento confidencial',
            ],
        ]);
    }

    public function guards(): void
    {
        $this->landing('guards', [
            'title' => 'Guardias Municipales',
            'kicker' => 'Módulo operativo',
            'icon' => 'bi-person-badge',
            'lead' => 'Turnos, rondas y novedades de terreno para el personal de guardia.',
            'message' => 'La bitácora operativa se habilitará en una etapa posterior. El acceso a este módulo ya queda gobernado por permisos.',
            'features' => [
                'Turnos y asistencia',
                'Rondas y novedades',
                'Registro de incidencias en terreno',
            ],
        ]);
    }

    private function landing(string $view, array $data): void
    {
        $this->view($view . '/index', array_merge($data, [
            'user' => Auth::user(),
        ]));
    }
}
