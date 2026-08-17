<?php

declare(strict_types=1);

namespace App\Controllers\Senda;

use App\Services\Senda\EntryType;
use App\Services\Senda\EntryTypeContext;
use App\Services\Senda\PersonContext;
use App\Services\Senda\PersonService;
use App\Validators\Senda\PersonLookupValidator;
use Core\Request;
use Core\Session;

final class EntryTypeController extends SendaController
{
    public function __construct(
        private readonly PersonService $people = new PersonService()
    ) {
    }

    public function index(Request $request): void
    {
        $next = $this->nextTarget($request);
        $step = trim((string) $request->query('step', ''));

        if ($step === 'tipo') {
            $this->showTypeSelection($request, $next);

            return;
        }

        if ($next === 'attention') {
            $person = $this->people->current();

            if ($person === null) {
                Session::flashAlert(
                    'info',
                    'Identificar persona',
                    'Ingrese el RUT de la persona antes de continuar.'
                );
                $this->showRutForm($request);

                return;
            }

            $this->showTypeSelection($request, 'attention', $person);

            return;
        }

        PersonContext::forget();
        $this->showRutForm($request);
    }

    public function lookup(Request $request): void
    {
        $payload = [
            'rut' => trim((string) $request->input('rut', '')),
        ];

        $errors = (new PersonLookupValidator())->validate($payload);
        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            $this->redirect(url('/senda'));
        }

        try {
            $result = $this->people->lookup($payload['rut']);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndBack($e);
        }

        if ($result['exists'] && !empty($result['person']['id'])) {
            try {
                $this->people->use((int) $result['person']['id']);
            } catch (\Throwable $e) {
                $this->failAndBack($e);
            }

            $this->redirect(url('/senda') . '?next=attention');
        }

        if (!hasPermission('senda.people.create')) {
            Session::flashAlert(
                'warning',
                'Persona no registrada',
                'No hay una persona con ese RUT y su perfil no puede registrar nuevas personas.'
            );
            $this->showRutForm($request, $result['rut'], false);

            return;
        }

        $this->showRutForm($request, $result['rut'], false);
    }

    public function store(Request $request): void
    {
        $type = trim((string) $request->input('tipo_ingreso', ''));
        $next = $this->nextTarget($request);
        $personId = PersonContext::id();

        if ($next === 'attention' && $personId === null) {
            Session::flashAlert(
                'info',
                'Identificar persona',
                'Ingrese el RUT de la persona antes de seleccionar el tipo de atención.'
            );
            $this->redirect(url('/senda'));
        }

        if (EntryType::isFollowUpMenuOption($type)) {
            if (!hasPermission('senda.followups.view')) {
                Session::flashAlert('warning', 'Acceso denegado', 'No puede acceder al módulo de seguimiento.');
                $this->redirect(url('/senda') . ($next === 'attention' ? '?next=attention' : '?step=tipo'));
            }

            if ($personId !== null) {
                $this->redirect(url('/senda/follow-ups/person/' . $personId));
            }

            $this->redirect(url('/senda/follow-ups'));
        }

        if (!EntryType::isValid($type)) {
            Session::flashAlert(
                'error',
                'Atención',
                'Seleccione una opción válida para continuar.'
            );
            $this->redirect($this->typeSelectionUrl($next));
        }

        EntryTypeContext::remember($type);

        if ($next === 'attention' && hasPermission('senda.attentions.create')) {
            $this->redirect(url('/senda/attentions/create'));
        }

        $this->redirect(url('/senda/dashboard') . '?' . http_build_query([
            EntryTypeContext::QUERY_KEY => $type,
        ]));
    }

    private function showRutForm(Request $request, ?string $rut = null, ?bool $exists = null): void
    {
        $this->sendaView('entry/rut', [
            'title' => 'Atención — SENDA',
            'rut' => $rut ?? (string) old('rut', PersonContext::lookupRut() ?? ''),
            'exists' => $exists,
            'showSendaEntryBanner' => false,
        ]);
    }

    /**
     * @param array<string, mixed>|null $person
     */
    private function showTypeSelection(Request $request, string $next = '', ?array $person = null): void
    {
        $this->sendaView('entry/index', [
            'title' => 'Atención — SENDA',
            'options' => EntryType::attentionMenuOptions(hasPermission('senda.followups.view')),
            'currentEntryType' => EntryTypeContext::current(),
            'next' => $next,
            'person' => $person,
            'showSendaEntryBanner' => false,
        ]);
    }

    private function typeSelectionUrl(string $next): string
    {
        if ($next === 'attention') {
            return url('/senda') . '?next=attention';
        }

        return url('/senda') . '?step=tipo';
    }

    private function nextTarget(Request $request): string
    {
        $next = trim((string) $request->input('next', $request->query('next', '')));

        return $next === 'attention' ? 'attention' : '';
    }
}
