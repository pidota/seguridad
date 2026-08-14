<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SectorRepository;
use Core\Exceptions\HttpException;

final class SectorService
{
    public function __construct(
        private readonly SectorRepository $sectors = new SectorRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function create(array $data): int
    {
        $slug = $this->slugify($data['slug'] ?? $data['name']);

        if ($this->sectors->slugExists($slug)) {
            throw new HttpException(422, 'El identificador del sector ya existe.');
        }

        $id = $this->sectors->create([
            'slug' => $slug,
            'name' => trim($data['name']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);

        $created = $this->sectors->findForAdmin($id);
        $this->audit->created('sectors', 'sectors', $id, $this->snapshot($created));

        return $id;
    }

    public function update(int $id, array $data): void
    {
        $current = $this->sectors->findForAdmin($id);

        if ($current === null) {
            throw new HttpException(404, 'El sector no existe.');
        }

        $old = $this->snapshot($current);

        $this->sectors->update($id, [
            'name' => trim($data['name']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);

        $updated = $this->sectors->findForAdmin($id);
        $this->audit->updated('sectors', 'sectors', $id, $old, $this->snapshot($updated));
    }

    public function delete(int $id): void
    {
        $current = $this->sectors->findForAdmin($id);

        if ($current === null) {
            throw new HttpException(404, 'El sector no existe.');
        }

        if ($this->sectors->usageCount($id) > 0) {
            throw new HttpException(
                403,
                'No puede eliminar un sector asociado a cámaras o registros de bitácora.'
            );
        }

        $this->sectors->softDelete($id);
        $this->audit->deleted('sectors', 'sectors', $id, $this->snapshot($current));
    }

    /**
     * @param array<string, mixed>|null $sector
     * @return array<string, mixed>
     */
    private function snapshot(?array $sector): array
    {
        if ($sector === null) {
            return [];
        }

        return [
            'id' => $sector['id'],
            'slug' => $sector['slug'],
            'name' => $sector['name'],
            'description' => $sector['description'],
            'sort_order' => (int) ($sector['sort_order'] ?? 0),
            'is_active' => (int) ($sector['is_active'] ?? 0),
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

        return $value !== '' ? $value : 'sector';
    }
}
