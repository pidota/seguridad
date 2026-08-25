<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Models\Cctv\LogEntry;
use App\Models\Cctv\LogEntryHistory as HistoryAction;
use App\Models\Cctv\LogHandover;
use App\Models\Cctv\LogType;
use App\Repositories\Cctv\LogContactRepository;
use App\Repositories\Cctv\LogEntryRepository;
use App\Repositories\Cctv\LogHandoverRepository;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;

final class LogHandoverService
{
    public function __construct(
        private readonly LogHandoverRepository $handovers = new LogHandoverRepository(),
        private readonly LogEntryRepository $entries = new LogEntryRepository(),
        private readonly LogContactRepository $contacts = new LogContactRepository(),
        private readonly LogEntryHistoryService $history = new LogEntryHistoryService(),
        private readonly LogEntryService $logEntries = new LogEntryService(),
        private readonly CctvAuditService $cctvAudit = new CctvAuditService()
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingEntriesForClosing(int $operatorId): array
    {
        return array_map(
            fn (array $row): array => $this->logEntries->present($row),
            $this->entries->listPendingForOperator($operatorId)
        );
    }

    /**
     * @param array<int|string, mixed> $handoverNotesByEntry
     */
    public function createOnShiftClose(
        int $shiftId,
        int $operatorId,
        array $handoverNotesByEntry,
        string $handedOverAt
    ): int {
        $pending = $this->entries->listPendingForOperator($operatorId);
        $created = 0;

        foreach ($pending as $row) {
            $entryId = (int) ($row['id'] ?? 0);
            if ($entryId < 1) {
                continue;
            }

            $notes = trim((string) ($handoverNotesByEntry[$entryId] ?? $handoverNotesByEntry[(string) $entryId] ?? ''));

            $handoverId = $this->handovers->create([
                'log_entry_id' => $entryId,
                'from_shift_id' => $shiftId,
                'to_shift_id' => null,
                'from_operator_id' => $operatorId,
                'to_operator_id' => null,
                'handover_status' => LogHandover::STATUS_PENDING,
                'handover_notes' => $notes !== '' ? $notes : null,
                'handed_over_at' => $handedOverAt,
            ]);

            $this->history->record(
                $entryId,
                HistoryAction::ACTION_HANDOVER,
                'Pendiente entregado al siguiente operador.' . ($notes !== '' ? ' Nota: ' . $notes : ''),
                $operatorId,
                $shiftId,
                ['handover_id' => $handoverId]
            );

            $created++;
        }

        return $created;
    }

    public function assignPendingToNewShift(int $shiftId, int $operatorId): int
    {
        return $this->handovers->assignUnassignedPending($shiftId, $operatorId);
    }

    public function countUnreviewedForShift(int $shiftId, ?int $operatorId = null): int
    {
        $operatorId ??= Auth::id();
        if ($operatorId === null || $operatorId < 1 || $shiftId < 1) {
            return 0;
        }

        if (!hasPermission('cctv.handovers.view')) {
            return 0;
        }

        return $this->handovers->countUnreviewedForShift($shiftId, $operatorId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPendingForShift(int $shiftId, ?int $operatorId = null): array
    {
        $operatorId ??= Auth::id();
        if ($operatorId === null || $shiftId < 1) {
            return [];
        }

        return array_map(
            [$this, 'presentListItem'],
            $this->handovers->listPendingForShift($shiftId, $operatorId)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function reviewDetail(int $handoverId, ?int $viewerId = null): array
    {
        $viewerId ??= Auth::id();
        $row = $this->handovers->findById($handoverId);

        if ($row === null) {
            throw new HttpException(404, 'El traspaso no existe.');
        }

        $this->assertCanReview($row, $viewerId);

        $entryId = (int) ($row['log_entry_id'] ?? 0);
        $entry = $this->logEntries->find($entryId);
        $history = $this->history->listForEntry($entryId);
        $lastAction = $this->history->lastActionForEntry($entryId);
        $lastCoordination = $this->lastCoordination($entryId);
        $handoverHistory = array_map([$this, 'present'], $this->handovers->listForEntry($entryId));

        $presented = $this->present($row);
        $presented['entry'] = $entry;
        $presented['entry_reference'] = $this->entryReference($entry);
        $presented['operational_history'] = $history;
        $presented['handover_history'] = $handoverHistory;
        $presented['last_action'] = $lastAction;
        $presented['last_coordination'] = $lastCoordination;
        $presented['elapsed_since_last_action'] = $this->history->elapsedSince($lastAction);
        $presented['involved_shifts'] = $this->buildInvolvedShifts($entryId, $handoverHistory);

        return $presented;
    }

    public function accept(int $handoverId, ?int $operatorId = null, ?int $shiftId = null): void
    {
        if (!hasPermission('cctv.handovers.accept')) {
            throw new HttpException(403, 'No puede aceptar continuidad de procedimientos.');
        }

        $operatorId ??= Auth::id();
        $shiftId ??= $this->requireOpenShiftId($operatorId);

        $row = $this->handovers->findById($handoverId);
        if ($row === null) {
            throw new HttpException(404, 'El traspaso no existe.');
        }

        $this->assertCanDecide($row, $operatorId);

        if (!LogHandover::isPending((string) ($row['handover_status'] ?? ''))) {
            throw new HttpException(422, 'Este traspaso ya fue revisado.');
        }

        $entryId = (int) ($row['log_entry_id'] ?? 0);
        $user = Auth::user();
        $operatorName = trim((string) ($user['name'] ?? '')) ?: 'Operador';

        Database::transaction(function () use ($handoverId, $entryId, $operatorId, $shiftId, $operatorName): void {
            $this->handovers->updateReview($handoverId, [
                'handover_status' => LogHandover::STATUS_ACCEPTED,
                'decision' => LogHandover::DECISION_CONTINUE,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'reviewed_by' => $operatorId,
            ]);

            $this->entries->assignToShift($entryId, $shiftId, $operatorId);

            $this->history->record(
                $entryId,
                HistoryAction::ACTION_HANDOVER_ACCEPTED,
                'Operador ' . $operatorName . ' revisó los antecedentes y acepta continuar el procedimiento.',
                $operatorId,
                $shiftId,
                ['handover_id' => $handoverId]
            );
        });

        $entry = $this->logEntries->find($entryId);
        $this->cctvAudit->logEntryUpdated($entryId, $entry, $entry, false);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function decline(int $handoverId, array $data, ?int $operatorId = null, ?int $shiftId = null): void
    {
        if (!hasPermission('cctv.handovers.decline')) {
            throw new HttpException(403, 'No puede declinar continuidad de procedimientos.');
        }

        $operatorId ??= Auth::id();
        $shiftId ??= $this->requireOpenShiftId($operatorId);

        $row = $this->handovers->findById($handoverId);
        if ($row === null) {
            throw new HttpException(404, 'El traspaso no existe.');
        }

        $this->assertCanDecide($row, $operatorId);

        if (!LogHandover::isPending((string) ($row['handover_status'] ?? ''))) {
            throw new HttpException(422, 'Este traspaso ya fue revisado.');
        }

        $reason = trim((string) ($data['decision_reason'] ?? ''));
        $details = trim((string) ($data['decision_details'] ?? ''));
        $reasonOther = trim((string) ($data['decision_reason_other'] ?? ''));

        if ($reason === '' || !HandoverCatalog::isValidDeclineReason($reason)) {
            throw new HttpException(422, 'Seleccione un motivo de no continuidad válido.');
        }

        if ($reason === 'other' && $reasonOther === '') {
            throw new HttpException(422, 'Especifique el motivo de no continuidad.');
        }

        if ($details === '') {
            throw new HttpException(422, 'La justificación es obligatoria.');
        }

        $reasonLabel = $reason === 'other' ? $reasonOther : HandoverCatalog::declineReasonLabel($reason);
        $institution = trim((string) ($data['delegated_institution'] ?? ''));
        $approxTime = trim((string) ($data['approx_completion_time'] ?? ''));

        if ($reason === 'derived_no_cctv_followup' && $institution === '') {
            throw new HttpException(422, 'Indique la institución que quedó a cargo del procedimiento.');
        }

        $entryId = (int) ($row['log_entry_id'] ?? 0);
        $entryBefore = $this->logEntries->find($entryId);
        $logTypeSlug = (string) ($entryBefore['log_type_slug'] ?? '');
        $finalStatus = $logTypeSlug === LogType::SLUG_TECHNICAL
            ? TechnicalEntryCatalog::STATUS_OPERATIONAL_AGAIN
            : LogEntry::STATUS_FINISHED;

        $user = Auth::user();
        $operatorName = trim((string) ($user['name'] ?? '')) ?: 'Operador';

        Database::transaction(function () use (
            $handoverId,
            $entryId,
            $operatorId,
            $shiftId,
            $operatorName,
            $reason,
            $reasonLabel,
            $details,
            $institution,
            $approxTime,
            $finalStatus,
            $entryBefore
        ): void {
            $this->handovers->updateReview($handoverId, [
                'handover_status' => LogHandover::STATUS_NOT_CONTINUED,
                'decision' => LogHandover::DECISION_STOP,
                'decision_reason' => $reason === 'other' ? $reasonLabel : $reason,
                'decision_details' => $details,
                'approx_completion_time' => $approxTime !== '' ? $this->normalizeTime($approxTime) : null,
                'delegated_institution' => $institution !== '' ? $institution : null,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'reviewed_by' => $operatorId,
            ]);

            $this->entries->assignToShift($entryId, $shiftId, $operatorId);
            $this->entries->updateStatus($entryId, $finalStatus);

            $this->history->record(
                $entryId,
                HistoryAction::ACTION_HANDOVER_NOT_CONTINUED,
                'Operador ' . $operatorName . ' determinó no continuar. Motivo: ' . $reasonLabel . '.',
                $operatorId,
                $shiftId,
                [
                    'handover_id' => $handoverId,
                    'justification' => $details,
                ]
            );

            $this->history->record(
                $entryId,
                HistoryAction::ACTION_COMPLETED,
                'Procedimiento finalizado tras revisión de traspaso.',
                $operatorId,
                $shiftId
            );
        });

        $entryAfter = $this->logEntries->find($entryId);
        $this->cctvAudit->logEntryUpdated($entryId, $entryBefore, $entryAfter, false);
    }

    public function assertEntryEditable(int $entryId): void
    {
        $operatorId = Auth::id();
        if ($operatorId === null) {
            throw new HttpException(403, 'Debe iniciar sesión.');
        }

        $pending = $this->handovers->findOpenPendingForOperator($entryId, $operatorId);
        if ($pending !== null) {
            throw new HttpException(
                422,
                'Debe revisar y decidir la continuidad de este procedimiento traspasado antes de modificarlo.'
            );
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function assertCanReview(array $row, int $viewerId): void
    {
        if (!hasPermission('cctv.handovers.view')) {
            throw new HttpException(403, 'No puede consultar traspasos de procedimientos.');
        }

        if (hasPermission('cctv.shifts.view_all')) {
            return;
        }

        if ((int) ($row['to_operator_id'] ?? 0) !== $viewerId) {
            throw new HttpException(403, 'Este traspaso no está asignado a su turno.');
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function assertCanDecide(array $row, int $viewerId): void
    {
        if (!hasPermission('cctv.handovers.view')) {
            throw new HttpException(403, 'No puede consultar traspasos de procedimientos.');
        }

        if ((int) ($row['to_operator_id'] ?? 0) !== $viewerId) {
            throw new HttpException(403, 'Este traspaso no está asignado a su turno.');
        }
    }

    private function requireOpenShiftId(int $operatorId): int
    {
        $shift = (new \App\Repositories\Cctv\ShiftRepository())->findOpenByOperator($operatorId);
        if ($shift === null) {
            throw new HttpException(422, 'Debe tener un turno abierto para revisar traspasos.');
        }

        return (int) ($shift['id'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lastCoordination(int $entryId): ?array
    {
        $contacts = $this->contacts->listByEntry($entryId);
        if ($contacts === []) {
            return null;
        }

        $last = $contacts[count($contacts) - 1];
        $contactType = trim((string) ($last['contact_type'] ?? ''));
        $result = trim((string) ($last['notes'] ?? ''));

        return [
            'institution' => LogContactCatalog::label($contactType),
            'time_label' => $this->formatTime($last['contact_time'] ?? null),
            'result' => $result !== '' ? $result : '—',
        ];
    }

    /**
     * @param list<array<string, mixed>> $handovers
     * @return list<array<string, mixed>>
     */
    private function buildInvolvedShifts(int $entryId, array $handovers): array
    {
        $entry = $this->entries->findById($entryId);
        $items = [];

        if ($entry !== null) {
            $items[] = [
                'label' => 'Turno de origen',
                'operator' => trim((string) ($entry['created_by_name'] ?? '')) ?: '—',
                'description' => 'Creó el procedimiento.',
            ];
        }

        foreach ($handovers as $handover) {
            $fromLabel = trim((string) ($handover['from_operator_label'] ?? ''));
            if ($fromLabel !== '' && $fromLabel !== '—') {
                $items[] = [
                    'label' => $this->formatShiftRange($handover, 'from'),
                    'operator' => $fromLabel,
                    'description' => 'Entregó procedimiento en desarrollo.',
                ];
            }

            if (($handover['handover_status'] ?? '') === LogHandover::STATUS_ACCEPTED) {
                $items[] = [
                    'label' => $this->formatShiftRange($handover, 'to'),
                    'operator' => (string) ($handover['to_operator_label'] ?? '—'),
                    'description' => 'Aceptó continuidad.',
                ];
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function formatShiftRange(array $row, string $prefix): string
    {
        $started = trim((string) ($row[$prefix . '_shift_started_at'] ?? ''));
        $ended = trim((string) ($row[$prefix . '_shift_ended_at'] ?? ''));

        if ($started === '') {
            return '—';
        }

        $startTs = strtotime($started);
        $date = $startTs !== false ? date('d-m-Y', $startTs) : '—';
        $startTime = $startTs !== false ? date('H:i', $startTs) : '—';
        $endTime = '—';

        if ($ended !== '') {
            $endTs = strtotime($ended);
            $endTime = $endTs !== false ? date('H:i', $endTs) : '—';
        }

        return $date . ' · ' . $startTime . ' - ' . $endTime;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function closureSummaryForEntry(int $entryId): ?array
    {
        if ($entryId < 1) {
            return null;
        }

        $lastReviewed = null;
        foreach ($this->handovers->listForEntry($entryId) as $row) {
            if (trim((string) ($row['reviewed_at'] ?? '')) === '') {
                continue;
            }

            $lastReviewed = $this->present($row);
        }

        if ($lastReviewed === null) {
            return null;
        }

        $status = (string) ($lastReviewed['handover_status'] ?? '');
        $reviewer = (string) ($lastReviewed['reviewed_by_label'] ?? '—');
        $reviewedAt = (string) ($lastReviewed['reviewed_at_formatted'] ?? '—');

        $summary = [
            'handover_status' => $status,
            'handover_status_label' => (string) ($lastReviewed['handover_status_label'] ?? '—'),
            'reviewed_by_label' => $reviewer,
            'reviewed_at_formatted' => $reviewedAt,
            'decision_reason_label' => (string) ($lastReviewed['decision_reason_label'] ?? '—'),
            'decision_details' => trim((string) ($lastReviewed['decision_details'] ?? '')),
            'delegated_institution_label' => (string) ($lastReviewed['delegated_institution_label'] ?? '—'),
            'approx_completion_time' => $this->formatTime($lastReviewed['approx_completion_time'] ?? null),
        ];

        if ($status === LogHandover::STATUS_NOT_CONTINUED) {
            $summary['finalized_by_label'] = $reviewer;
            $summary['finalized_at_formatted'] = $reviewedAt;
        }

        if ($status === LogHandover::STATUS_ACCEPTED) {
            $summary['continuity_by_label'] = $reviewer;
            $summary['continuity_at_formatted'] = $reviewedAt;
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $row['handover_status_label'] = HandoverCatalog::statusLabel((string) ($row['handover_status'] ?? ''));
        $row['decision_reason_label'] = HandoverCatalog::declineReasonLabel((string) ($row['decision_reason'] ?? ''));
        $row['delegated_institution_label'] = HandoverCatalog::institutionLabel($row['delegated_institution'] ?? null);
        $row['from_operator_label'] = trim((string) ($row['from_operator_name'] ?? '')) ?: '—';
        $row['to_operator_label'] = trim((string) ($row['to_operator_name'] ?? '')) ?: '—';
        $row['reviewed_by_label'] = trim((string) ($row['reviewed_by_name'] ?? '')) ?: '—';
        $row['handed_over_at_formatted'] = $this->formatDateTime($row['handed_over_at'] ?? null);
        $row['reviewed_at_formatted'] = $this->formatDateTime($row['reviewed_at'] ?? null);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function presentListItem(array $row): array
    {
        $presented = $this->present($row);
        $entryId = (int) ($row['log_entry_id'] ?? 0);
        $entryRecord = $this->entries->findById($entryId);
        $entry = $entryRecord ? $this->logEntries->present($entryRecord) : [];
        $lastAction = $this->history->lastActionForEntry($entryId);
        $lastCoordination = $this->lastCoordination($entryId);

        $presented['entry_reference'] = $this->entryReference($entry);
        $presented['type_label'] = trim((string) ($entry['log_type_name'] ?? '')) ?: '—';
        $presented['incident_label'] = (string) ($entry['incident_type_display'] ?? $entry['technical_issue_display'] ?? '—');
        $presented['sector_label'] = trim((string) ($entry['sector_name'] ?? '')) ?: '—';
        $presented['camera_label'] = trim((string) ($entry['camera_name'] ?? '')) ?: '—';
        $presented['event_time_label'] = (string) ($entry['event_time_formatted'] ?? '—');
        $presented['event_date_label'] = (string) ($entry['event_date_formatted'] ?? '—');
        $presented['status_label'] = (string) ($entry['status_label'] ?? '—');
        $presented['last_action'] = $lastAction;
        $presented['last_coordination'] = $lastCoordination;

        return $presented;
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function entryReference(array $entry): string
    {
        $id = (int) ($entry['id'] ?? 0);
        $occurred = trim((string) ($entry['occurred_at'] ?? $entry['event_date'] ?? ''));
        $year = $occurred !== '' ? date('Y', strtotime($occurred) ?: time()) : date('Y');

        return sprintf('CCTV-%s-%06d', $year, $id);
    }

    private function formatDateTime(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '—';
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('d-m-Y H:i', $timestamp) : $value;
    }

    private function formatTime(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '—';
        }

        if (preg_match('/^\d{2}:\d{2}/', $value) === 1) {
            return substr($value, 0, 5);
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('H:i', $timestamp) : $value;
    }

    private function normalizeTime(string $value): string
    {
        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        throw new HttpException(422, 'Indique una hora aproximada válida (HH:MM).');
    }
}
