<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Models\Cctv\LogType;
use App\Services\AuditService;

/**
 * Auditoría semántica del módulo CCTV con snapshots sin texto sensible completo.
 */
final class CctvAuditService
{
    public const EVENT_SHIFT_OPENED = 'shift_opened';
    public const EVENT_SHIFT_CLOSED = 'shift_closed';
    public const EVENT_SHIFT_UPDATED = 'shift_updated';
    public const EVENT_CLOSED_SHIFT_UPDATED = 'closed_shift_updated';
    public const EVENT_LOG_ENTRY_CREATED = 'log_entry_created';
    public const EVENT_LOG_ENTRY_UPDATED = 'log_entry_updated';
    public const EVENT_LOG_ENTRY_CANCELLED = 'log_entry_cancelled';
    public const EVENT_INCIDENT_CREATED = 'incident_created';
    public const EVENT_INCIDENT_UPDATED = 'incident_updated';
    public const EVENT_COORDINATION_REGISTERED = 'coordination_registered';
    public const EVENT_CAMERA_STATUS_CHANGED = 'camera_status_changed';
    public const EVENT_OFFICE_VISIT_CREATED = 'office_visit_created';
    public const EVENT_RECORDING_REQUEST_CREATED = 'recording_request_created';
    public const EVENT_RECORDING_REQUEST_COMPLAINT = 'recording_request_complaint_registered';
    public const EVENT_RECORDING_REQUEST_STATUS = 'recording_request_status_changed';
    public const EVENT_RECORDING_REQUEST_DELIVERED = 'recording_request_delivered';

    private const TEXT_EXCERPT = 120;

    public function __construct(private readonly AuditService $audit = new AuditService())
    {
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param list<array<string, mixed>>|null $equipmentChecks
     */
    public function shiftOpened(int $id, array $snapshot, ?array $equipmentChecks = null): void
    {
        $payload = $this->withEvent(self::EVENT_SHIFT_OPENED, $this->sanitizeShift($snapshot));

        if ($equipmentChecks !== null) {
            $payload['equipment_checks'] = array_map(
                fn (array $check): array => $this->sanitizeEquipmentCheck($check),
                $equipmentChecks
            );
        }

        $this->audit->created(
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CCTV_SHIFT,
            $id,
            $payload
        );
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     * @param list<array<string, mixed>>|null $closingEquipmentChecks
     */
    public function shiftClosed(int $id, array $old, array $new, ?array $closingEquipmentChecks = null): void
    {
        $oldSanitized = $this->withEvent(self::EVENT_SHIFT_CLOSED, $this->sanitizeShift($old));
        $newSanitized = $this->withEvent(self::EVENT_SHIFT_CLOSED, $this->sanitizeShift($new));

        if ($closingEquipmentChecks !== null) {
            $newSanitized['equipment_checks_closing'] = array_map(
                fn (array $check): array => $this->sanitizeEquipmentCheck($check),
                $closingEquipmentChecks
            );
        }

        if (AuditService::same($oldSanitized, $newSanitized)) {
            return;
        }

        $this->audit->updated(
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CCTV_SHIFT,
            $id,
            $oldSanitized,
            $newSanitized
        );
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    public function shiftUpdated(int $id, array $old, array $new, bool $shiftWasClosed): void
    {
        $event = $shiftWasClosed ? self::EVENT_CLOSED_SHIFT_UPDATED : self::EVENT_SHIFT_UPDATED;
        $oldSanitized = $this->withEvent($event, $this->sanitizeShift($old));
        $newSanitized = $this->withEvent($event, $this->sanitizeShift($new));

        if (AuditService::same($oldSanitized, $newSanitized)) {
            return;
        }

        if ($shiftWasClosed) {
            $this->audit->log(
                AuditService::ACTION_UPDATED_COMPLETED,
                AuditService::MODULE_CCTV,
                AuditService::RESOURCE_CCTV_SHIFT,
                $id,
                $oldSanitized,
                $newSanitized
            );

            return;
        }

        $this->audit->updated(
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CCTV_SHIFT,
            $id,
            $oldSanitized,
            $newSanitized
        );
    }

    /**
     * @param array<string, mixed> $presented
     */
    public function logEntryCreated(int $id, array $presented): void
    {
        $event = $this->isIncident($presented)
            ? self::EVENT_INCIDENT_CREATED
            : self::EVENT_LOG_ENTRY_CREATED;

        $this->audit->created(
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CCTV_LOG_ENTRY,
            $id,
            $this->withEvent($event, $this->sanitizeLogEntry($presented))
        );
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    public function logEntryUpdated(int $id, array $old, array $new, bool $shiftWasClosed): void
    {
        $event = $this->isIncident($new) || $this->isIncident($old)
            ? self::EVENT_INCIDENT_UPDATED
            : self::EVENT_LOG_ENTRY_UPDATED;

        $oldSanitized = $this->withEvent($event, $this->sanitizeLogEntry($old));
        $newSanitized = $this->withEvent($event, $this->sanitizeLogEntry($new));

        if ($shiftWasClosed) {
            $oldSanitized['closed_shift'] = true;
            $newSanitized['closed_shift'] = true;
        }

        if (AuditService::same($oldSanitized, $newSanitized)) {
            return;
        }

        if ($shiftWasClosed) {
            $this->audit->log(
                AuditService::ACTION_UPDATED_COMPLETED,
                AuditService::MODULE_CCTV,
                AuditService::RESOURCE_CCTV_LOG_ENTRY,
                $id,
                $oldSanitized,
                $newSanitized
            );

            return;
        }

        $this->audit->updated(
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CCTV_LOG_ENTRY,
            $id,
            $oldSanitized,
            $newSanitized
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function logEntryCancelled(int $id, array $snapshot, bool $shiftWasClosed): void
    {
        $payload = $this->withEvent(self::EVENT_LOG_ENTRY_CANCELLED, $this->sanitizeLogEntry($snapshot));

        if ($shiftWasClosed) {
            $payload['closed_shift'] = true;
        }

        $this->audit->log(
            AuditService::ACTION_CANCELLED,
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CCTV_LOG_ENTRY,
            $id,
            $payload,
            null
        );
    }

    /**
     * @param list<array<string, mixed>> $contacts
     * @param array<string, mixed> $entryContext
     */
    public function coordinationRegistered(int $logEntryId, array $contacts, array $entryContext): void
    {
        if ($contacts === []) {
            return;
        }

        $this->audit->log(
            AuditService::ACTION_CREATED,
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CCTV_LOG_ENTRY,
            $logEntryId,
            null,
            $this->withEvent(self::EVENT_COORDINATION_REGISTERED, [
                'log_entry_id' => $logEntryId,
                'shift_id' => $entryContext['shift_id'] ?? null,
                'occurred_at_formatted' => $entryContext['occurred_at_formatted'] ?? null,
                'sector_name' => $entryContext['sector_name'] ?? null,
                'camera_name' => $entryContext['camera_name'] ?? null,
                'contacts' => $this->sanitizeContacts($contacts),
                'contacts_count' => count($contacts),
            ])
        );
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    public function cameraStatusChanged(int $cameraId, array $old, array $new, ?int $sourceLogEntryId = null): void
    {
        $oldSanitized = $this->withEvent(self::EVENT_CAMERA_STATUS_CHANGED, $this->sanitizeCamera($old));
        $newSanitized = $this->withEvent(self::EVENT_CAMERA_STATUS_CHANGED, $this->sanitizeCamera($new));

        if ($sourceLogEntryId !== null && $sourceLogEntryId > 0) {
            $oldSanitized['source_log_entry_id'] = $sourceLogEntryId;
            $newSanitized['source_log_entry_id'] = $sourceLogEntryId;
        }

        if (AuditService::same($oldSanitized, $newSanitized)) {
            return;
        }

        $this->audit->updated(
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CCTV_CAMERA,
            $cameraId,
            $oldSanitized,
            $newSanitized
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function sanitizeShift(array $row): array
    {
        $snapshot = AuditService::pick($row, [
            'id',
            'operator_id',
            'operator_name',
            'shift_date',
            'status',
            'status_label',
            'started_at',
            'ended_at',
        ]);

        $openingNotes = trim((string) ($row['opening_notes'] ?? ''));
        if ($openingNotes !== '') {
            $snapshot['opening_notes_excerpt'] = $this->excerpt($openingNotes);
        }

        $closingNotes = trim((string) ($row['closing_notes'] ?? ''));
        if ($closingNotes !== '') {
            $snapshot['closing_notes_excerpt'] = $this->excerpt($closingNotes);
        }

        return $snapshot;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function sanitizeEquipmentCheck(array $row): array
    {
        $snapshot = AuditService::pick($row, [
            'equipment_name',
            'equipment_slug',
            'check_phase',
            'phase_label',
            'status',
            'status_label',
        ]);

        $observations = trim((string) ($row['observations'] ?? ''));
        if ($observations !== '') {
            $snapshot['observations_excerpt'] = $this->excerpt($observations);
        }

        return $snapshot;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function sanitizeLogEntry(array $row): array
    {
        $snapshot = AuditService::pick($row, [
            'id',
            'shift_id',
            'log_type_id',
            'log_type_slug',
            'log_type_name',
            'incident_type_id',
            'incident_type_name',
            'incident_type_other',
            'incident_type_display',
            'technical_issue_type_id',
            'technical_issue_type_name',
            'technical_issue_other',
            'technical_issue_display',
            'equipment_id',
            'equipment_name',
            'target_label',
            'camera_status_applied',
            'camera_status_applied_label',
            'camera_id',
            'camera_name',
            'sector_id',
            'sector_name',
            'occurred_at',
            'occurred_at_formatted',
            'coordination_notified',
            'coordination_notified_label',
            'police_arrived',
            'police_arrived_label',
            'police_arrival_time',
            'police_arrival_time_formatted',
            'contacts_summary',
            'status',
            'status_label',
            'status_tone',
            'created_by',
            'created_by_name',
            'cancelled_by',
            'cancelled_by_name',
            'cancelled_at',
        ]);

        $observations = trim((string) ($row['observations'] ?? ''));
        if ($observations !== '') {
            $snapshot['observations_excerpt'] = $this->excerpt($observations);
        }

        if (isset($row['contacts']) && is_array($row['contacts'])) {
            $snapshot['contacts'] = $this->sanitizeContacts($row['contacts']);
        }

        return $snapshot;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function sanitizeCamera(array $row): array
    {
        return AuditService::pick($row, [
            'id',
            'code',
            'name',
            'sector_id',
            'sector_label',
            'location',
            'camera_type',
            'camera_type_label',
            'status',
            'status_label',
            'active',
            'active_label',
        ]);
    }

    /**
     * @param list<array<string, mixed>> $contacts
     * @return list<array<string, mixed>>
     */
    public function sanitizeContacts(array $contacts): array
    {
        $sanitized = [];

        foreach ($contacts as $contact) {
            if (!is_array($contact)) {
                continue;
            }

            $item = AuditService::pick($contact, [
                'contact_type',
                'contact_type_label',
                'contacted_at',
                'contacted_at_formatted',
                'contact_name',
            ]);

            $notes = trim((string) ($contact['notes'] ?? ''));
            if ($notes !== '') {
                $item['notes_excerpt'] = $this->excerpt($notes);
            }

            $sanitized[] = $item;
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isIncident(array $row): bool
    {
        return (string) ($row['log_type_slug'] ?? '') === LogType::SLUG_INCIDENT;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function withEvent(string $event, array $payload): array
    {
        $payload['cctv_event'] = $event;

        return $payload;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function officeVisitCreated(int $id, array $snapshot): void
    {
        $this->audit->created(
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CCTV_OFFICE_VISIT,
            $id,
            $this->withEvent(self::EVENT_OFFICE_VISIT_CREATED, $snapshot)
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function recordingRequestCreated(int $id, array $snapshot): void
    {
        $this->audit->created(
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CCTV_RECORDING_REQUEST,
            $id,
            $this->withEvent(self::EVENT_RECORDING_REQUEST_CREATED, $snapshot)
        );
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    public function recordingRequestComplaintRegistered(int $id, array $old, array $new): void
    {
        $this->audit->updated(
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CCTV_RECORDING_REQUEST,
            $id,
            $this->withEvent(self::EVENT_RECORDING_REQUEST_COMPLAINT, $old),
            $this->withEvent(self::EVENT_RECORDING_REQUEST_COMPLAINT, $new)
        );
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    public function recordingRequestStatusChanged(int $id, array $old, array $new, string $previous, string $next): void
    {
        $payloadOld = $this->withEvent(self::EVENT_RECORDING_REQUEST_STATUS, array_merge($old, [
            'previous_status' => $previous,
            'new_status' => $next,
        ]));
        $payloadNew = $this->withEvent(self::EVENT_RECORDING_REQUEST_STATUS, array_merge($new, [
            'previous_status' => $previous,
            'new_status' => $next,
        ]));

        $this->audit->updated(
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CCTV_RECORDING_REQUEST,
            $id,
            $payloadOld,
            $payloadNew
        );
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    public function recordingRequestDelivered(int $id, array $old, array $new): void
    {
        $this->audit->updated(
            AuditService::MODULE_CCTV,
            AuditService::RESOURCE_CCTV_RECORDING_REQUEST,
            $id,
            $this->withEvent(self::EVENT_RECORDING_REQUEST_DELIVERED, $old),
            $this->withEvent(self::EVENT_RECORDING_REQUEST_DELIVERED, $new)
        );
    }

    private function excerpt(string $text, int $limit = self::TEXT_EXCERPT): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, max(1, $limit - 1))) . '…';
    }
}
