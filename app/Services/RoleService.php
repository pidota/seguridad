<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use Core\Exceptions\HttpException;
use Core\Permission;

final class RoleService
{
    public function __construct(
        private readonly RoleRepository $roles = new RoleRepository(),
        private readonly PermissionRepository $permissions = new PermissionRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function create(array $data): int
    {
        $slug = $this->slugify($data['slug'] ?? $data['name']);

        if ($this->roles->slugExists($slug)) {
            throw new HttpException(422, 'El identificador del rol ya existe.');
        }

        $permissionIds = $this->permissions->validIds($data['permission_ids'] ?? []);

        $id = $this->roles->create([
            'slug' => $slug,
            'name' => trim($data['name']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
        ]);

        $this->roles->syncPermissions($id, $permissionIds);
        Permission::flush();

        $created = $this->roles->findById($id);
        $this->audit->created('roles', 'roles', $id, $this->snapshot($created));

        return $id;
    }

    public function update(int $id, array $data): void
    {
        $current = $this->roles->findById($id);

        if ($current === null) {
            throw new HttpException(404, 'El rol no existe.');
        }

        $old = $this->snapshot($current);

        $this->roles->update($id, [
            'name' => trim($data['name']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
        ]);

        if (($current['slug'] ?? '') !== 'superadministrador') {
            $permissionIds = $this->permissions->validIds($data['permission_ids'] ?? []);
            $this->roles->syncPermissions($id, $permissionIds);
        }

        Permission::flush();

        $updated = $this->roles->findById($id);
        $this->audit->updated('roles', 'roles', $id, $old, $this->snapshot($updated));
    }

    public function delete(int $id): void
    {
        $current = $this->roles->findById($id);

        if ($current === null) {
            throw new HttpException(404, 'El rol no existe.');
        }

        if (!empty($current['is_system'])) {
            throw new HttpException(403, 'Los roles de sistema no pueden eliminarse.');
        }

        if ($this->roles->countUsers($id) > 0) {
            throw new HttpException(403, 'No puede eliminar un rol asignado a usuarios.');
        }

        $this->roles->delete($id);
        Permission::flush();
        $this->audit->deleted('roles', 'roles', $id, $this->snapshot($current));
    }

    private function snapshot(?array $role): array
    {
        if ($role === null) {
            return [];
        }

        return [
            'id' => $role['id'],
            'slug' => $role['slug'],
            'name' => $role['name'],
            'description' => $role['description'],
            'is_system' => (int) ($role['is_system'] ?? 0),
            'permission_ids' => $role['permission_ids'] ?? [],
        ];
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
        ]);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;
        $value = trim($value, '_');

        return $value !== '' ? $value : 'rol';
    }
}
