<?php

declare(strict_types=1);

namespace App\Services\Meetings;

use Core\Auth;
use Core\Exceptions\HttpException;

final class MeetingAccessPolicy
{
    public function __construct(
        private readonly \App\Repositories\Meetings\MeetingParticipantRepository $participants = new \App\Repositories\Meetings\MeetingParticipantRepository()
    ) {
    }

    /**
     * @param array<string, mixed> $meeting
     */
    public function canView(array $meeting): bool
    {
        if (hasPermission('meetings.view_all')) {
            return true;
        }

        $userId = Auth::id();
        if ($userId === null) {
            return false;
        }

        if ((int) ($meeting['created_by'] ?? 0) === $userId) {
            return true;
        }

        return $this->participants->isParticipant((int) ($meeting['id'] ?? 0), $userId);
    }

    /**
     * @param array<string, mixed> $meeting
     */
    public function assertCanView(array $meeting): void
    {
        if (!hasPermission('meetings.view')) {
            throw new HttpException(403, 'No tiene permiso para consultar reuniones.');
        }

        if (!$this->canView($meeting)) {
            throw new HttpException(403, 'No tiene permiso para consultar esta reunión.');
        }
    }

    /**
     * @param array<string, mixed> $meeting
     */
    public function canEdit(array $meeting): bool
    {
        if (!MeetingStatus::isEditable((string) ($meeting['status'] ?? ''))) {
            return false;
        }

        if (!hasPermission('meetings.edit')) {
            return false;
        }

        if (hasPermission('meetings.view_all')) {
            return true;
        }

        $userId = Auth::id();

        return $userId !== null && (int) ($meeting['created_by'] ?? 0) === $userId;
    }

    /**
     * @param array<string, mixed> $meeting
     */
    public function assertCanEdit(array $meeting): void
    {
        $this->assertCanView($meeting);

        if (!$this->canEdit($meeting)) {
            throw new HttpException(403, 'No tiene permiso para modificar esta reunión.');
        }
    }

    public function assertCanCreate(string $sourceModule): void
    {
        if (!hasPermission('meetings.create')) {
            throw new HttpException(403, 'No tiene permiso para crear reuniones.');
        }

        if ($sourceModule === MeetingSourceModule::SENDA && !hasPermission('senda.meetings.create')) {
            throw new HttpException(403, 'No tiene permiso para crear reuniones desde SENDA.');
        }
    }

    /**
     * @param array<string, mixed> $meeting
     */
    public function canCancel(array $meeting): bool
    {
        if (!MeetingStatus::isCancellable((string) ($meeting['status'] ?? ''))) {
            return false;
        }

        if (!hasPermission('meetings.cancel')) {
            return false;
        }

        return $this->canManageLifecycle($meeting);
    }

    /**
     * @param array<string, mixed> $meeting
     */
    public function canReopen(array $meeting): bool
    {
        if (!MeetingStatus::isReopenable((string) ($meeting['status'] ?? ''))) {
            return false;
        }

        if (!hasPermission('meetings.reopen')) {
            return false;
        }

        return $this->canManageLifecycle($meeting);
    }

    /**
     * @param array<string, mixed> $meeting
     */
    public function assertCanCancel(array $meeting): void
    {
        $this->assertCanView($meeting);

        if (!$this->canCancel($meeting)) {
            throw new HttpException(403, 'No tiene permiso para anular esta reunión.');
        }
    }

    /**
     * @param array<string, mixed> $meeting
     */
    public function assertCanReopen(array $meeting): void
    {
        $this->assertCanView($meeting);

        if (!$this->canReopen($meeting)) {
            throw new HttpException(403, 'No tiene permiso para reabrir esta reunión.');
        }
    }

    /**
     * @param array<string, mixed> $meeting
     */
    private function canManageLifecycle(array $meeting): bool
    {
        if (hasPermission('meetings.view_all')) {
            return true;
        }

        $userId = Auth::id();

        return $userId !== null && (int) ($meeting['created_by'] ?? 0) === $userId;
    }
}
