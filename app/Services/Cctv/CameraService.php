<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Repositories\Cctv\CameraRepository;
use App\Repositories\SectorRepository;
use App\Services\AuditService;
use Core\Exceptions\HttpException;

final class CameraService
{
    public function __construct(
        private readonly CameraRepository $cameras = new CameraRepository(),
        private readonly SectorRepository $sectors = new SectorRepository(),
        private readonly AuditService $audit = new AuditService(),
        private readonly CctvAuditService $cctvAudit = new CctvAuditService()
    ) {
    }

    public function find(int $id): array
    {
        $record = $this->cameras->findById($id);

        if ($record === null) {
            throw new HttpException(404, 'La cámara no existe.');
        }

        return $this->present($record);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function search(array $filters, int $page, int $perPage, bool $activeOnly): array
    {
        $page = max(1, $page);
        $result = $this->cameras->paginate($filters, $page, $perPage, $activeOnly);
        $pages = max(1, (int) ceil($result['total'] / $perPage));

        return [
            'data' => array_map([$this, 'present'], $result['data']),
            'total' => $result['total'],
            'page' => min($page, $pages),
            'pages' => $pages,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeOptions(): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'value' => (string) $row['code'],
            'label' => (string) $row['code'] . ' — ' . (string) $row['name'],
            'location' => (string) ($row['location'] ?? ''),
        ], $this->cameras->listActive());
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'code' => '',
            'name' => '',
            'sector_id' => '',
            'location' => '',
            'latitude' => '',
            'longitude' => '',
            'camera_type' => CameraCatalog::TYPE_FIXED,
            'status' => CameraCatalog::STATUS_OPERATIONAL,
            'active' => 1,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function monitoringOptions(): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'value' => (string) $row['code'],
            'label' => (string) $row['code'] . ' — ' . (string) $row['name'],
            'location' => (string) ($row['location'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'status_label' => CameraCatalog::statusMeta((string) ($row['status'] ?? ''))['label'],
        ], $this->cameras->listForMonitoring());
    }

    public function countMonitoringIssues(): int
    {
        return $this->cameras->countMonitoringIssues();
    }

    public function applyStatus(int $id, string $status, ?int $sourceLogEntryId = null): void
    {
        $current = $this->find($id);

        if (!CameraCatalog::isValidStatus($status)) {
            throw new HttpException(422, 'Seleccione un estado de cámara válido.');
        }

        if (($current['status'] ?? '') === $status) {
            return;
        }

        $this->cameras->update($id, [
            'code' => $current['code'],
            'name' => $current['name'],
            'sector_id' => $current['sector_id'],
            'location' => $current['location'],
            'latitude' => $current['latitude'] ?? null,
            'longitude' => $current['longitude'] ?? null,
            'camera_type' => $current['camera_type'],
            'status' => $status,
            'active' => $current['active'],
        ]);

        $updated = $this->cameras->findById($id);
        $presented = $updated ? $this->present($updated) : array_merge($current, ['status' => $status]);

        $this->cctvAudit->cameraStatusChanged(
            $id,
            $current,
            $presented,
            $sourceLogEntryId
        );
    }

    public function create(array $data): int
    {
        $payload = $this->payload($data);
        $id = $this->cameras->create($payload);
        $created = $this->cameras->findById($id);

        $this->audit->created(
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CCTV_CAMERA,
            $id,
            $this->cctvAudit->sanitizeCamera($created ? $this->present($created) : $payload)
        );

        return $id;
    }

    public function update(int $id, array $data): void
    {
        $current = $this->find($id);
        $payload = $this->payload($data, $id);

        $this->cameras->update($id, $payload);
        $updated = $this->cameras->findById($id);
        $presented = $updated ? $this->present($updated) : array_merge($current, $payload);

        $this->audit->updated(
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CCTV_CAMERA,
            $id,
            $this->cctvAudit->sanitizeCamera($current),
            $this->cctvAudit->sanitizeCamera($presented)
        );
    }

    public function delete(int $id): void
    {
        $current = $this->find($id);
        $this->cameras->softDelete($id);

        $this->audit->deleted(
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CCTV_CAMERA,
            $id,
            $this->cctvAudit->sanitizeCamera($current)
        );
    }

    /**
     * @return list<array{id: int, slug: string, name: string}>
     */
    public function sectorOptions(): array
    {
        return $this->sectors->options();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForMap(bool $activeOnly = false): array
    {
        return array_map(
            fn (array $row): array => $this->present($row),
            $this->cameras->listWithCoordinates($activeOnly)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function mapConfig(): array
    {
        return [
            'defaultLat' => (float) cctv_config('map_default_latitude', -33.4489),
            'defaultLng' => (float) cctv_config('map_default_longitude', -70.6693),
            'defaultZoom' => (int) cctv_config('map_default_zoom', 13),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $status = (string) ($row['status'] ?? '');
        $meta = CameraCatalog::statusMeta($status);

        $row['camera_type_label'] = CameraCatalog::label(CameraCatalog::types(), $row['camera_type'] ?? null);
        $row['status_label'] = $meta['label'];
        $row['status_tone'] = $meta['tone'];
        $row['active_label'] = !empty($row['active']) ? 'Activa' : 'Inactiva';
        $row['sector_label'] = trim((string) ($row['sector_name'] ?? '')) !== ''
            ? (string) $row['sector_name']
            : '—';
        $row['has_coordinates'] = $row['latitude'] !== null
            && $row['longitude'] !== null
            && $row['latitude'] !== ''
            && $row['longitude'] !== '';

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(array $data, ?int $exceptId = null): array
    {
        $code = mb_strtoupper(trim((string) ($data['code'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        $cameraType = trim((string) ($data['camera_type'] ?? ''));
        $status = trim((string) ($data['status'] ?? ''));

        if ($code === '') {
            throw new HttpException(422, 'Indique el código de la cámara.');
        }

        if ($name === '') {
            throw new HttpException(422, 'Indique el nombre de la cámara.');
        }

        if (!CameraCatalog::isValidType($cameraType)) {
            throw new HttpException(422, 'Seleccione un tipo de cámara válido.');
        }

        if (!CameraCatalog::isValidStatus($status)) {
            throw new HttpException(422, 'Seleccione un estado válido.');
        }

        if ($this->cameras->codeExists($code, $exceptId)) {
            throw new HttpException(422, 'Ya existe una cámara con ese código.');
        }

        $sectorId = trim((string) ($data['sector_id'] ?? ''));
        $sectorId = $sectorId === '' ? null : (int) $sectorId;

        if ($sectorId !== null && $this->sectors->findById($sectorId) === null) {
            throw new HttpException(422, 'Seleccione un sector válido.');
        }

        return [
            'code' => $code,
            'name' => $name,
            'sector_id' => $sectorId,
            'location' => $this->nullable($data['location'] ?? null),
            'latitude' => $this->nullableCoordinate($data['latitude'] ?? null, -90.0, 90.0),
            'longitude' => $this->nullableCoordinate($data['longitude'] ?? null, -180.0, 180.0),
            'camera_type' => $cameraType,
            'status' => $status,
            'active' => !empty($data['active']) ? 1 : 0,
        ];
    }

    private function nullableCoordinate(mixed $value, float $min, float $max): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new HttpException(422, 'Las coordenadas del mapa no son válidas.');
        }

        $float = (float) $value;
        if ($float < $min || $float > $max) {
            throw new HttpException(422, 'Las coordenadas del mapa están fuera de rango.');
        }

        return round($float, 7);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
