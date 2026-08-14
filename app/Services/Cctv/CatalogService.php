<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Models\Cctv\IncidentType;
use App\Models\Cctv\LogType;
use App\Models\Cctv\TechnicalIssueType;
use App\Repositories\Cctv\EquipmentRepository;
use App\Repositories\Cctv\IncidentTypeRepository;
use App\Repositories\Cctv\LogTypeRepository;
use App\Repositories\Cctv\TechnicalIssueTypeRepository;
use Core\Exceptions\HttpException;

final class CatalogService
{
    public function __construct(
        private readonly LogTypeRepository $logTypes = new LogTypeRepository(),
        private readonly IncidentTypeRepository $incidentTypes = new IncidentTypeRepository(),
        private readonly TechnicalIssueTypeRepository $technicalIssueTypes = new TechnicalIssueTypeRepository(),
        private readonly EquipmentRepository $equipment = new EquipmentRepository()
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeLogTypes(): array
    {
        return array_map([$this, 'presentLogType'], $this->logTypes->listActive());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeIncidentTypes(): array
    {
        return array_map([$this, 'presentIncidentType'], $this->incidentTypes->listActive());
    }

    /**
     * Opciones para selects de tipos de registro.
     *
     * @return list<array{id: int, value: string, label: string, tone: string, requires_incident: bool}>
     */
    public function logTypeOptions(): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'value' => (string) $row['slug'],
            'label' => (string) $row['name'],
            'tone' => (string) ($row['tone'] ?? 'other'),
            'requires_incident' => !empty($row['requires_incident']),
        ], $this->activeLogTypes());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeTechnicalIssueTypes(): array
    {
        return array_map([$this, 'presentTechnicalIssueType'], $this->technicalIssueTypes->listActive());
    }

    /**
     * Opciones para selects de tipos de problema técnico.
     *
     * @return list<array{id: int, value: string, label: string, tone: string, allows_other: bool}>
     */
    public function technicalIssueTypeOptions(): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'value' => (string) $row['slug'],
            'label' => (string) $row['name'],
            'tone' => (string) ($row['tone'] ?? 'other'),
            'allows_other' => !empty($row['allows_other']),
        ], $this->activeTechnicalIssueTypes());
    }

    /**
     * @return list<array{id: int, value: string, label: string}>
     */
    public function equipmentOptions(): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'value' => (string) ($row['slug'] ?? ''),
            'label' => (string) ($row['name'] ?? ''),
        ], $this->equipment->listActive());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLogTypeById(int $id): ?array
    {
        $row = $this->logTypes->findById($id);

        return $row ? $this->presentLogType($row) : null;
    }

    /**
     * Opciones para selects de tipos de incidente.
     *
     * @return list<array{id: int, value: string, label: string, tone: string, allows_other: bool}>
     */
    public function incidentTypeOptions(): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'value' => (string) $row['slug'],
            'label' => (string) $row['name'],
            'tone' => (string) ($row['tone'] ?? 'other'),
            'allows_other' => !empty($row['allows_other']),
        ], $this->activeIncidentTypes());
    }

    public function isValidLogTypeSlug(string $slug): bool
    {
        return $this->logTypes->findBySlug($slug) !== null;
    }

    public function isValidIncidentTypeSlug(string $slug): bool
    {
        return $this->incidentTypes->findBySlug($slug) !== null;
    }

    public function logTypeRequiresIncident(string $slug): bool
    {
        $row = $this->logTypes->findBySlug($slug);

        return $row !== null && !empty($row['requires_incident']);
    }

    public function incidentAllowsOther(string $slug): bool
    {
        $row = $this->incidentTypes->findBySlug($slug);

        return $row !== null && !empty($row['allows_other']);
    }

    public function isOtherIncidentType(string $slug): bool
    {
        return trim($slug) === IncidentType::SLUG_OTHER;
    }

    public function isOtherLogType(string $slug): bool
    {
        return trim($slug) === LogType::SLUG_OTHER;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLogTypeBySlug(string $slug): ?array
    {
        $row = $this->logTypes->findBySlug($slug);

        return $row ? $this->presentLogType($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findIncidentTypeBySlug(string $slug): ?array
    {
        $row = $this->incidentTypes->findBySlug($slug);

        return $row ? $this->presentIncidentType($row) : null;
    }

    public function incidentTypeLabel(string $slug): string
    {
        return $this->findIncidentTypeBySlug($slug)['name'] ?? ($slug !== '' ? $slug : '—');
    }

    public function incidentTypeTone(string $slug): string
    {
        return $this->findIncidentTypeBySlug($slug)['tone'] ?? 'other';
    }

    /**
     * @param list<array{value: string, label: string}> $options
     */
    public static function label(array $options, mixed $value): string
    {
        $needle = trim((string) $value);

        if ($needle === '') {
            return '—';
        }

        foreach ($options as $option) {
            if (($option['value'] ?? '') === $needle) {
                return (string) ($option['label'] ?? $needle);
            }
        }

        return $needle;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveLogType(array $data, ?int $id = null): int
    {
        $payload = $this->logTypePayload($data);

        if ($id === null) {
            $id = $this->logTypes->create($payload);
            return $id;
        }

        $this->logTypes->update($id, $payload);

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveIncidentType(array $data, ?int $id = null): int
    {
        $payload = $this->incidentTypePayload($data);

        if ($id === null) {
            $id = $this->incidentTypes->create($payload);
            return $id;
        }

        $this->incidentTypes->update($id, $payload);

        return $id;
    }

    public function deactivateLogType(int $id): void
    {
        $this->logTypes->deactivate($id);
    }

    public function deactivateIncidentType(int $id): void
    {
        $this->incidentTypes->deactivate($id);
    }

    public function isOtherTechnicalIssueType(string $slug): bool
    {
        return trim($slug) === TechnicalIssueType::SLUG_OTHER;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentTechnicalIssueType(array $row): array
    {
        $row['allows_other'] = !empty($row['allows_other']);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentLogType(array $row): array
    {
        $row['requires_incident'] = !empty($row['requires_incident']);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentIncidentType(array $row): array
    {
        $row['allows_other'] = !empty($row['allows_other']);

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function logTypePayload(array $data): array
    {
        $slug = $this->slugify((string) ($data['slug'] ?? $data['name'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));

        if ($slug === '' || $name === '') {
            throw new HttpException(422, 'Indique nombre y slug del tipo de registro.');
        }

        return [
            'slug' => $slug,
            'name' => $name,
            'description' => $this->nullable($data['description'] ?? null),
            'tone' => $this->nullable($data['tone'] ?? null) ?? 'other',
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'requires_incident' => !empty($data['requires_incident']) ? 1 : 0,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function incidentTypePayload(array $data): array
    {
        $slug = $this->slugify((string) ($data['slug'] ?? $data['name'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));

        if ($slug === '' || $name === '') {
            throw new HttpException(422, 'Indique nombre y slug del tipo de incidente.');
        }

        return [
            'slug' => $slug,
            'name' => $name,
            'description' => $this->nullable($data['description'] ?? null),
            'tone' => $this->nullable($data['tone'] ?? null) ?? 'other',
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'allows_other' => !empty($data['allows_other']) ? 1 : 0,
        ];
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $value
        );
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
        $value = trim($value, '_');

        return $value;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
