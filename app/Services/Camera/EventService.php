<?php

declare(strict_types=1);

namespace App\Services\Camera;

use App\Repositories\Camera\EventRepository;
use App\Services\AuditService;
use App\Services\Cctv\CatalogService;
use Core\Auth;
use Core\Exceptions\HttpException;

final class EventService
{
    public function __construct(
        private readonly EventRepository $events = new EventRepository(),
        private readonly AuditService $audit = new AuditService(),
        private readonly CatalogService $catalogs = new CatalogService()
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function search(array $filters, int $page, int $perPage = 15): array
    {
        $page = max(1, $page);
        $result = $this->events->paginate($filters, $page, $perPage);
        $pages = max(1, (int) ceil($result['total'] / $perPage));

        return [
            'data' => array_map([$this, 'present'], $result['data']),
            'total' => $result['total'],
            'page' => min($page, $pages),
            'pages' => $pages,
        ];
    }

    public function find(int $id): array
    {
        $record = $this->events->findById($id);

        if ($record === null) {
            throw new HttpException(404, 'El evento no existe.');
        }

        $this->assertCanAccess($record);

        return $this->present($record);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'event_date' => date('Y-m-d'),
            'event_time' => date('H:i'),
            'shift' => $this->currentShift(),
            'classification' => '',
            'classification_other' => '',
            'location' => '',
            'description' => '',
            'actions_taken' => '',
        ];
    }

    public function create(array $data): int
    {
        $payload = $this->payload($data, true);
        $id = $this->events->create($payload);
        $created = $this->events->findById($id);
        $this->audit->created(
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CAMERA_EVENT,
            $id,
            $this->auditSnapshot($created ? $this->present($created) : $payload)
        );

        return $id;
    }

    public function update(int $id, array $data): void
    {
        $current = $this->find($id);
        $payload = $this->payload($data, false);
        $payload['updated_by'] = Auth::id();

        $this->events->update($id, $payload);
        $updated = $this->events->findById($id);
        $presented = $updated ? $this->present($updated) : array_merge($current, $payload);

        $this->audit->updated(
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CAMERA_EVENT,
            $id,
            $this->auditSnapshot($current),
            $this->auditSnapshot($presented)
        );
    }

    public function countToday(): int
    {
        return $this->events->countToday();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function operatorOptions(): array
    {
        return $this->events->operatorOptions();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $classification = (string) ($row['classification'] ?? '');
        $time = trim((string) ($row['event_time'] ?? ''));
        $other = trim((string) ($row['classification_other'] ?? ''));

        $row['event_time'] = $time === '' ? '' : substr($time, 0, 5);
        $row['shift_label'] = EventCatalog::label(EventCatalog::shifts(), $row['shift'] ?? null);
        $row['classification_label'] = $this->catalogs->incidentTypeLabel($classification);
        $row['classification_tone'] = $this->catalogs->incidentTypeTone($classification);

        if ($this->catalogs->isOtherIncidentType($classification) && $other !== '') {
            $row['classification_label'] .= ': ' . $other;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(array $data, bool $creating): array
    {
        $shift = trim((string) ($data['shift'] ?? ''));
        $classification = trim((string) ($data['classification'] ?? ''));

        if (!EventCatalog::isValidShift($shift)) {
            throw new HttpException(422, 'Seleccione un turno válido.');
        }

        if (!$this->catalogs->isValidIncidentTypeSlug($classification)) {
            throw new HttpException(422, 'Seleccione una clasificación válida.');
        }

        $other = $this->catalogs->isOtherIncidentType($classification)
            ? $this->nullable($data['classification_other'] ?? null)
            : null;

        if ($this->catalogs->isOtherIncidentType($classification) && $other === null) {
            throw new HttpException(422, 'Especifique la clasificación.');
        }

        $location = trim((string) ($data['location'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        if ($location === '') {
            throw new HttpException(422, 'Indique la ubicación o cámara.');
        }

        if ($description === '') {
            throw new HttpException(422, 'Describa la novedad registrada.');
        }

        $result = [
            'event_date' => trim((string) ($data['event_date'] ?? '')),
            'event_time' => $this->nullable($data['event_time'] ?? null),
            'shift' => $shift,
            'classification' => $classification,
            'classification_other' => $other,
            'location' => $location,
            'description' => $description,
            'actions_taken' => $this->nullable($data['actions_taken'] ?? null),
        ];

        if ($creating) {
            $result['created_by'] = Auth::id();
            $result['updated_by'] = Auth::id();
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function auditSnapshot(array $row): array
    {
        $snapshot = AuditService::pick($row, [
            'id',
            'event_date',
            'shift',
            'shift_label',
            'classification',
            'classification_label',
            'classification_other',
            'location',
            'description',
            'actions_taken',
            'created_by_name',
            'updated_by_name',
        ]);
        $time = trim((string) ($row['event_time'] ?? ''));
        $snapshot['event_time'] = $time === '' ? null : substr($time, 0, 5);

        return $snapshot;
    }

    private function currentShift(): string
    {
        $hour = (int) date('G');

        if ($hour >= 7 && $hour < 15) {
            return 'manana';
        }

        if ($hour >= 15 && $hour < 23) {
            return 'tarde';
        }

        return 'noche';
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function assertCanAccess(array $record): void
    {
        if (hasPermission('cctv.log.view_all')) {
            return;
        }

        $userId = Auth::id();
        if ($userId === null || (int) ($record['created_by'] ?? 0) !== $userId) {
            throw new HttpException(403, 'No puede consultar registros de otros operadores.');
        }
    }
}
