<?php

declare(strict_types=1);

namespace App\Controllers\Senda;

use App\Services\Senda\EntryFlowContext;
use App\Services\Senda\PersonContext;
use App\Services\Senda\PersonHistoryService;
use App\Services\Senda\PersonService;
use App\Support\ChileanRutValidator;
use App\Validators\Senda\PersonLookupValidator;
use App\Validators\Senda\PersonStoreValidator;
use App\Validators\Senda\PersonUpdateValidator;
use Core\Request;
use Core\Session;

final class PeopleController extends SendaController
{
    public function __construct(
        private readonly PersonService $people = new PersonService(),
        private readonly PersonHistoryService $history = new PersonHistoryService()
    ) {
    }

    public function index(Request $request): void
    {
        $search = trim((string) $request->query('q', ''));

        $this->sendaView('people/index', [
            'title' => 'Personas — SENDA',
            'people' => $this->people->all($search !== '' ? $search : null),
            'search' => $search,
        ]);
    }

    public function create(Request $request): void
    {
        $this->sendaView('people/search', [
            'title' => 'Buscar persona — SENDA',
            'rut' => (string) old('rut', (string) $request->query('rut', PersonContext::lookupRut() ?? '')),
            'found' => null,
            'exists' => null,
            'next' => $this->nextTarget($request),
        ]);
    }

    public function lookup(Request $request): void
    {
        $payload = [
            'rut' => trim((string) $request->input('rut', '')),
            'next' => $this->nextTarget($request),
        ];

        $errors = (new PersonLookupValidator())->validate($payload);
        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            $this->redirect($this->searchUrl($payload['next']));
        }

        try {
            $result = $this->people->lookup($payload['rut']);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndBack($e);
        }

        $this->sendaView('people/search', [
            'title' => 'Buscar persona — SENDA',
            'rut' => $result['rut'],
            'found' => $result['person'],
            'exists' => $result['exists'],
            'next' => $payload['next'],
        ]);
    }

    public function form(Request $request): void
    {
        $rut = trim((string) $request->query('rut', PersonContext::lookupRut() ?? ''));
        $formatted = ChileanRutValidator::format($rut);

        if ($formatted === null) {
            Session::flashAlert('info', 'RUT requerido', 'Ingrese un RUT válido antes de registrar a la persona.');
            $this->redirect($this->searchUrl($this->nextTarget($request)));
        }

        $existing = $this->people->lookup($formatted);
        if ($existing['exists']) {
            Session::flashAlert('info', 'Persona ya registrada', 'Utilice el registro existente. Una persona no se duplica al volver a SENDA.');
            $this->sendaView('people/search', [
                'title' => 'Buscar persona — SENDA',
                'rut' => $formatted,
                'found' => $existing['person'],
                'exists' => true,
                'next' => $this->nextTarget($request),
            ]);
            return;
        }

        $this->sendaView('people/form', [
            'title' => 'Nueva persona — SENDA',
            'record' => null,
            'rut' => $formatted,
            'next' => $this->nextTarget($request),
        ]);
    }

    public function store(Request $request): void
    {
        $payload = $request->all();
        $next = $this->nextTarget($request);
        $errors = (new PersonStoreValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/senda/people/create/form') . '?' . http_build_query([
                'rut' => (string) ($payload['rut'] ?? ''),
                'next' => $next,
            ]));
        }

        try {
            $id = $this->people->create($payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndBack($e);
        }

        Session::flashAlert('success', 'Persona registrada', 'El RUT quedó asociado a un único registro permanente.');
        $this->redirect($this->afterPersonUrl($id, $next));
    }

    public function show(Request $request, string $id): void
    {
        try {
            $record = $this->people->find((int) $id);
        } catch (\Throwable $e) {
            $this->failAndBack($e);
        }

        $this->sendaView('people/show', [
            'title' => $record['full_name'] . ' — SENDA',
            'record' => $record,
            'history' => $this->history->forPerson((int) $id),
        ]);
    }

    public function usePerson(Request $request, string $id): void
    {
        try {
            $person = $this->people->use((int) $id);
        } catch (\Throwable $e) {
            $this->failAndBack($e);
        }

        Session::flashAlert('success', 'Persona seleccionada', 'Se utilizará el registro existente para las atenciones.');
        $this->redirect($this->afterPersonUrl((int) $person['id'], $this->nextTarget($request)));
    }

    public function edit(Request $request, string $id): void
    {
        try {
            $record = $this->people->find((int) $id);
        } catch (\Throwable $e) {
            $this->failAndBack($e);
        }

        $this->sendaView('people/form', [
            'title' => 'Editar persona — SENDA',
            'record' => $record,
            'rut' => $record['rut'],
            'next' => '',
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $payload = $request->all();
        $errors = (new PersonUpdateValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/senda/people/' . $id . '/edit'));
        }

        try {
            $this->people->update((int) $id, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndBack($e);
        }

        Session::flashAlert('success', 'Persona actualizada', 'Los datos se guardaron correctamente.');
        $this->redirect(url('/senda/people/' . $id));
    }

    private function nextTarget(Request $request): string
    {
        $next = trim((string) $request->input('next', $request->query('next', '')));

        return $next === 'attention' ? 'attention' : '';
    }

    private function searchUrl(string $next): string
    {
        if ($next === 'attention') {
            return url('/senda');
        }

        return url('/senda/people/create');
    }

    private function afterPersonUrl(int $id, string $next): string
    {
        if ($next === 'attention' && hasPermission('senda.attentions.create')) {
            PersonContext::remember($id);
            EntryFlowContext::forget();

            return EntryFlowContext::attentionTypesUrl();
        }

        return url('/senda/people/' . $id);
    }
}
