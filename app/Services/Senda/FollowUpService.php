<?php

declare(strict_types=1);

namespace App\Services\Senda;

use App\Repositories\Senda\AttentionRepository;
use App\Repositories\Senda\FollowUpRepository;
use App\Services\AuditService;
use Core\Auth;
use Core\Exceptions\HttpException;

final class FollowUpService
{
    public function __construct(
        private readonly FollowUpRepository $followUps = new FollowUpRepository(),
        private readonly AttentionRepository $attentions = new AttentionRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(?int $attentionId = null): array
    {
        return array_map([$this, 'present'], $this->followUps->all($attentionId));
    }

    public function search(array $filters, int $page, int $perPage = 15): array
    {
        $page = max(1, $page);
        $result = $this->followUps->paginate($filters, $page, $perPage);
        $pages = max(1, (int) ceil($result['total'] / $perPage));

        return [
            'data' => array_map([$this, 'present'], $result['data']),
            'total' => $result['total'],
            'page' => min($page, $pages),
            'pages' => $pages,
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function staffOptions(): array
    {
        return $this->followUps->staffOptions();
    }

    /**
     * @return list<array{key: string, label: string, count: int, path: string, tone: string}>
     */
    public function dashboardMetrics(?string $today = null): array
    {
        $counts = $this->followUps->scheduleCounts($today);
        $metrics = [];

        foreach (FollowUpStatus::dashboardKeys() as $key) {
            $metrics[] = [
                'key' => $key,
                'label' => FollowUpStatus::label($key),
                'count' => (int) ($counts[$key] ?? 0),
                'path' => '/senda/follow-ups?status=' . $key,
                'tone' => FollowUpStatus::tone($key),
            ];
        }

        return $metrics;
    }

    public function find(int $id): array
    {
        $record = $this->followUps->findById($id);

        if ($record === null) {
            throw new HttpException(404, 'El seguimiento no existe.');
        }

        return $this->present($record);
    }

    /**
     * @param array<string, mixed> $attention
     * @return array<string, mixed>
     */
    public function defaults(array $attention): array
    {
        return [
            'senda_attention_id' => (int) $attention['id'],
            'follow_up_date' => date('Y-m-d'),
            'follow_up_time' => date('H:i'),
            'contact_type' => '',
            'contact_type_other' => '',
            'result' => '',
            'result_other' => '',
            'notes' => '',
            'requires_follow_up' => '',
            'next_follow_up_date' => '',
        ];
    }

    public function create(array $data): int
    {
        $payload = $this->payload($data);
        $id = $this->followUps->create($payload);
        $created = $this->followUps->findById($id);
        $this->audit->created(
            AuditService::MODULE_SENDA,
            AuditService::RESOURCE_FOLLOW_UP,
            $id,
            $this->auditSnapshot($created ? $this->present($created) : $payload)
        );

        return $id;
    }

    public function update(int $id, array $data): void
    {
        $current = $this->find($id);
        $data['senda_attention_id'] = $current['senda_attention_id'];
        $payload = $this->payload($data);
        unset($payload['senda_attention_id'], $payload['created_by']);

        $this->followUps->update($id, $payload);
        $updated = $this->followUps->findById($id);
        $presented = $updated ? $this->present($updated) : $payload;
        $this->auditFollowUpUpdate($id, $current, $presented);
    }

    public function delete(int $id): void
    {
        $current = $this->find($id);
        $this->followUps->delete($id);
        $this->audit->deleted(
            AuditService::MODULE_SENDA,
            AuditService::RESOURCE_FOLLOW_UP,
            $id,
            $this->auditSnapshot($current)
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $row['person_full_name'] = PersonService::fullName($row);
        $row['person_rut'] = (string) ($row['rut'] ?? '');
        $requires = $row['requires_follow_up'] ?? 0;
        $row['requires_follow_up'] = in_array($requires, ['si', 'no'], true)
            ? $requires
            : ((int) $requires === 1 ? 'si' : 'no');
        $row['contact_type_label'] = FollowUpCatalog::optionLabel(
            FollowUpCatalog::contactTypes(),
            $row['contact_type'] ?? null
        );
        $row['result_label'] = FollowUpCatalog::optionLabel(
            FollowUpCatalog::results(),
            $row['result'] ?? null
        );
        $row['requires_follow_up_label'] = FollowUpCatalog::optionLabel(
            FollowUpCatalog::yesNo(),
            $row['requires_follow_up']
        );

        if (($row['contact_type'] ?? '') === 'otro' && trim((string) ($row['contact_type_other'] ?? '')) !== '') {
            $row['contact_type_label'] .= ': ' . trim((string) $row['contact_type_other']);
        }

        if (($row['result'] ?? '') === 'otro' && trim((string) ($row['result_other'] ?? '')) !== '') {
            $row['result_label'] .= ': ' . trim((string) $row['result_other']);
        }

        $time = trim((string) ($row['follow_up_time'] ?? ''));
        $row['follow_up_time'] = $time === '' ? '' : substr($time, 0, 5);
        $row['is_pending'] = FollowUpStatus::isPending($row);
        $row['is_due_today'] = FollowUpStatus::isDueToday($row);
        $row['is_overdue'] = FollowUpStatus::isOverdue($row);
        $row['is_done_today'] = FollowUpStatus::isDoneOn($row);

        return $row;
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $updated
     */
    private function auditFollowUpUpdate(int $id, array $current, array $updated): void
    {
        $old = $this->auditSnapshot($current);
        $new = $this->auditSnapshot($updated);
        $oldDate = $this->auditNextDate($old);
        $newDate = $this->auditNextDate($new);
        $dateChanged = $oldDate !== $newDate
            || (string) ($old['requires_follow_up'] ?? '') !== (string) ($new['requires_follow_up'] ?? '');

        $withoutSchedule = static function (array $snapshot): array {
            unset($snapshot['next_follow_up_date'], $snapshot['requires_follow_up']);

            return $snapshot;
        };

        $otherChanged = !AuditService::same($withoutSchedule($old), $withoutSchedule($new));

        if ($otherChanged) {
            $this->audit->updated(
                AuditService::MODULE_SENDA,
                AuditService::RESOURCE_FOLLOW_UP,
                $id,
                $old,
                $new
            );
        }

        if ($dateChanged) {
            $this->audit->log(
                AuditService::ACTION_NEXT_DATE_CHANGED,
                AuditService::MODULE_SENDA,
                AuditService::RESOURCE_FOLLOW_UP,
                $id,
                [
                    'requires_follow_up' => $old['requires_follow_up'] ?? null,
                    'next_follow_up_date' => $oldDate,
                ],
                [
                    'requires_follow_up' => $new['requires_follow_up'] ?? null,
                    'next_follow_up_date' => $newDate,
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function auditSnapshot(array $row): array
    {
        $snapshot = AuditService::pick($row, [
            'id',
            'senda_attention_id',
            'person_full_name',
            'follow_up_date',
            'contact_type',
            'contact_type_other',
            'result',
            'result_other',
            'notes',
            'requires_follow_up',
        ]);
        $time = trim((string) ($row['follow_up_time'] ?? ''));
        $snapshot['follow_up_time'] = $time === '' ? null : substr($time, 0, 5);
        $snapshot['next_follow_up_date'] = $this->auditNextDate($row);

        return $snapshot;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function auditNextDate(array $row): ?string
    {
        $value = substr(trim((string) ($row['next_follow_up_date'] ?? '')), 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $attentionId = (int) ($data['senda_attention_id'] ?? 0);
        $attention = $this->attentions->findById($attentionId);

        if ($attention === null) {
            throw new HttpException(422, 'El seguimiento debe pertenecer a una atención existente.');
        }

        $contactType = trim((string) ($data['contact_type'] ?? ''));
        $result = trim((string) ($data['result'] ?? ''));
        $requires = trim((string) ($data['requires_follow_up'] ?? ''));

        if (!in_array($contactType, array_column(FollowUpCatalog::contactTypes(), 'value'), true)) {
            throw new HttpException(422, 'Indique el tipo de contacto.');
        }

        if (!in_array($result, array_column(FollowUpCatalog::results(), 'value'), true)) {
            throw new HttpException(422, 'Indique el resultado del seguimiento.');
        }

        if (!in_array($requires, ['si', 'no'], true)) {
            throw new HttpException(422, 'Indique si requiere un nuevo seguimiento.');
        }

        $contactOther = FollowUpCatalog::isOther($contactType)
            ? $this->nullable($data['contact_type_other'] ?? null)
            : null;
        $resultOther = FollowUpCatalog::isOther($result)
            ? $this->nullable($data['result_other'] ?? null)
            : null;

        if (FollowUpCatalog::isOther($contactType) && $contactOther === null) {
            throw new HttpException(422, 'Especifique el tipo de contacto.');
        }

        if (FollowUpCatalog::isOther($result) && $resultOther === null) {
            throw new HttpException(422, 'Especifique el resultado.');
        }

        $nextDate = null;
        if ($requires === 'si') {
            $nextDate = $this->nullable($data['next_follow_up_date'] ?? null);
            if ($nextDate === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextDate)) {
                throw new HttpException(422, 'Indique la fecha del próximo seguimiento.');
            }
        }

        return [
            'senda_attention_id' => $attentionId,
            'follow_up_date' => trim((string) ($data['follow_up_date'] ?? '')),
            'follow_up_time' => $this->nullable($data['follow_up_time'] ?? null),
            'contact_type' => $contactType,
            'contact_type_other' => $contactOther,
            'result' => $result,
            'result_other' => $resultOther,
            'notes' => $this->nullable($data['notes'] ?? null),
            'requires_follow_up' => $requires === 'si' ? 1 : 0,
            'next_follow_up_date' => $nextDate,
            'created_by' => Auth::id(),
        ];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
