<?php

declare(strict_types=1);

namespace App\Services\Meetings;

use App\Repositories\Meetings\MeetingAgreementRepository;
use App\Repositories\Meetings\MeetingParticipantRepository;
use App\Repositories\Meetings\MeetingRepository;
use App\Repositories\Meetings\MeetingSignatureRepository;
use App\Repositories\Meetings\MeetingTopicRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;

final class MeetingService
{
    public function __construct(
        private readonly MeetingRepository $meetings = new MeetingRepository(),
        private readonly MeetingParticipantRepository $participants = new MeetingParticipantRepository(),
        private readonly MeetingTopicRepository $topics = new MeetingTopicRepository(),
        private readonly MeetingAgreementRepository $agreements = new MeetingAgreementRepository(),
        private readonly MeetingNumberService $numbers = new MeetingNumberService(),
        private readonly MeetingAccessPolicy $access = new MeetingAccessPolicy(),
        private readonly MeetingAuditService $audit = new MeetingAuditService(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly MeetingSignatureRepository $meetingSignatures = new MeetingSignatureRepository(),
        private readonly NotificationService $notifications = new NotificationService()
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function search(array $filters, int $page, int $perPage = 15): array
    {
        $scoped = $this->scopedFilters($filters);
        $result = $this->meetings->paginate($scoped, $page, $perPage);
        $pages = max(1, (int) ceil($result['total'] / $perPage));

        return [
            'data' => array_map([$this, 'presentListRow'], $result['data']),
            'total' => $result['total'],
            'page' => min($page, $pages),
            'pages' => $pages,
        ];
    }

    public function find(int $id): array
    {
        $row = $this->meetings->findById($id);
        if ($row === null) {
            throw new HttpException(404, 'La reunión no existe.');
        }

        return $this->present($row);
    }

    public function findDetailed(int $id): array
    {
        $meeting = $this->find($id);
        $meeting['participants'] = $this->presentParticipants($this->participants->forMeeting($id));
        $meeting['topics'] = $this->topics->forMeeting($id);
        $meeting['agreements'] = $this->presentAgreements($this->agreements->forMeeting($id));

        return $meeting;
    }

    public function createDraft(string $sourceModule, array $data): int
    {
        $this->access->assertCanCreate($sourceModule);
        $payload = $this->meetingPayload($data);
        $participantItems = $this->participantItems($data, null);
        $topicItems = $this->topicItems($data);
        $agreementItems = $this->agreementItems($data);

        $createdBy = Auth::id();
        if ($createdBy === null) {
            throw new HttpException(401, 'Debe iniciar sesión.');
        }

        $pdo = Database::connection();
        $started = $pdo->inTransaction();
        if (!$started) {
            $pdo->beginTransaction();
        }

        try {
            $id = $this->meetings->create([
                'meeting_number' => $this->numbers->next(),
                'source_module' => $sourceModule,
                'source_record_id' => $this->nullableInt($data['source_record_id'] ?? null),
                'meeting_date' => $payload['meeting_date'],
                'meeting_time' => $payload['meeting_time'],
                'meeting_place' => $payload['meeting_place'],
                'additional_notes' => $payload['additional_notes'],
                'next_meeting_required' => $payload['next_meeting_required'],
                'next_meeting_date' => $payload['next_meeting_date'],
                'next_meeting_time' => $payload['next_meeting_time'],
                'next_meeting_notes' => $payload['next_meeting_notes'],
                'status' => MeetingStatus::DRAFT,
                'created_by' => $createdBy,
            ]);

            $participantItems = $this->ensureCreatorParticipant($participantItems, $createdBy, $data);
            $this->participants->sync($id, $participantItems);
            $this->topics->sync($id, $topicItems);
            $this->agreements->sync($id, $agreementItems);

            $created = $this->findDetailed($id);
            $this->audit->created($id, $this->auditSnapshot($created));

            if (!$started) {
                $pdo->commit();
            }

            return $id;
        } catch (\Throwable $e) {
            if (!$started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function updateDraft(int $id, array $data): void
    {
        $before = $this->findDetailed($id);
        $this->access->assertCanEdit($before);

        $payload = $this->meetingPayload($data);
        $participantItems = $this->participantItems($data, $id);
        $topicItems = $this->topicItems($data);
        $agreementItems = $this->agreementItems($data);

        $pdo = Database::connection();
        $started = $pdo->inTransaction();
        if (!$started) {
            $pdo->beginTransaction();
        }

        try {
            $this->meetings->update($id, $payload);

            $participantItems = $this->ensureCreatorParticipant(
                $participantItems,
                (int) ($before['created_by'] ?? 0),
                $data
            );
            $this->participants->sync($id, $participantItems);
            $this->topics->sync($id, $topicItems);
            $this->agreements->sync($id, $agreementItems);

            $after = $this->findDetailed($id);
            $this->audit->draftSaved($id, $this->auditSnapshot($after));
            $this->audit->updated($id, $this->auditSnapshot($before), $this->auditSnapshot($after));

            if (!$started) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchActiveUsers(string $term, int $limit = 15): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email
             FROM users u
             WHERE u.is_active = 1
               AND (u.name LIKE :q OR u.email LIKE :q2)
             ORDER BY u.name ASC
             LIMIT ' . max(1, min($limit, 30))
        );
        $like = '%' . $term . '%';
        $stmt->execute(['q' => $like, 'q2' => $like]);

        return array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'label' => trim((string) ($row['name'] ?? '')),
            ];
        }, $stmt->fetchAll() ?: []);
    }

    public function assertCanView(array $meeting): void
    {
        $this->access->assertCanView($meeting);
    }

    public function auditView(array $meeting): void
    {
        $this->access->assertCanView($meeting);
        $this->audit->viewed(
            (int) ($meeting['id'] ?? 0),
            (string) ($meeting['meeting_number'] ?? '')
        );
    }

    public function assertCanEdit(array $meeting): void
    {
        $this->access->assertCanEdit($meeting);
    }

    public function cancel(int $id, string $reason): void
    {
        $before = $this->findDetailed($id);
        $this->access->assertCanCancel($before);

        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new HttpException(422, 'Indique un motivo de anulación de al menos 10 caracteres.');
        }

        $userId = Auth::id();
        if ($userId === null) {
            throw new HttpException(401, 'Debe iniciar sesión.');
        }

        $pdo = Database::connection();
        $started = $pdo->inTransaction();
        if (!$started) {
            $pdo->beginTransaction();
        }

        try {
            $this->meetingSignatures->invalidateForMeeting($id);
            $this->meetings->cancel($id, [
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancelled_by' => $userId,
                'cancellation_reason' => $reason,
            ]);

            $after = $this->findDetailed($id);
            $this->audit->cancelled(
                $id,
                $this->auditSnapshot($before),
                $this->auditSnapshot(array_merge($after, ['cancellation_reason' => $reason]))
            );
            $this->notifyParticipants($before, 'cancelled');

            if (!$started) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof HttpException) {
                throw $e;
            }

            throw new HttpException(500, 'No fue posible anular la reunión.');
        }
    }

    public function reopen(int $id, string $reason): void
    {
        $before = $this->findDetailed($id);
        $this->access->assertCanReopen($before);

        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new HttpException(422, 'Indique un motivo de reapertura de al menos 10 caracteres.');
        }

        $userId = Auth::id();
        if ($userId === null) {
            throw new HttpException(401, 'Debe iniciar sesión.');
        }

        $pdo = Database::connection();
        $started = $pdo->inTransaction();
        if (!$started) {
            $pdo->beginTransaction();
        }

        try {
            $this->meetingSignatures->invalidateForMeeting($id);
            $this->meetings->reopenToDraft($id, [
                'reopened_at' => date('Y-m-d H:i:s'),
                'reopened_by' => $userId,
                'reopen_reason' => $reason,
            ]);

            $after = $this->findDetailed($id);
            $this->audit->reopened(
                $id,
                $this->auditSnapshot($before),
                $this->auditSnapshot(array_merge($after, ['reopen_reason' => $reason]))
            );
            $this->notifyParticipants($before, 'reopened');

            if (!$started) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof HttpException) {
                throw $e;
            }

            throw new HttpException(500, 'No fue posible reabrir la reunión.');
        }
    }

    public function canCancel(array $meeting): bool
    {
        return $this->access->canCancel($meeting);
    }

    public function canReopen(array $meeting): bool
    {
        return $this->access->canReopen($meeting);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function present(array $row): array
    {
        $row['status_label'] = MeetingStatus::label((string) ($row['status'] ?? ''));
        $row['source_module_label'] = MeetingSourceModule::label((string) ($row['source_module'] ?? ''));
        $row['next_meeting_required'] = !empty($row['next_meeting_required']);
        $row['can_edit'] = $this->access->canEdit($row);
        $row['can_cancel'] = $this->access->canCancel($row);
        $row['can_reopen'] = $this->access->canReopen($row);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function presentListRow(array $row): array
    {
        $presented = $this->present($row);
        $meetingId = (int) ($row['id'] ?? 0);
        $participantRows = $this->participants->forMeeting($meetingId);
        $names = [];
        foreach ($participantRows as $participant) {
            if (($participant['participant_type'] ?? '') === 'internal') {
                $names[] = (string) ($participant['user_name'] ?? '');
            } else {
                $names[] = (string) ($participant['external_name'] ?? '');
            }
        }
        $presented['participants_label'] = $names !== [] ? implode(', ', array_filter($names)) : '—';

        return $presented;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function presentParticipants(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['display_name'] = ($row['participant_type'] ?? '') === 'internal'
                ? (string) ($row['user_name'] ?? '')
                : (string) ($row['external_name'] ?? '');
            $row['signature_required'] = !empty($row['signature_required']);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function presentAgreements(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['responsible_label'] = trim((string) ($row['responsible_user_name'] ?? '')) !== ''
                ? (string) $row['responsible_user_name']
                : (trim((string) ($row['responsible_text'] ?? '')) !== '' ? (string) $row['responsible_text'] : '—');
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function scopedFilters(array $filters): array
    {
        if (!hasPermission('meetings.view_all')) {
            $userId = Auth::id();
            if ($userId !== null) {
                $filters['accessible_user_id'] = $userId;
            }

            unset($filters['created_by']);
        }

        return $filters;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function meetingPayload(array $data): array
    {
        $meetingDate = trim((string) ($data['meeting_date'] ?? ''));
        $meetingTime = trim((string) ($data['meeting_time'] ?? ''));
        $meetingPlace = trim((string) ($data['meeting_place'] ?? ''));

        if ($meetingDate === '' || $meetingTime === '' || $meetingPlace === '') {
            throw new HttpException(422, 'Complete fecha, hora y lugar de la reunión.');
        }

        $nextRequired = trim((string) ($data['next_meeting_required'] ?? 'no')) === 'yes';
        $nextDate = $this->nullableString($data['next_meeting_date'] ?? null);
        $nextTime = $this->nullableTime($data['next_meeting_time'] ?? null);
        $nextNotes = $this->nullableString($data['next_meeting_notes'] ?? null);

        if ($nextRequired && $nextDate === null) {
            throw new HttpException(422, 'Indique la fecha de la próxima reunión o seguimiento.');
        }

        if (!$nextRequired) {
            $nextDate = null;
            $nextTime = null;
            $nextNotes = null;
        }

        return [
            'meeting_date' => $meetingDate,
            'meeting_time' => $this->normalizeTime($meetingTime),
            'meeting_place' => $meetingPlace,
            'additional_notes' => $this->nullableString($data['additional_notes'] ?? null),
            'next_meeting_required' => $nextRequired ? 1 : 0,
            'next_meeting_date' => $nextDate,
            'next_meeting_time' => $nextTime,
            'next_meeting_notes' => $nextNotes,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    private function participantItems(array $data, ?int $meetingId): array
    {
        $raw = $data['participants'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        $order = 0;
        foreach (array_values($raw) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = trim((string) ($row['participant_type'] ?? 'internal'));
            if (!in_array($type, ['internal', 'external'], true)) {
                continue;
            }

            $order++;
            $signatureRequired = !empty($row['signature_required']) || (string) ($row['signature_required'] ?? '') === '1';

            if ($type === 'internal') {
                $userId = (int) ($row['user_id'] ?? 0);
                if ($userId < 1 || $this->users->findById($userId) === null) {
                    throw new HttpException(422, 'Seleccione un participante interno válido.');
                }

                $items[] = [
                    'participant_type' => 'internal',
                    'user_id' => $userId,
                    'external_name' => null,
                    'external_position' => null,
                    'external_organization' => null,
                    'external_email' => null,
                    'signature_required' => $signatureRequired ? 1 : 0,
                    'sort_order' => $order,
                ];

                continue;
            }

            $name = trim((string) ($row['external_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $items[] = [
                'participant_type' => 'external',
                'user_id' => null,
                'external_name' => $name,
                'external_position' => $this->nullableString($row['external_position'] ?? null),
                'external_organization' => $this->nullableString($row['external_organization'] ?? null),
                'external_email' => $this->nullableString($row['external_email'] ?? null),
                'signature_required' => 0,
                'sort_order' => $order,
            ];
        }

        if ($items === []) {
            throw new HttpException(422, 'Agregue al menos un participante.');
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    private function ensureCreatorParticipant(array $items, int $creatorId, array $data): array
    {
        $includeCreator = !isset($data['include_creator']) || (string) $data['include_creator'] === '1';
        if (!$includeCreator || $creatorId < 1) {
            return $items;
        }

        foreach ($items as $item) {
            if (($item['participant_type'] ?? '') === 'internal' && (int) ($item['user_id'] ?? 0) === $creatorId) {
                return $items;
            }
        }

        $items[] = [
            'participant_type' => 'internal',
            'user_id' => $creatorId,
            'external_name' => null,
            'external_position' => null,
            'external_organization' => null,
            'external_email' => null,
            'signature_required' => 1,
            'sort_order' => count($items) + 1,
        ];

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{position: int, description: string}>
     */
    private function topicItems(array $data): array
    {
        $raw = $data['topics'] ?? [];
        if (!is_array($raw)) {
            throw new HttpException(422, 'Agregue al menos un tema abordado.');
        }

        $items = [];
        $position = 0;
        foreach (array_values($raw) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $description = trim((string) ($row['description'] ?? ''));
            if ($description === '') {
                continue;
            }

            $position++;
            $items[] = ['position' => $position, 'description' => $description];
        }

        if ($items === []) {
            throw new HttpException(422, 'Agregue al menos un tema abordado.');
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    private function agreementItems(array $data): array
    {
        $raw = $data['agreements'] ?? [];
        if (!is_array($raw)) {
            throw new HttpException(422, 'Agregue al menos un acuerdo.');
        }

        $items = [];
        $position = 0;
        foreach (array_values($raw) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $description = trim((string) ($row['description'] ?? ''));
            if ($description === '') {
                continue;
            }

            $position++;
            $responsibleUserId = (int) ($row['responsible_user_id'] ?? 0);
            $items[] = [
                'position' => $position,
                'description' => $description,
                'responsible_user_id' => $responsibleUserId > 0 ? $responsibleUserId : null,
                'responsible_text' => $this->nullableString($row['responsible_text'] ?? null),
                'due_date' => $this->nullableString($row['due_date'] ?? null),
            ];
        }

        if ($items === []) {
            throw new HttpException(422, 'Agregue al menos un acuerdo.');
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function auditSnapshot(array $row): array
    {
        return $this->audit->sanitize(AuditService::pick($row, [
            'id', 'meeting_number', 'source_module', 'meeting_date', 'meeting_time',
            'meeting_place', 'status', 'next_meeting_required', 'next_meeting_date',
            'created_by', 'content_hash', 'content_version',
            'additional_notes', 'next_meeting_notes', 'cancellation_reason', 'reopen_reason',
        ]));
    }

    private function normalizeTime(string $time): string
    {
        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            return $time . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time) === 1) {
            return $time;
        }

        throw new HttpException(422, 'La hora de la reunión no es válida.');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return $this->normalizeTime($value);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    /**
     * @param array<string, mixed> $meeting
     */
    private function notifyParticipants(array $meeting, string $type): void
    {
        $meetingId = (int) ($meeting['id'] ?? 0);
        $meetingNumber = (string) ($meeting['meeting_number'] ?? '');
        $creatorId = (int) ($meeting['created_by'] ?? 0);
        $notified = [];

        foreach ($meeting['participants'] ?? [] as $participant) {
            if (($participant['participant_type'] ?? '') !== 'internal') {
                continue;
            }

            $userId = (int) ($participant['user_id'] ?? 0);
            if ($userId < 1 || isset($notified[$userId])) {
                continue;
            }

            $notified[$userId] = true;

            if ($type === 'cancelled') {
                $this->notifications->notifyMeetingCancelled($userId, $meetingId, $meetingNumber);
            } else {
                $this->notifications->notifyMeetingReopened($userId, $meetingId, $meetingNumber);
            }
        }

        if ($creatorId > 0 && !isset($notified[$creatorId])) {
            if ($type === 'cancelled') {
                $this->notifications->notifyMeetingCancelled($creatorId, $meetingId, $meetingNumber);
            } else {
                $this->notifications->notifyMeetingReopened($creatorId, $meetingId, $meetingNumber);
            }
        }
    }
}
