<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\Senda\FollowUpRepository;
use App\Repositories\WomenOffice\StatsRepository;
use App\Services\Senda\FollowUpStatus;
use Core\Auth;

final class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notifications = new NotificationRepository(),
        private readonly PermissionRepository $permissions = new PermissionRepository(),
        private readonly FollowUpRepository $sendaFollowUps = new FollowUpRepository(),
        private readonly StatsRepository $womenStats = new StatsRepository()
    ) {
    }

    public function unreadCount(?int $userId = null): int
    {
        $userId ??= Auth::id();
        if ($userId === null || $userId < 1) {
            return 0;
        }

        $this->syncOperationalAlerts($userId);

        return $this->notifications->unreadCount($userId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentForNavbar(int $limit = 8, ?int $userId = null): array
    {
        $userId ??= Auth::id();
        if ($userId === null || $userId < 1) {
            return [];
        }

        $this->syncOperationalAlerts($userId);

        return array_map(
            [$this, 'present'],
            $this->notifications->listForUser($userId, $limit)
        );
    }

    /**
     * @return array{data: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function paginateForUser(int $page, int $perPage = 20, ?int $userId = null): array
    {
        $userId ??= Auth::id();
        if ($userId === null || $userId < 1) {
            return ['data' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        }

        $this->syncOperationalAlerts($userId);

        $page = max(1, $page);
        $result = $this->notifications->paginateForUser($userId, $page, $perPage);
        $pages = max(1, (int) ceil($result['total'] / $perPage));

        return [
            'data' => array_map([$this, 'present'], $result['data']),
            'total' => $result['total'],
            'page' => min($page, $pages),
            'pages' => $pages,
        ];
    }

    public function markRead(int $id, ?int $userId = null): bool
    {
        $userId ??= Auth::id();
        if ($userId === null || $userId < 1) {
            return false;
        }

        return $this->notifications->markRead($id, $userId);
    }

    public function markAllRead(?int $userId = null): int
    {
        $userId ??= Auth::id();
        if ($userId === null || $userId < 1) {
            return 0;
        }

        return $this->notifications->markAllRead($userId);
    }

    public function notify(int $userId, string $type, string $title, string $message, ?string $relatedType = null, ?int $relatedId = null): void
    {
        if ($userId < 1) {
            return;
        }

        if ($this->notifications->hasUnreadDuplicate($userId, $type, $relatedType, $relatedId)) {
            return;
        }

        $this->notifications->create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);
    }

    public function notifySignaturePending(int $userId, int $meetingId, string $meetingNumber): void
    {
        $this->notify(
            $userId,
            NotificationCatalog::TYPE_MEETING_SIGNATURE_PENDING,
            'Firma pendiente de reunión',
            'La reunión ' . $meetingNumber . ' requiere su firma simple interna.',
            'meeting',
            $meetingId
        );
    }

    public function notifyMeetingCompleted(int $userId, int $meetingId, string $meetingNumber): void
    {
        $this->notify(
            $userId,
            NotificationCatalog::TYPE_MEETING_SIGNED_COMPLETE,
            'Reunión firmada',
            'La reunión ' . $meetingNumber . ' fue firmada por todos los asistentes requeridos.',
            'meeting',
            $meetingId
        );
    }

    public function notifyCorrectionRequested(int $userId, int $meetingId, string $meetingNumber, string $reason): void
    {
        $this->notify(
            $userId,
            NotificationCatalog::TYPE_MEETING_CORRECTION,
            'Corrección solicitada en reunión',
            'Se solicitó corrección en ' . $meetingNumber . ': ' . mb_substr(trim($reason), 0, 120),
            'meeting',
            $meetingId
        );
    }

    public function notifyMeetingCancelled(int $userId, int $meetingId, string $meetingNumber): void
    {
        $this->notify(
            $userId,
            NotificationCatalog::TYPE_MEETING_CANCELLED,
            'Reunión anulada',
            'La reunión ' . $meetingNumber . ' fue anulada.',
            'meeting',
            $meetingId
        );
    }

    public function notifyMeetingReopened(int $userId, int $meetingId, string $meetingNumber): void
    {
        $this->notify(
            $userId,
            NotificationCatalog::TYPE_MEETING_REOPENED,
            'Reunión reabierta',
            'La reunión ' . $meetingNumber . ' volvió a borrador para corrección.',
            'meeting',
            $meetingId
        );
    }

    public function notifyAttendanceResponse(int $userId, int $meetingId, string $meetingNumber, string $participantName, string $status): void
    {
        $confirmed = $status === 'confirmed';
        $this->notify(
            $userId,
            $confirmed
                ? NotificationCatalog::TYPE_MEETING_ATTENDANCE_CONFIRMED
                : NotificationCatalog::TYPE_MEETING_ATTENDANCE_DECLINED,
            $confirmed ? 'Asistencia confirmada' : 'Asistencia declinada',
            trim($participantName) . ($confirmed
                ? ' confirmó asistencia a la reunión ' . $meetingNumber . '.'
                : ' declinó asistencia a la reunión ' . $meetingNumber . '.'),
            'meeting',
            $meetingId
        );
    }

    public function notifySendaReferralCreated(int $userId, int $referralId, string $personLabel): void
    {
        $this->notify(
            $userId,
            NotificationCatalog::TYPE_SENDA_REFERRAL_CREATED,
            'Nueva ficha de referencia SENDA',
            'Se registró una ficha de referencia para ' . $personLabel . '.',
            'senda_referral',
            $referralId
        );
    }

    public function notifySendaFollowUpDue(int $userId, int $followUpId, string $personLabel, string $dueDate, bool $overdue = false): void
    {
        $this->notify(
            $userId,
            $overdue
                ? NotificationCatalog::TYPE_SENDA_FOLLOWUP_OVERDUE
                : NotificationCatalog::TYPE_SENDA_FOLLOWUP_DUE,
            $overdue ? 'Seguimiento SENDA atrasado' : 'Seguimiento SENDA pendiente',
            ($overdue ? 'Seguimiento atrasado' : 'Seguimiento pendiente') . ' de ' . $personLabel . ' (próxima fecha: ' . $dueDate . ').',
            'senda_follow_up',
            $followUpId
        );
    }

    public function notifyWomenReferralCreated(int $userId, int $caseId, string $caseNumber): void
    {
        $this->notify(
            $userId,
            NotificationCatalog::TYPE_WOMEN_REFERRAL_CREATED,
            'Derivación registrada',
            'Se registró una derivación en el caso ' . $caseNumber . '.',
            'women_case',
            $caseId
        );
    }

    public function notifyWomenFollowUpDue(int $userId, int $caseId, string $caseNumber): void
    {
        $this->notify(
            $userId,
            NotificationCatalog::TYPE_WOMEN_FOLLOWUP_DUE,
            'Seguimiento pendiente — Oficina de la Mujer',
            'El caso ' . $caseNumber . ' requiere seguimiento.',
            'women_case',
            $caseId > 0 ? $caseId : null
        );
    }

    public function broadcastToPermission(
        string $permission,
        callable $notify,
        ?int $exceptUserId = null
    ): void {
        foreach ($this->permissions->userIdsWithPermission($permission) as $userId) {
            if ($exceptUserId !== null && $userId === $exceptUserId) {
                continue;
            }

            $notify($userId);
        }
    }

    public function broadcastSendaReferralCreated(int $referralId, string $personLabel, ?int $exceptUserId = null): void
    {
        $this->broadcastToPermission(
            'senda.referrals.view',
            fn (int $userId): mixed => $this->notifySendaReferralCreated($userId, $referralId, $personLabel),
            $exceptUserId
        );
    }

    public function broadcastWomenReferralCreated(int $caseId, string $caseNumber, ?int $exceptUserId = null): void
    {
        $this->broadcastToPermission(
            'women.cases.view',
            fn (int $userId): mixed => $this->notifyWomenReferralCreated($userId, $caseId, $caseNumber),
            $exceptUserId
        );
    }

    /**
     * Genera alertas operativas deduplicadas (seguimientos atrasados, etc.).
     */
    public function syncOperationalAlerts(int $userId): void
    {
        if (hasPermission('senda.followups.view')) {
            $this->syncSendaFollowUpAlerts($userId);
        }

        if (hasPermission('women.cases.view')) {
            $this->syncWomenFollowUpAlerts($userId);
        }
    }

    private function syncSendaFollowUpAlerts(int $userId): void
    {
        $today = FollowUpStatus::today();

        foreach ($this->sendaFollowUps->pendingAlerts(12) as $row) {
            $followUpId = (int) ($row['id'] ?? 0);
            if ($followUpId < 1) {
                continue;
            }

            $nextDate = trim((string) ($row['next_follow_up_date'] ?? ''));
            if ($nextDate === '') {
                continue;
            }

            $person = trim((string) ($row['person_full_name'] ?? $row['first_name'] ?? 'persona atendida'));
            $overdue = $nextDate < $today;
            $dueToday = $nextDate === $today;

            if (!$overdue && !$dueToday) {
                continue;
            }

            $this->notifySendaFollowUpDue(
                $userId,
                $followUpId,
                $person !== '' ? $person : 'persona atendida',
                $this->formatDate($nextDate),
                $overdue
            );
        }
    }

    private function syncWomenFollowUpAlerts(int $userId): void
    {
        $from = date('Y-01-01');
        $to = date('Y-m-d');
        $pending = $this->womenStats->pendingFollowUpCases($from, $to);
        $overdue = $this->womenStats->overdueFollowUpCases($from, $to);

        if ($pending > 0) {
            $this->notifyWomenFollowUpDue($userId, 0, 'casos con seguimiento pendiente');
        }

        if ($overdue > 0) {
            $this->notify(
                $userId,
                NotificationCatalog::TYPE_WOMEN_FOLLOWUP_DUE,
                'Casos con seguimiento atrasado',
                'Hay ' . $overdue . ' caso(s) con seguimiento atrasado en el periodo.',
                'women_case',
                null
            );
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $meta = NotificationCatalog::meta((string) ($row['type'] ?? ''));
        $createdAt = trim((string) ($row['created_at'] ?? ''));
        $timestamp = $createdAt !== '' ? strtotime($createdAt) : false;

        $row['icon'] = $meta['icon'];
        $row['tone'] = $meta['tone'];
        $row['module_label'] = $meta['module'];
        $row['is_unread'] = empty($row['read_at']);
        $row['created_at_formatted'] = $timestamp !== false ? date('d-m-Y H:i', $timestamp) : '—';
        $row['time_ago'] = $timestamp !== false ? $this->timeAgo($timestamp) : '—';
        $row['url'] = NotificationCatalog::url($row);
        $row['excerpt'] = $this->excerpt((string) ($row['message'] ?? ''));

        return $row;
    }

    private function formatDate(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp !== false ? date('d-m-Y', $timestamp) : $value;
    }

    private function excerpt(string $text, int $max = 120): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        if ($text === '') {
            return '—';
        }

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }

    private function timeAgo(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'Hace un momento';
        }

        if ($diff < 3600) {
            return 'Hace ' . intdiv($diff, 60) . ' min';
        }

        if ($diff < 86400) {
            return 'Hace ' . intdiv($diff, 3600) . ' h';
        }

        if ($diff < 604800) {
            return 'Hace ' . intdiv($diff, 86400) . ' d';
        }

        return date('d-m-Y', $timestamp);
    }
}
