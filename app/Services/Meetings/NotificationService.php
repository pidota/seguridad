<?php

declare(strict_types=1);

namespace App\Services\Meetings;

use App\Repositories\Meetings\NotificationRepository;

final class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notifications = new NotificationRepository()
    ) {
    }

    public function notifySignaturePending(int $userId, int $meetingId, string $meetingNumber): void
    {
        $this->notifications->create([
            'user_id' => $userId,
            'type' => 'meeting_signature_pending',
            'title' => 'Firma pendiente de reunión',
            'message' => 'La reunión ' . $meetingNumber . ' requiere su firma simple interna.',
            'related_type' => 'meeting',
            'related_id' => $meetingId,
        ]);
    }

    public function notifyMeetingCompleted(int $userId, int $meetingId, string $meetingNumber): void
    {
        $this->notifications->create([
            'user_id' => $userId,
            'type' => 'meeting_signed_complete',
            'title' => 'Reunión firmada',
            'message' => 'La reunión ' . $meetingNumber . ' fue firmada por todos los asistentes requeridos.',
            'related_type' => 'meeting',
            'related_id' => $meetingId,
        ]);
    }

    public function notifyCorrectionRequested(int $userId, int $meetingId, string $meetingNumber, string $reason): void
    {
        $excerpt = mb_substr(trim($reason), 0, 120);
        $this->notifications->create([
            'user_id' => $userId,
            'type' => 'meeting_correction_requested',
            'title' => 'Corrección solicitada en reunión',
            'message' => 'Se solicitó corrección en ' . $meetingNumber . ': ' . $excerpt,
            'related_type' => 'meeting',
            'related_id' => $meetingId,
        ]);
    }

    public function notifyMeetingCancelled(int $userId, int $meetingId, string $meetingNumber): void
    {
        $this->notifications->create([
            'user_id' => $userId,
            'type' => 'meeting_cancelled',
            'title' => 'Reunión anulada',
            'message' => 'La reunión ' . $meetingNumber . ' fue anulada.',
            'related_type' => 'meeting',
            'related_id' => $meetingId,
        ]);
    }

    public function notifyMeetingReopened(int $userId, int $meetingId, string $meetingNumber): void
    {
        $this->notifications->create([
            'user_id' => $userId,
            'type' => 'meeting_reopened',
            'title' => 'Reunión reabierta',
            'message' => 'La reunión ' . $meetingNumber . ' volvió a borrador para corrección.',
            'related_type' => 'meeting',
            'related_id' => $meetingId,
        ]);
    }

    public function unreadCount(int $userId): int
    {
        return $this->notifications->unreadCount($userId);
    }
}
