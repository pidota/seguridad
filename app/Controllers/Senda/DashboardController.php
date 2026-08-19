<?php

declare(strict_types=1);

namespace App\Controllers\Senda;

use App\Services\Senda\EntryType;
use App\Services\Senda\EntryTypeContext;
use App\Services\Senda\FollowUpService;
use App\Services\Senda\StatisticsService;
use Core\Request;

final class DashboardController extends SendaController
{
    public function __construct(
        private readonly StatisticsService $statistics = new StatisticsService(),
        private readonly FollowUpService $followUps = new FollowUpService()
    ) {
    }

    public function index(Request $request): void
    {
        $fromQuery = trim((string) $request->query(EntryTypeContext::QUERY_KEY, ''));

        if (EntryType::isValid($fromQuery)) {
            EntryTypeContext::remember($fromQuery);
            $type = $fromQuery;
        } else {
            $type = $this->requireEntryType();
        }

        $this->sendaView('dashboard/index', [
            'title' => 'Dashboard SENDA',
            'entryType' => EntryType::meta($type),
            'cards' => $this->cards(),
            'metrics' => $this->statistics->dashboardCards(),
            'followUpAlertPanel' => hasPermission('senda.followups.view')
                ? $this->followUps->dashboardAlertPanel()
                : null,
        ]);
    }

    private function cards(): array
    {
        $items = [
            [
                'label' => 'Registro de Atención',
                'description' => 'Registrar la atención de la persona según el tipo de ingreso seleccionado.',
                'path' => hasPermission('senda.attentions.create')
                    ? '/senda/attentions/create'
                    : '/senda/attentions',
                'permission' => 'senda.attentions.view',
                'icon' => 'bi-clipboard2-pulse',
            ],
            [
                'label' => 'Ficha de Referencia Asistida a Tratamiento',
                'description' => 'Completar la ficha de referencia asistida a tratamiento.',
                'path' => hasPermission('senda.referrals.create')
                    ? '/senda/referrals/create'
                    : '/senda/referrals',
                'permission' => 'senda.referrals.view',
                'icon' => 'bi-file-earmark-medical',
            ],
            [
                'label' => 'Seguimiento',
                'description' => 'Registrar y consultar acciones de seguimiento.',
                'path' => hasPermission('senda.followups.create')
                    ? '/senda/follow-ups/create'
                    : '/senda/follow-ups',
                'permission' => 'senda.followups.view',
                'icon' => 'bi-arrow-repeat',
            ],
            [
                'label' => 'Reuniones con Jefatura',
                'description' => 'Registrar reuniones, acuerdos y compromisos, y solicitar firma a los participantes.',
                'path' => hasPermission('senda.meetings.create')
                    ? '/senda/meetings/create'
                    : '/senda/meetings',
                'permission' => 'senda.meetings.view',
                'icon' => 'bi-journal-text',
            ],
        ];

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => hasPermission($item['permission'])
        ));
    }
}
