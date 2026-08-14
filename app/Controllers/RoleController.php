<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Services\RoleService;
use App\Validators\RoleStoreValidator;
use Core\Auth;
use Core\Controller;
use Core\Request;
use Core\Session;

final class RoleController extends Controller
{
    public function __construct(
        private readonly RoleRepository $roles = new RoleRepository(),
        private readonly PermissionRepository $permissions = new PermissionRepository(),
        private readonly RoleService $service = new RoleService()
    ) {
    }

    public function index(): void
    {
        $this->view('roles/index', [
            'title' => 'Roles',
            'user' => Auth::user(),
            'roles' => $this->roles->all(),
        ]);
    }

    public function create(): void
    {
        $this->view('roles/form', [
            'title' => 'Nuevo rol',
            'user' => Auth::user(),
            'record' => null,
            'grouped' => $this->permissions->groupedByModule(),
            'selectedPermissionIds' => array_map('intval', (array) old('permission_ids', [])),
        ]);
    }

    public function store(Request $request): void
    {
        $payload = $this->payload($request);
        $errors = (new RoleStoreValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/roles/create'));
        }

        try {
            $this->service->create($payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndBack($e);
        }

        Session::flashAlert('success', 'Rol creado', 'El rol quedó disponible para asignarse.');
        $this->redirect(url('/roles'));
    }

    public function edit(Request $request, string $id): void
    {
        $record = $this->roles->findById((int) $id);

        if ($record === null) {
            Session::flashAlert('error', 'No encontrado', 'El rol no existe.');
            $this->redirect(url('/roles'));
        }

        $this->view('roles/form', [
            'title' => 'Editar rol',
            'user' => Auth::user(),
            'record' => $record,
            'grouped' => $this->permissions->groupedByModule(),
            'selectedPermissionIds' => array_map('intval', (array) old('permission_ids', $record['permission_ids'])),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $payload = $this->payload($request);
        $errors = (new RoleStoreValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/roles/' . $id . '/edit'));
        }

        try {
            $this->service->update((int) $id, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndBack($e);
        }

        Session::flashAlert('success', 'Rol actualizado', 'Los permisos se sincronizaron.');
        $this->redirect(url('/roles'));
    }

    public function destroy(Request $request, string $id): void
    {
        try {
            $this->service->delete((int) $id);
        } catch (\Throwable $e) {
            $this->failAndBack($e);
        }

        Session::flashAlert('success', 'Rol eliminado', 'El rol personalizado fue eliminado.');
        $this->redirect(url('/roles'));
    }

    private function payload(Request $request): array
    {
        $data = $request->only(['name', 'slug', 'description']);
        $data['permission_ids'] = $request->input('permission_ids', []);

        if (!is_array($data['permission_ids'])) {
            $data['permission_ids'] = [];
        }

        return $data;
    }
}
