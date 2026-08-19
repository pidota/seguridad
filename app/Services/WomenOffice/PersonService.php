<?php

declare(strict_types=1);

namespace App\Services\WomenOffice;

use App\Repositories\WomenOffice\CatalogRepository;
use App\Repositories\WomenOffice\CaseRepository;
use App\Repositories\WomenOffice\PersonRepository;
use App\Services\AuditService;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;

final class PersonService
{
    public function __construct(
        private readonly PersonRepository $people = new PersonRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function find(int $id, bool $withDeleted = false): array
    {
        $record = $this->people->findById($id, $withDeleted);

        if ($record === null) {
            throw new HttpException(404, 'La persona no existe.');
        }

        return $this->present($record);
    }

    /**
     * @return array{rut: string, exists: bool, person: array<string, mixed>|null}
     */
    public function lookup(string $rut): array
    {
        $normalized = \App\Support\ChileanRutValidator::normalize($rut);

        if ($normalized === null) {
            throw new HttpException(422, 'El RUT ingresado no es válido.');
        }

        $formatted = \App\Support\ChileanRutValidator::format($normalized) ?? $rut;
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
            AuditService::MODULE_WOMEN,
            AuditService::RESOURCE_WOMEN_PERSON,
            $id,
            $this->auditSnapshot($created ?? $payload)
        );
        PersonContext::remember($id);

        return $id;
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
                AuditService::MODULE_WOMEN,
                AuditService::RESOURCE_WOMEN_PERSON,
                $id,
                $this->auditSnapshot($previous),
                $this->auditSnapshot($record ?? [])
            );
        }

        PersonContext::remember($id);

        return $this->present($record ?? $this->find($id));
    }

    public function assertCanEdit(int $personId): void
    {
        if (!hasPermission('women.people.edit')) {
            throw new HttpException(403, 'No tiene permiso para editar personas.');
        }

        if (hasPermission('women.cases.view_all')) {
            return;
        }

        $userId = Auth::id();
        if ($userId === null || !$this->people->isAffectedInUserCases($personId, $userId)) {
            throw new HttpException(403, 'No tiene permiso para editar esta persona.');
        }
    }

    public function update(int $id, array $data): void
    {
        $record = $this->people->findById($id);
        if ($record === null) {
            throw new HttpException(404, 'La persona no existe.');
        }

        $this->assertCanEdit($id);
        $payload = $this->updatePayload($data);
        $before = $this->auditSnapshot($record);
        $this->people->update($id, $payload);
        $updated = $this->people->findById($id) ?? [];
        $this->audit->updated(
            AuditService::MODULE_WOMEN,
            AuditService::RESOURCE_WOMEN_PERSON,
            $id,
            $this->auditSnapshot($before),
            $this->auditSnapshot($updated)
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function updatePayload(array $data): array
    {
        $birthDate = trim((string) ($data['birth_date'] ?? ''));
        $this->assertBirthDate($birthDate);

        $safeContact = trim((string) ($data['safe_contact'] ?? ''));
        $safeNotes = $this->nullable($data['safe_contact_notes'] ?? null);

        if ($safeContact === 'restricted' && $safeNotes === null) {
            throw new HttpException(422, 'Indique las indicaciones de contacto seguro.');
        }

        if ($safeContact !== 'restricted') {
            $safeNotes = null;
        }

        return [
            'first_names' => trim((string) ($data['first_names'] ?? '')),
            'paternal_surname' => trim((string) ($data['paternal_surname'] ?? '')),
            'maternal_surname' => $this->nullable($data['maternal_surname'] ?? null),
            'birth_date' => $birthDate,
            'phone' => $this->nullable($data['phone'] ?? null),
            'email' => $this->nullable($data['email'] ?? null),
            'address' => $this->nullable($data['address'] ?? null),
            'sector_id' => $this->nullableInt($data['sector_id'] ?? null),
            'nationality' => $this->nullable($data['nationality'] ?? null),
            'occupation' => $this->nullable($data['occupation'] ?? null),
            'education_level_id' => $this->nullableInt($data['education_level_id'] ?? null),
            'safe_contact' => $safeContact !== '' ? $safeContact : null,
            'safe_contact_notes' => $safeNotes,
        ];
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

    public static function fullName(array $row): string
    {
        return trim(implode(' ', array_filter([
            trim((string) ($row['first_names'] ?? '')),
            trim((string) ($row['paternal_surname'] ?? '')),
            trim((string) ($row['maternal_surname'] ?? '')),
        ], static fn (string $part): bool => $part !== '')));
    }

    public static function age(null|string $birthDate): ?int
    {
        if ($birthDate === null || trim($birthDate) === '') {
            return null;
        }

        $birth = \DateTimeImmutable::createFromFormat('Y-m-d', substr($birthDate, 0, 10));

        if (!$birth instanceof \DateTimeImmutable) {
            return null;
        }

        return (int) $birth->diff(new \DateTimeImmutable('today'))->y;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $normalized = \App\Support\ChileanRutValidator::normalize((string) ($data['rut'] ?? ''));

        if ($normalized === null) {
            throw new HttpException(422, 'El RUT ingresado no es válido.');
        }

        $birthDate = trim((string) ($data['birth_date'] ?? ''));
        $this->assertBirthDate($birthDate);

        $safeContact = trim((string) ($data['safe_contact'] ?? ''));
        $safeNotes = $this->nullable($data['safe_contact_notes'] ?? null);

        if ($safeContact === 'restricted' && $safeNotes === null) {
            throw new HttpException(422, 'Indique las indicaciones de contacto seguro.');
        }

        if ($safeContact !== 'restricted') {
            $safeNotes = null;
        }

        return [
            'first_names' => trim((string) ($data['first_names'] ?? '')),
            'paternal_surname' => trim((string) ($data['paternal_surname'] ?? '')),
            'maternal_surname' => $this->nullable($data['maternal_surname'] ?? null),
            'rut' => \App\Support\ChileanRutValidator::format($normalized) ?? $normalized,
            'rut_normalized' => $normalized,
            'birth_date' => $birthDate,
            'phone' => $this->nullable($data['phone'] ?? null),
            'email' => $this->nullable($data['email'] ?? null),
            'address' => $this->nullable($data['address'] ?? null),
            'sector_id' => $this->nullableInt($data['sector_id'] ?? null),
            'nationality' => $this->nullable($data['nationality'] ?? null),
            'occupation' => $this->nullable($data['occupation'] ?? null),
            'education_level_id' => $this->nullableInt($data['education_level_id'] ?? null),
            'safe_contact' => $safeContact !== '' ? $safeContact : null,
            'safe_contact_notes' => $safeNotes,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function auditSnapshot(array $row): array
    {
        return AuditService::pick($row, [
            'id', 'first_names', 'paternal_surname', 'maternal_surname', 'rut', 'birth_date',
            'phone', 'email', 'address', 'sector_id', 'nationality', 'occupation',
            'education_level_id', 'safe_contact', 'deleted_at',
        ]);
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
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
