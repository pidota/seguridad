<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Models\Cctv\LogEntryHistory as HistoryAction;
use App\Repositories\Cctv\LogEntryHistoryRepository;

final class LogEntryHistoryService
{
    public function __construct(
        private readonly LogEntryHistoryRepository $history = new LogEntryHistoryRepository()
    ) {
    }

    public function record(
        int $entryId,
        string $actionType,
        string $description,
        ?int $userId = null,
        ?int $shiftId = null,
        ?array $metadata = null
    ): int {
        return $this->history->create([
            'log_entry_id' => $entryId,
            'action_type' => $actionType,
            'description' => trim($description),
            'user_id' => $userId,
            'shift_id' => $shiftId,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForEntry(int $entryId): array
    {
        return array_map(
            [$this, 'present'],
            $this->history->listForEntry($entryId)
        );
    }

    public function lastActionForEntry(int $entryId): ?array
    {
        $row = $this->history->findLastForEntry($entryId);

        return $row ? $this->present($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $createdAt = trim((string) ($row['created_at'] ?? ''));
        $timestamp = $createdAt !== '' ? strtotime($createdAt) : false;

        $row['time_label'] = $timestamp !== false ? date('H:i', $timestamp) : '—';
        $row['datetime_label'] = $timestamp !== false ? date('d-m-Y H:i', $timestamp) : '—';
        $row['action_label'] = $this->actionLabel((string) ($row['action_type'] ?? ''));
        $row['user_label'] = trim((string) ($row['user_name'] ?? '')) ?: '—';

        return $row;
    }

    public function actionLabel(string $actionType): string
    {
        return match ($actionType) {
            HistoryAction::ACTION_CREATED => 'Registro creado',
            HistoryAction::ACTION_UPDATED => 'Actualización',
            HistoryAction::ACTION_COORDINATION => 'Coordinación',
            HistoryAction::ACTION_HANDOVER => 'Cambio de turno',
            HistoryAction::ACTION_HANDOVER_ACCEPTED => 'Continuidad aceptada',
            HistoryAction::ACTION_HANDOVER_NOT_CONTINUED => 'No continuidad',
            HistoryAction::ACTION_STATUS_CHANGE => 'Cambio de estado',
            HistoryAction::ACTION_COMPLETED => 'Finalizado',
            default => strtoupper(str_replace('_', ' ', $actionType)),
        };
    }

    public function elapsedSince(?array $action): ?string
    {
        if ($action === null) {
            return null;
        }

        $createdAt = trim((string) ($action['created_at'] ?? ''));
        $timestamp = $createdAt !== '' ? strtotime($createdAt) : false;

        if ($timestamp === false) {
            return null;
        }

        $diff = max(0, time() - $timestamp);

        if ($diff < 60) {
            return 'Hace un momento';
        }

        if ($diff < 3600) {
            return 'Hace ' . intdiv($diff, 60) . ' minutos';
        }

        if ($diff < 86400) {
            return 'Hace ' . intdiv($diff, 3600) . ' horas';
        }

        return 'Hace ' . intdiv($diff, 86400) . ' días';
    }
}
