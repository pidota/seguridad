<?php

declare(strict_types=1);

namespace App\Controllers\Senda;

use App\Services\Senda\EntryTypeContext;
use Core\Auth;
use Core\Controller;
use Core\Session;

abstract class SendaController extends Controller
{
    /**
     * @param array<string, mixed> $data
     */
    protected function sendaView(string $view, array $data = []): void
    {
        $payload = array_merge([
            'sendaEntry' => EntryTypeContext::meta(),
            'showSendaEntryBanner' => true,
        ], $data, [
            'user' => Auth::user(),
            'sendaNav' => $this->navigation(),
            'moduleScripts' => [
                asset('js/modules/senda/entry-type.js'),
                asset('js/modules/senda/people.js'),
                asset('js/modules/senda/attention.js'),
                asset('js/modules/senda/referral.js'),
                asset('js/modules/senda/assist.js'),
                asset('js/modules/senda/followup.js'),
            ],
        ]);

        if (isset($data['entryType']) && is_array($data['entryType'])) {
            $payload['sendaEntry'] = $data['entryType'];
        }

        $this->view('senda/' . $view, $payload);
    }

    protected function requireEntryType(string $next = ''): string
    {
        $type = EntryTypeContext::current();

        if ($type === null) {
            Session::flashAlert(
                'info',
                'Tipo de ingreso',
                'Seleccione el tipo de ingreso antes de continuar.'
            );
            $this->redirect($next === 'attention' ? url('/senda') . '?next=attention' : url('/senda') . '?step=tipo');
        }

        return $type;
    }

    protected function notReady(string $section): never
    {
        Session::flashAlert(
            'info',
            'SENDA en preparación',
            'La operación de ' . $section . ' quedará disponible en la siguiente etapa. La ruta y los permisos ya están activos.'
        );
        $this->back();
    }

    /**
     * @return list<array{label: string, path: string, permission: string, icon: string, exact?: bool}>
     */
    protected function navigation(): array
    {
        $items = [
            ['label' => 'Registro de Atención', 'path' => '/senda/attentions', 'permission' => 'senda.attentions.view', 'icon' => 'bi-clipboard2-pulse', 'exact' => false],
            ['label' => 'Ficha de Referencia', 'path' => '/senda/referrals', 'permission' => 'senda.referrals.view', 'icon' => 'bi-file-earmark-medical', 'exact' => false],
            ['label' => 'Personas', 'path' => '/senda/people', 'permission' => 'senda.people.view', 'icon' => 'bi-people', 'exact' => false],
            ['label' => 'Estadísticas', 'path' => '/senda/statistics', 'permission' => 'senda.statistics.view', 'icon' => 'bi-graph-up', 'exact' => false],
        ];

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => hasPermission($item['permission'])
        ));
    }
}
