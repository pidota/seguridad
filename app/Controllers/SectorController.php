<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SectorRepository;
use App\Services\SectorService;
use App\Validators\SectorStoreValidator;
use Core\Auth;
use Core\Controller;
use Core\Request;
use Core\Session;

final class SectorController extends Controller
{
    public function __construct(
        private readonly SectorRepository $sectors = new SectorRepository(),
        private readonly SectorService $service = new SectorService()
    ) {
    }

    public function index(): void
    {
        $this->view('sectors/index', [
            'title' => 'Sectores',
            'user' => Auth::user(),
            'sectors' => $this->sectors->all(),
        ]);
    }

    public function create(): void
    {
        $this->view('sectors/form', [
            'title' => 'Nuevo sector',
            'user' => Auth::user(),
            'record' => null,
        ]);
    }

    public function store(Request $request): void
    {
        $payload = $this->payload($request);
        $errors = (new SectorStoreValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/sectors/create'));
        }

        try {
            $this->service->create($payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndBack($e);
        }

        Session::flashAlert('success', 'Sector creado', 'El sector quedó disponible para CCTV y bitácora.');
        $this->redirect(url('/sectors'));
    }

    public function edit(Request $request, string $id): void
    {
        $record = $this->sectors->findForAdmin((int) $id);

        if ($record === null) {
            Session::flashAlert('error', 'No encontrado', 'El sector no existe.');
            $this->redirect(url('/sectors'));
        }

        $this->view('sectors/form', [
            'title' => 'Editar sector',
            'user' => Auth::user(),
            'record' => $record,
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $payload = $this->payload($request);
        $errors = (new SectorStoreValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/sectors/' . $id . '/edit'));
        }

        try {
            $this->service->update((int) $id, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndBack($e);
        }

        Session::flashAlert('success', 'Sector actualizado', 'Los cambios se guardaron correctamente.');
        $this->redirect(url('/sectors'));
    }

    public function destroy(Request $request, string $id): void
    {
        try {
            $this->service->delete((int) $id);
        } catch (\Throwable $e) {
            $this->failAndBack($e);
        }

        Session::flashAlert('success', 'Sector eliminado', 'El sector fue dado de baja.');
        $this->redirect(url('/sectors'));
    }

    private function payload(Request $request): array
    {
        $data = $request->only(['name', 'slug', 'description', 'sort_order']);
        $data['is_active'] = $request->input('is_active') ? '1' : '0';

        return $data;
    }
}
