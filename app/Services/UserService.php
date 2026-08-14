<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use Core\Auth;
use Core\Exceptions\HttpException;
use Core\Permission;

final class UserService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly RoleRepository $roles = new RoleRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function create(array $data): int
    {
        $roleIds = $this->sanitizeRoleIds($data['role_ids'] ?? []);

        if ($this->users->emailExists($data['email'])) {
            throw new HttpException(422, 'El correo electrónico ya está registrado.');
        }

        $id = $this->users->create([
            'name' => trim($data['name']),
            'email' => trim($data['email']),
            'password' => $data['password'],
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'must_change_password' => 1,
        ]);

        $this->users->syncRoles($id, $roleIds);
        Permission::flush();
        Auth::forgetCache();

        $created = $this->users->findById($id);
        $this->audit->created('users', 'users', $id, $this->snapshot($created));

        return $id;
    }

    public function update(int $id, array $data): void
    {
        $current = $this->users->findById($id);

        if ($current === null) {
            throw new HttpException(404, 'El usuario no existe.');
        }

        $this->guardProtectedUser($current);

        if ($this->users->emailExists($data['email'], $id)) {
            throw new HttpException(422, 'El correo electrónico ya está registrado.');
        }

        $roleIds = $this->sanitizeRoleIds($data['role_ids'] ?? []);
        $this->guardLastSuperAdmin($id, $roleIds, !empty($data['is_active']));

        $old = $this->snapshot($current);

        $this->users->update($id, [
            'name' => trim($data['name']),
            'email' => trim($data['email']),
            'password' => $data['password'] ?? '',
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);
        $this->users->syncRoles($id, $roleIds);

        Permission::flush();
        Auth::forgetCache();

        $updated = $this->users->findById($id);
        $this->audit->updated('users', 'users', $id, $old, $this->snapshot($updated));
    }

    public function toggleActive(int $id): void
    {
        $current = $this->users->findById($id);

        if ($current === null) {
            throw new HttpException(404, 'El usuario no existe.');
        }

        if (Auth::id() === $id) {
            throw new HttpException(403, 'No puede desactivar su propia cuenta.');
        }

        $this->guardProtectedUser($current);

        $activating = empty($current['is_active']);

        if (!$activating) {
            $this->guardLastSuperAdmin($id, array_column($current['roles'], 'id'), false);
        }

        $old = $this->snapshot($current);
        $this->users->setActive($id, $activating);

        $updated = $this->users->findById($id);
        $this->audit->log(
            $activating ? 'activated' : 'deactivated',
            'users',
            'users',
            $id,
            $old,
            $this->snapshot($updated)
        );
    }

    private function sanitizeRoleIds(array $roleIds): array
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));

        if ($roleIds === []) {
            throw new HttpException(422, 'Debe asignar al menos un rol.');
        }

        $valid = [];

        foreach ($roleIds as $roleId) {
            $role = $this->roles->findById($roleId);
            if ($role === null) {
                continue;
            }

            if ($role['slug'] === 'superadministrador' && !Auth::isSuperAdmin()) {
                throw new HttpException(403, 'Solo un superadministrador puede asignar ese rol.');
            }

            $valid[] = $roleId;
        }

        if ($valid === []) {
            throw new HttpException(422, 'Los roles seleccionados no son válidos.');
        }

        return $valid;
    }

    private function guardProtectedUser(array $user): void
    {
        $isTargetSuper = in_array('superadministrador', $user['role_slugs'] ?? [], true);

        if ($isTargetSuper && !Auth::isSuperAdmin()) {
            throw new HttpException(403, 'No puede modificar un superadministrador.');
        }
    }

    private function guardLastSuperAdmin(int $userId, array $newRoleIds, bool $willBeActive): void
    {
        $super = $this->roles->findBySlug('superadministrador');

        if ($super === null) {
            return;
        }

        $hadSuper = $this->users->hasRole($userId, 'superadministrador');
        $keepsSuper = in_array((int) $super['id'], array_map('intval', $newRoleIds), true);

        if ($hadSuper && (!$keepsSuper || !$willBeActive) && $this->users->countByRoleSlug('superadministrador') <= 1) {
            throw new HttpException(403, 'Debe existir al menos un superadministrador activo.');
        }
    }

    private function snapshot(?array $user): array
    {
        if ($user === null) {
            return [];
        }

        return [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'is_active' => (int) $user['is_active'],
            'roles' => $user['role_slugs'] ?? [],
        ];
    }
}
