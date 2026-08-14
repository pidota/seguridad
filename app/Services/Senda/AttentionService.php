<?php

declare(strict_types=1);

namespace App\Services\Senda;

use App\Repositories\Senda\AttentionRepository;
use App\Repositories\Senda\AttentionSequenceRepository;
use App\Repositories\Senda\PersonRepository;
use App\Services\AuditService;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;

final class AttentionService
{
    public function __construct(
        private readonly AttentionRepository $attentions = new AttentionRepository(),
        private readonly AttentionSequenceRepository $sequences = new AttentionSequenceRepository(),
        private readonly PersonRepository $people = new PersonRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function search(array $filters, int $page, int $perPage = 15): array
    {
        $page = max(1, $page);
        $result = $this->attentions->paginate($filters, $page, $perPage);
        $pages = max(1, (int) ceil($result['total'] / $perPage));

        return [
            'data' => array_map([$this, 'present'], $result['data']),
            'total' => $result['total'],
            'page' => min($page, $pages),
            'pages' => $pages,
        ];
    }

    public function staffOptions(): array
    {
        return $this->attentions->staffOptions();
    }

    public function all(): array
    {
        return array_map([$this, 'present'], $this->attentions->all());
    }

    public function find(int $id): array
    {
        $record = $this->attentions->findById($id);

        if ($record === null) {
            throw new HttpException(404, 'La atención no existe.');
        }

        return $this->present($record);
    }

    public function create(array $data): int
    {
        $payload = $this->payload($data, null);

        $attempts = 0;
        $last = null;

        while ($attempts < 3) {
            try {
                $id = Database::transaction(function () use ($payload): int {
                    $year = (int) substr($payload['attention_date'], 0, 4);
                    $sequence = $this->sequences->next($year);
                    $payload['attention_number'] = sprintf('SENDA-%d-%06d', $year, $sequence);

                    return $this->attentions->create($payload);
                });

                $created = $this->attentions->findById($id);
                $this->audit->created(
                    AuditService::MODULE_SENDA,
                    AuditService::RESOURCE_ATTENTION,
                    $id,
                    $this->auditSnapshot($created ? $this->present($created) : $payload)
                );

                return $id;
            } catch (\PDOException $e) {
                $last = $e;
                $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
                if ($sqlState !== '23000') {
                    throw $e;
                }
                $attempts++;
            }
        }

        throw $last ?? new HttpException(500, 'No fue posible asignar el correlativo de atención.');
    }

    public function update(int $id, array $data): void
    {
        $current = $this->find($id);
        $data['entry_type'] = $current['entry_type'];
        $data['senda_person_id'] = $current['senda_person_id'];
        $payload = $this->payload($data, $id);

        unset($payload['attention_number'], $payload['senda_person_id'], $payload['entry_type'], $payload['created_by']);

        $this->attentions->update($id, $payload);
        $updated = $this->attentions->findById($id);
        $this->audit->updated(
            AuditService::MODULE_SENDA,
            AuditService::RESOURCE_ATTENTION,
            $id,
            $this->auditSnapshot($current),
            $this->auditSnapshot($updated ? $this->present($updated) : $payload)
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        $row['person_full_name'] = PersonService::fullName($row);
        $row['person_age'] = PersonService::age(isset($row['birth_date']) ? (string) $row['birth_date'] : null);
        $row['person_rut'] = (string) ($row['rut'] ?? '');
        $row['has_ficha'] = (int) ($row['ficha_count'] ?? 0) > 0;
        $row['ficha_id'] = isset($row['ficha_id']) && $row['ficha_id'] !== null ? (int) $row['ficha_id'] : null;
        $row['has_followup'] = (int) ($row['followup_count'] ?? 0) > 0;
        $row['followup_count'] = (int) ($row['followup_count'] ?? 0);
        $type = (string) ($row['referral_institution_type'] ?? '');
        $row['referral_institution_type_label'] = $type !== '' ? ReferralInstitutionType::label($type) : '';
        $row['attention_time_short'] = $this->formatTime($row['attention_time'] ?? null);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function auditSnapshot(array $row): array
    {
        $snapshot = AuditService::pick($row, [
            'id',
            'attention_number',
            'senda_person_id',
            'person_full_name',
            'attention_date',
            'entry_type',
            'referral_institution_type',
            'referral_institution_name',
            'referral_person',
            'referral_phone',
            'referral_email',
            'referral_notes',
            'summary',
        ]);
        $time = trim((string) ($row['attention_time_short'] ?? $row['attention_time'] ?? ''));
        $snapshot['attention_time'] = $time === '' ? null : substr($time, 0, 5);

        return $snapshot;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(array $data, ?int $exceptId): array
    {
        $entryType = $exceptId === null
            ? EntryTypeContext::resolveForStore($data['entry_type'] ?? null)
            : (string) ($data['entry_type'] ?? '');

        if (!EntryType::isValid((string) $entryType)) {
            throw new HttpException(422, 'Debe seleccionar un tipo de ingreso antes de registrar la atención.');
        }

        $personId = $this->requirePersonId($data['senda_person_id'] ?? $data['person_id'] ?? PersonContext::id());
        $isReferral = $entryType === EntryType::DERIVACION;

        return [
            'attention_number' => '',
            'senda_person_id' => $personId,
            'attention_date' => trim((string) ($data['attention_date'] ?? '')),
            'attention_time' => $this->normalizeTime((string) ($data['attention_time'] ?? '')),
            'entry_type' => $entryType,
            'referral_institution_type' => $isReferral ? $this->nullable($data['referral_institution_type'] ?? null) : null,
            'referral_institution_name' => $isReferral ? $this->nullable($data['referral_institution_name'] ?? null) : null,
            'referral_person' => $isReferral ? $this->nullable($data['referral_person'] ?? null) : null,
            'referral_phone' => $isReferral ? $this->nullable($data['referral_phone'] ?? null) : null,
            'referral_email' => $isReferral ? $this->nullable($data['referral_email'] ?? null) : null,
            'referral_notes' => $isReferral ? $this->nullable($data['referral_notes'] ?? null) : null,
            'summary' => $this->nullable($data['summary'] ?? null) ?? '',
            'created_by' => Auth::id(),
        ];
    }

    private function requirePersonId(mixed $value): int
    {
        $personId = $this->nullableInt($value);

        if ($personId === null) {
            throw new HttpException(422, 'Debe seleccionar una persona registrada antes de crear la atención.');
        }

        if ($this->people->findById($personId) === null) {
            throw new HttpException(422, 'La persona seleccionada no existe.');
        }

        return $personId;
    }

    private function normalizeTime(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1) {
            return $value . ':00';
        }

        return $value;
    }

    private function formatTime(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return substr($value, 0, 5);
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

        return (int) $value;
    }
}
