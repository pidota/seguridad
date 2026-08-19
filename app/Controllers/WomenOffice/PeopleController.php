<?php

declare(strict_types=1);

namespace App\Controllers\WomenOffice;

use App\Repositories\SectorRepository;
use App\Repositories\WomenOffice\CatalogRepository;
use App\Services\WomenOffice\PersonContext;
use App\Services\WomenOffice\PersonService;
use App\Support\ChileanRutValidator;
use App\Validators\WomenOffice\PersonLookupValidator;
use App\Validators\WomenOffice\PersonStoreValidator;
use App\Validators\WomenOffice\PersonUpdateValidator;
use Core\Request;
use Core\Session;

final class PeopleController extends WomenOfficeController
{
    public function __construct(
        private readonly PersonService $people = new PersonService(),
        private readonly CatalogRepository $catalogs = new CatalogRepository()
    ) {
    }

    public function create(Request $request): void
    {
        $this->womenView('people/search', [
            'title' => 'Persona afectada — Oficina de la Mujer',
            'rut' => (string) old('rut', (string) $request->query('rut', PersonContext::lookupRut() ?? '')),
            'found' => null,
            'exists' => null,
        ]);
    }

    public function lookup(Request $request): void
    {
        $payload = ['rut' => trim((string) $request->input('rut', ''))];
        $errors = (new PersonLookupValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            $this->redirect(url('/women/cases/create/person'));
        }

        try {
            $result = $this->people->lookup($payload['rut']);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndBack($e);
        }

        $this->womenView('people/search', [
            'title' => 'Persona afectada — Oficina de la Mujer',
            'rut' => $result['rut'],
            'found' => $result['person'],
            'exists' => $result['exists'],
        ]);
    }

    public function form(Request $request): void
    {
        $rut = trim((string) $request->query('rut', PersonContext::lookupRut() ?? ''));
        $formatted = ChileanRutValidator::format($rut);

        if ($formatted === null) {
            Session::flashAlert('info', 'RUT requerido', 'Ingrese un RUT válido antes de registrar a la persona.');
            $this->redirect(url('/women/cases/create/person'));
        }

        $existing = $this->people->lookup($formatted);
        if ($existing['exists']) {
            Session::flashAlert('info', 'Persona ya registrada', 'Utilice el registro existente.');
            $this->womenView('people/search', [
                'title' => 'Persona afectada — Oficina de la Mujer',
                'rut' => $formatted,
                'found' => $existing['person'],
                'exists' => true,
            ]);

            return;
        }

        $this->womenView('people/form', [
            'title' => 'Registrar persona afectada',
            'record' => null,
            'rut' => $formatted,
            'educationLevels' => $this->catalogs->educationLevels(),
            'sectors' => (new SectorRepository())->options(),
        ]);
    }

    public function store(Request $request): void
    {
        $payload = $request->all();
        $errors = (new PersonStoreValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/women/people/create/form') . '?' . http_build_query([
                'rut' => (string) ($payload['rut'] ?? ''),
            ]));
        }

        try {
            $this->people->create($payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/women/people/create/form') . '?' . http_build_query([
                'rut' => (string) ($payload['rut'] ?? ''),
            ]));
        }

        Session::flashAlert('success', 'Persona registrada', 'Continúe con los datos del caso.');
        $this->redirect(url('/women/cases/create/register'));
    }

    public function edit(Request $request, string $id): void
    {
        $personId = (int) $id;

        try {
            $person = $this->people->find($personId);
            $this->people->assertCanEdit($personId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases'));
        }

        $returnUrl = trim((string) $request->query('return', ''));

        $this->womenView('people/form', [
            'title' => 'Editar persona afectada',
            'record' => $person,
            'rut' => (string) ($person['rut'] ?? ''),
            'isEdit' => true,
            'personId' => $personId,
            'returnUrl' => $returnUrl !== '' ? $returnUrl : null,
            'educationLevels' => $this->catalogs->educationLevels(),
            'sectors' => (new SectorRepository())->options(),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $personId = (int) $id;
        $payload = $request->all();
        $errors = (new PersonUpdateValidator())->validate($payload);

        $returnUrl = trim((string) ($payload['return_url'] ?? ''));

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/women/people/' . $personId . '/edit') . ($returnUrl !== '' ? '?' . http_build_query(['return' => $returnUrl]) : ''));
        }

        try {
            $this->people->update($personId, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/women/people/' . $personId . '/edit') . ($returnUrl !== '' ? '?' . http_build_query(['return' => $returnUrl]) : ''));
        }

        Session::flashAlert('success', 'Persona actualizada', 'Los datos de la persona afectada quedaron guardados.');

        if ($returnUrl !== '') {
            $this->redirect($returnUrl);
        }

        $this->redirect(url('/women/cases'));
    }

    public function usePerson(Request $request, string $id): void
    {
        try {
            $this->people->use((int) $id);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/women/cases/create/person'));
        }

        Session::flashAlert('success', 'Persona seleccionada', 'Complete los datos del registro del caso.');
        $this->redirect(url('/women/cases/create/register'));
    }
}
