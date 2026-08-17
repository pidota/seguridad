<?php

declare(strict_types=1);

namespace App\Services\Senda;

use App\Repositories\Senda\PersonRepository;
use App\Services\AuditService;
use App\Support\ChileanRutValidator;
use Core\Exceptions\HttpException;

final class PersonService
{
    public function __construct(
        private readonly PersonRepository $people = new PersonRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function all(?string $search = null): array
    {
        $term = $this->searchTerm($search);

        return array_map([$this, 'present'], $this->people->search($term));
    }

    public function find(int $id, bool $withDeleted = false): array
    {
        $record = $this->people->findById($id, $withDeleted);

        if ($record === null) {
            throw new HttpException(404, 'La persona no existe.');
        }

        return $this->present($record);
    }

    public function current(): ?array
    {
        $id = PersonContext::id();

        if ($id === null) {
            return null;
        }

        $record = $this->people->findById($id);

        return $record === null ? null : $this->present($record);
    }

    public function lookup(string $rut): array
    {
        $normalized = ChileanRutValidator::normalize($rut);

        if ($normalized === null) {
            throw new HttpException(422, 'El RUT ingresado no es válido.');
        }

        $formatted = ChileanRutValidator::format($normalized) ?? $rut;
        PersonContext::rememberLookupRut($formatted);

        $record = $this->people->findByNormalizedRut($normalized, true);

        return [
            'rut' => $formatted,
            'exists' => $record !== null,
            'person' => $record === null ? null : $this->present($record),
        ];
    }

    public function create(array $data): int
    {
        $payload = $this->payload($data);
        $existing = $this->people->findByNormalizedRut($payload['rut_normalized'], true);

        if ($existing !== null) {
            throw new HttpException(422, 'Ya existe una persona registrada con este RUT. Utilice el registro existente.');
        }

        try {
            $id = $this->people->create($payload);
        } catch (\PDOException $e) {
            if ((string) ($e->errorInfo[0] ?? '') === '23000') {
                throw new HttpException(422, 'Ya existe una persona registrada con este RUT. Utilice el registro existente.');
            }

            throw $e;
        }

        $created = $this->people->findById($id);
        $this->audit->created(
            AuditService::MODULE_SENDA,
            AuditService::RESOURCE_PERSON,
            $id,
            $this->auditSnapshot($created ?? $payload)
        );
        PersonContext::remember($id);

        return $id;
    }

    public function update(int $id, array $data): void
    {
        $current = $this->find($id);

        foreach (['motivo', 'orientaciones', 'gestion'] as $field) {
            if (!array_key_exists($field, $data)) {
                $data[$field] = $current[$field] ?? null;
            }
        }

        $payload = $this->payload($data, $id);

        try {
            $this->people->update($id, $payload);
        } catch (\PDOException $e) {
            if ((string) ($e->errorInfo[0] ?? '') === '23000') {
                throw new HttpException(422, 'Ya existe una persona registrada con este RUT.');
            }

            throw $e;
        }

        $updated = $this->people->findById($id);
        $this->audit->updated(
            AuditService::MODULE_SENDA,
            AuditService::RESOURCE_PERSON,
            $id,
            $this->auditSnapshot($current),
            $this->auditSnapshot($updated ?? $payload)
        );
    }

    public function use(int $id): array
    {
        $record = $this->people->findById($id, true);

        if ($record === null) {
            throw new HttpException(404, 'La persona no existe.');
        }

        if ($record['deleted_at'] !== null) {
            $previous = $record;
            $this->people->restore($id);
            $record = $this->people->findById($id);
            $this->audit->restored(
                AuditService::MODULE_SENDA,
                AuditService::RESOURCE_PERSON,
                $id,
                $this->auditSnapshot($previous),
                $this->auditSnapshot($record ?? [])
            );
        }

        PersonContext::remember($id);

        return $this->present($record ?? $this->find($id));
    }

    public function attentions(int $personId): array
    {
        $this->find($personId);

        return $this->people->attentionsFor($personId);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $row['full_name'] = self::fullName($row);
        $row['age'] = self::age($row['birth_date'] ?? null);
        $row['is_deleted'] = !empty($row['deleted_at']);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function auditSnapshot(array $row): array
    {
        return AuditService::pick($row, [
            'id',
            'first_names',
            'paternal_surname',
            'maternal_surname',
            'rut',
            'birth_date',
            'address',
            'phone',
            'email',
            'education',
            'occupation',
            'motivo',
            'orientaciones',
            'gestion',
            'deleted_at',
        ]);
    }

    public static function fullName(array $row): string
    {
        return trim(implode(' ', array_filter([
            trim((string) ($row['first_names'] ?? '')),
            trim((string) ($row['paternal_surname'] ?? '')),
            trim((string) ($row['maternal_surname'] ?? '')),
        ], static fn (string $part): bool => $part !== '')));
    }

    public static function age(null|string $birthDate, ?\DateTimeInterface $on = null): ?int
    {
        if ($birthDate === null || trim($birthDate) === '') {
            return null;
        }

        $birth = \DateTimeImmutable::createFromFormat('Y-m-d', substr($birthDate, 0, 10));

        if (!$birth instanceof \DateTimeImmutable) {
            return null;
        }

        $onDate = $on instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($on)
            : new \DateTimeImmutable('today');

        return (int) $birth->diff($onDate)->y;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(array $data, ?int $exceptId = null): array
    {
        $normalized = ChileanRutValidator::normalize((string) ($data['rut'] ?? ''));

        if ($normalized === null) {
            throw new HttpException(422, 'El RUT ingresado no es válido.');
        }

        $existing = $this->people->findByNormalizedRut($normalized, true);

        if ($existing !== null && (int) $existing['id'] !== (int) $exceptId) {
            throw new HttpException(422, 'Ya existe una persona registrada con este RUT.');
        }

        $birthDate = trim((string) ($data['birth_date'] ?? ''));
        $this->assertBirthDate($birthDate);

        return [
            'first_names' => trim((string) ($data['first_names'] ?? '')),
            'paternal_surname' => trim((string) ($data['paternal_surname'] ?? '')),
            'maternal_surname' => $this->nullable($data['maternal_surname'] ?? null),
            'rut' => ChileanRutValidator::format($normalized) ?? $normalized,
            'rut_normalized' => $normalized,
            'birth_date' => $birthDate,
            'address' => $this->nullable($data['address'] ?? null),
            'phone' => $this->nullable($data['phone'] ?? null),
            'email' => $this->nullable($data['email'] ?? null),
            'education' => $this->nullable($data['education'] ?? null),
            'occupation' => $this->nullable($data['occupation'] ?? null),
            'motivo' => $this->nullable($data['motivo'] ?? null),
            'orientaciones' => $this->nullable($data['orientaciones'] ?? null),
            'gestion' => $this->nullable($data['gestion'] ?? null),
        ];
    }

    private function assertBirthDate(string $birthDate): void
    {
        $birth = \DateTimeImmutable::createFromFormat('Y-m-d', $birthDate);

        if (!$birth instanceof \DateTimeImmutable) {
            throw new HttpException(422, 'La fecha de nacimiento no es válida.');
        }

        $today = new \DateTimeImmutable('today');

        if ($birth > $today) {
            throw new HttpException(422, 'La fecha de nacimiento no puede ser futura.');
        }

        if ((int) $birth->diff($today)->y > 120) {
            throw new HttpException(422, 'La fecha de nacimiento no es consistente.');
        }
    }

    private function searchTerm(?string $search): ?string
    {
        $term = trim((string) $search);

        if ($term === '') {
            return null;
        }

        $normalized = ChileanRutValidator::normalize($term) ?? ChileanRutValidator::clean($term);

        return $normalized ?? $term;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
