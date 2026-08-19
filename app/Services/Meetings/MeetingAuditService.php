<?php

declare(strict_types=1);

namespace App\Services\Meetings;

use App\Services\AuditService;

/**
 * Auditoría del módulo de reuniones con snapshots sin contenido extenso del acta.
 */
final class MeetingAuditService
{
    private const TEXT_EXCERPT = 120;

    /** @var list<string> */
    private const TEXT_FIELDS = [
        'additional_notes',
        'next_meeting_notes',
        'meeting_place',
        'cancellation_reason',
        'reopen_reason',
        'rejection_reason',
        'reason',
        'description',
    ];

    public function __construct(private readonly AuditService $audit = new AuditService())
    {
    }

    public function viewed(int|string $meetingId, string $meetingNumber): void
    {
        $this->audit->log(
            AuditService::ACTION_VIEW_MEETING,
            AuditService::MODULE_MEETINGS,
            AuditService::RESOURCE_MEETING,
            $meetingId,
            null,
            [
                'meeting_id' => $meetingId,
                'meeting_number' => $meetingNumber,
            ]
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function created(int $id, array $snapshot): void
    {
        $this->audit->created(
            AuditService::MODULE_MEETINGS,
            AuditService::RESOURCE_MEETING,
            $id,
            $this->sanitize($snapshot)
        );
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    public function updated(int $id, array $old, array $new): void
    {
        $this->audit->updated(
            AuditService::MODULE_MEETINGS,
            AuditService::RESOURCE_MEETING,
            $id,
            $this->sanitize($old),
            $this->sanitize($new)
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function draftSaved(int $id, array $snapshot): void
    {
        $this->audit->log(
            AuditService::ACTION_DRAFT_SAVED,
            AuditService::MODULE_MEETINGS,
            AuditService::RESOURCE_MEETING,
            $id,
            null,
            $this->sanitize($snapshot)
        );
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    public function finalized(int $id, array $old, array $new): void
    {
        $this->audit->log(
            AuditService::ACTION_FINALIZED,
            AuditService::MODULE_MEETINGS,
            AuditService::RESOURCE_MEETING,
            $id,
            $this->sanitize($old),
            $this->sanitize($new)
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function signed(int $id, array $snapshot): void
    {
        $this->audit->log(
            AuditService::ACTION_SIGNED,
            AuditService::MODULE_MEETINGS,
            AuditService::RESOURCE_MEETING,
            $id,
            null,
            $this->sanitize($snapshot)
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function correctionRequested(int $id, array $snapshot): void
    {
        $this->audit->log(
            AuditService::ACTION_CORRECTION_REQUESTED,
            AuditService::MODULE_MEETINGS,
            AuditService::RESOURCE_MEETING,
            $id,
            null,
            $this->sanitize($snapshot)
        );
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    public function cancelled(int $id, array $old, array $new): void
    {
        $this->audit->log(
            AuditService::ACTION_CANCELLED,
            AuditService::MODULE_MEETINGS,
            AuditService::RESOURCE_MEETING,
            $id,
            $this->sanitize($old),
            $this->sanitize($new)
        );
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    public function reopened(int $id, array $old, array $new): void
    {
        $this->audit->log(
            AuditService::ACTION_REOPENED,
            AuditService::MODULE_MEETINGS,
            AuditService::RESOURCE_MEETING,
            $id,
            $this->sanitize($old),
            $this->sanitize($new)
        );
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function sanitize(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            if (in_array((string) $key, self::TEXT_FIELDS, true) && is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    $sanitized[$key . '_excerpt'] = mb_substr($trimmed, 0, self::TEXT_EXCERPT);
                }

                continue;
            }

            if (is_array($value)) {
                if (array_is_list($value)) {
                    $items = [];
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $items[] = $this->sanitize($item);
                        }
                    }
                    $sanitized[$key] = $items;

                    continue;
                }

                $sanitized[$key] = $this->sanitize($value);

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
