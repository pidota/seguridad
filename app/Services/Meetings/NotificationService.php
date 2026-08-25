<?php

declare(strict_types=1);

namespace App\Services\Meetings;

/**
 * Puente de compatibilidad hacia el servicio transversal de notificaciones.
 */
final class NotificationService
{
    public function __construct(
        private readonly \App\Services\NotificationService $notifications = new \App\Services\NotificationService()
    ) {
    }

    public function notifySignaturePending(int $userId, int $meetingId, string $meetingNumber): void
    {
        $this->notifications->notifySignaturePending($userId, $meetingId, $meetingNumber);
    }

    public function notifyMeetingCompleted(int $userId, int $meetingId, string $meetingNumber): void
    {
        $this->notifications->notifyMeetingCompleted($userId, $meetingId, $meetingNumber);
    }

    public function notifyCorrectionRequested(int $userId, int $meetingId, string $meetingNumber, string $reason): void
    {
        $this->notifications->notifyCorrectionRequested($userId, $meetingId, $meetingNumber, $reason);
    }

    public function notifyMeetingCancelled(int $userId, int $meetingId, string $meetingNumber): void
    {
        $this->notifications->notifyMeetingCancelled($userId, $meetingId, $meetingNumber);
    }

    public function notifyMeetingReopened(int $userId, int $meetingId, string $meetingNumber): void
    {
        $this->notifications->notifyMeetingReopened($userId, $meetingId, $meetingNumber);
    }

    public function unreadCount(int $userId): int
    {
        return $this->notifications->unreadCount($userId);
    }
}
