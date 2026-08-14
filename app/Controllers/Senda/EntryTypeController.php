<?php

declare(strict_types=1);

namespace App\Controllers\Senda;

use App\Services\Senda\EntryType;
use App\Services\Senda\EntryTypeContext;
use Core\Request;
use Core\Session;

final class EntryTypeController extends SendaController
{
    public function index(Request $request): void
    {
        $this->sendaView('entry/index', [
            'title' => 'Tipo de ingreso — SENDA',
            'options' => EntryType::options(),
            'currentEntryType' => EntryTypeContext::current(),
            'next' => $this->nextTarget($request),
            'showSendaEntryBanner' => false,
        ]);
    }

    public function store(Request $request): void
    {
        $type = trim((string) $request->input('tipo_ingreso', ''));
        $next = $this->nextTarget($request);

        if (!EntryType::isValid($type)) {
            Session::flashAlert(
                'error',
                'Tipo de ingreso',
                'Seleccione Derivación o Demanda Espontánea para continuar.'
            );
            $this->redirect(url('/senda') . ($next === 'attention' ? '?next=attention' : ''));
        }

        EntryTypeContext::remember($type);

        if ($next === 'attention' && hasPermission('senda.attentions.create')) {
            $this->redirect(url('/senda/attentions/create'));
        }

        $this->redirect(url('/senda/dashboard') . '?' . http_build_query([
            EntryTypeContext::QUERY_KEY => $type,
        ]));
    }

    private function nextTarget(Request $request): string
    {
        $next = trim((string) $request->input('next', $request->query('next', '')));

        return $next === 'attention' ? 'attention' : '';
    }
}
