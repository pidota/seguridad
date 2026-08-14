<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\UserService;
use App\Validators\UserStoreValidator;
use App\Validators\UserUpdateValidator;
use Core\Auth;
use Core\Controller;
use Core\Request;
use Core\Session;

final class UserController extends Controller
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly RoleRepository $roles = new RoleRepository(),
        private readonly UserService $service = new UserService()
    ) {
    }

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $search = trim((string) $request->query('q', ''));
        $result = $this->users->paginate($page, 15, $search !== '' ? $search : null);
        $pages = max(1, (int) ceil($result['total'] / 15));

        $this->view('users/index', [
            'title' => 'Usuarios',
            'user' => Auth::user(),
            'users' => $result['data'],
            'total' => $result['total'],
            'page' => min($page, $pages),
            'pages' => $pages,
            'search' => $search,
        ]);
    }

    public function create(): void
    {
        $this->view('users/form', [
            'title' => 'Nuevo usuario',
            'user' => Auth::user(),
            'record' => null,
            'roles' => $this->visibleRoles(),
            'selectedRoleIds' => array_map('intval', (array) old('role_ids', [])),
        ]);
    }

    public function store(Request $request): void
    {
        $payload = $this->payload($request);
        $errors = (new UserStoreValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/users/create'));
        }

        try {
            $this->service->create($payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndBack($e);
        }

        Session::flashAlert('success', 'Usuario creado', 'La cuenta quedó registrada.');
        $this->redirect(url('/users'));
    }

    public function edit(Request $request, string $id): void
    {
        $record = $this->users->findById((int) $id);

        if ($record === null) {
            Session::flashAlert('error', 'No encontrado', 'El usuario no existe.');
            $this->redirect(url('/users'));
        }

        unset($record['password']);

        $this->view('users/form', [
            'title' => 'Editar usuario',
            'user' => Auth::user(),
            'record' => $record,
            'roles' => $this->visibleRoles(),
            'selectedRoleIds' => array_map('intval', (array) old('role_ids', array_column($record['roles'], 'id'))),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $payload = $this->payload($request);
        $errors = (new UserUpdateValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/users/' . $id . '/edit'));
        }

        try {
            $this->service->update((int) $id, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndBack($e);
        }

        Session::flashAlert('success', 'Usuario actualizado', 'Los cambios se guardaron correctamente.');
        $this->redirect(url('/users'));
    }

    public function destroy(Request $request, string $id): void
    {
        try {
            $this->service->toggleActive((int) $id);
        } catch (\Throwable $e) {
            $this->failAndBack($e);
        }

        Session::flashAlert('success', 'Estado actualizado', 'El usuario cambió de estado.');
        $this->redirect(url('/users'));
    }

    private function payload(Request $request): array
    {
        $data = $request->only(['name', 'email', 'password', 'password_confirmation']);
        $data['role_ids'] = $request->input('role_ids', []);
        $data['is_active'] = $request->input('is_active') ? 1 : 0;

        if (!is_array($data['role_ids'])) {
            $data['role_ids'] = [];
        }

        return $data;
    }

    private function visibleRoles(): array
    {
        $roles = $this->roles->all();

        if (Auth::isSuperAdmin()) {
            return $roles;
        }

        return array_values(array_filter(
            $roles,
            static fn (array $role): bool => $role['slug'] !== 'superadministrador'
        ));
    }
}
