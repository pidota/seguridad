<?php

declare(strict_types=1);

namespace App\Services\Meetings;

use App\Repositories\AuditRepository;
use App\Repositories\Meetings\MeetingSignatureRepository;
use App\Services\AuditService;

final class MeetingHistoryService
{
    public function __construct(
        private readonly AuditRepository $audits = new AuditRepository(),
        private readonly MeetingSignatureRepository $signatures = new MeetingSignatureRepository()
    ) {
    }

    /**
     * @param array<string, mixed> $meeting
     * @return list<array<string, mixed>>
     */
    public function timeline(array $meeting, string $order = 'desc'): array
    {
        $events = $this->collectEvents($meeting);

        usort(
            $events,
            static fn (array $a, array $b): int => $order === 'asc'
                ? self::sortKey($a) <=> self::sortKey($b)
                : self::sortKey($b) <=> self::sortKey($a)
        );

        return array_values(array_map([$this, 'presentEvent'], $events));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function auditEntries(int $meetingId, string $order = 'desc'): array
    {
        $rows = $this->audits->forResource(
            AuditService::MODULE_MEETINGS,
            AuditService::RESOURCE_MEETING,
            $meetingId
        );

        if ($order === 'asc') {
            $rows = array_reverse($rows);
        }

        return array_map([$this, 'presentAuditEntry'], $rows);
    }

    /**
     * @param array<string, mixed> $meeting
     * @return list<array<string, mixed>>
     */
    private function collectEvents(array $meeting): array
    {
        $events = [];
        $meetingId = (int) ($meeting['id'] ?? 0);

        if (!empty($meeting['created_at'])) {
            $events[] = [
                'datetime' => (string) $meeting['created_at'],
                'icon' => 'bi-file-earmark-plus',
                'title' => 'Registro creado',
                'lines' => array_values(array_filter([
                    !empty($meeting['created_by_name']) ? 'Responsable: ' . (string) $meeting['created_by_name'] : null,
                    'Estado inicial: Borrador',
                ])),
            ];
        }

        if (!empty($meeting['finalized_at'])) {
            $events[] = [
                'datetime' => (string) $meeting['finalized_at'],
                'icon' => 'bi-lock',
                'title' => 'Finalizada y enviada a firma',
                'lines' => ['Se bloqueó la edición y se generó el hash de contenido.'],
            ];
        }

        foreach ($this->signatures->forMeeting($meetingId) as $signature) {
            if (($signature['status'] ?? '') === 'signed' && !empty($signature['signed_at'])) {
                $events[] = [
                    'datetime' => (string) $signature['signed_at'],
                    'icon' => 'bi-pen',
                    'title' => 'Firma registrada',
                    'lines' => ['Participante: ' . (string) ($signature['user_name'] ?? '—')],
                ];
            }

            if (($signature['status'] ?? '') === 'rejected' && !empty($signature['rejected_at'])) {
                $events[] = [
                    'datetime' => (string) $signature['rejected_at'],
                    'icon' => 'bi-arrow-return-left',
                    'title' => 'Corrección solicitada',
                    'lines' => array_values(array_filter([
                        'Participante: ' . (string) ($signature['user_name'] ?? '—'),
                        !empty($signature['rejection_reason'])
                            ? mb_substr((string) $signature['rejection_reason'], 0, 120)
                            : null,
                    ])),
                ];
            }
        }

        if (!empty($meeting['completed_at'])) {
            $events[] = [
                'datetime' => (string) $meeting['completed_at'],
                'icon' => 'bi-check2-circle',
                'title' => 'Firmada completamente',
                'lines' => ['Todos los participantes requeridos firmaron el registro.'],
            ];
        }

        if (!empty($meeting['reopened_at'])) {
            $events[] = [
                'datetime' => (string) $meeting['reopened_at'],
                'icon' => 'bi-arrow-counterclockwise',
                'title' => 'Reabierta para corrección',
                'lines' => array_values(array_filter([
                    !empty($meeting['reopen_reason']) ? (string) $meeting['reopen_reason'] : null,
                ])),
            ];
        }

        if (!empty($meeting['cancelled_at'])) {
            $events[] = [
                'datetime' => (string) $meeting['cancelled_at'],
                'icon' => 'bi-x-circle',
                'title' => 'Reunión anulada',
                'lines' => array_values(array_filter([
                    !empty($meeting['cancellation_reason']) ? (string) $meeting['cancellation_reason'] : null,
                ])),
            ];
        }

        if ($events === [] && $meetingId > 0) {
            $events[] = [
                'datetime' => date('Y-m-d H:i:s'),
                'icon' => 'bi-info-circle',
                'title' => 'Sin eventos adicionales',
                'lines' => ['El historial operativo se irá completando con las acciones del registro.'],
            ];
        }

        return $events;
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function presentEvent(array $event): array
    {
        $timestamp = strtotime((string) ($event['datetime'] ?? 'now'));

        return [
            'icon' => (string) ($event['icon'] ?? 'bi-circle'),
            'title' => (string) ($event['title'] ?? ''),
            'lines' => is_array($event['lines'] ?? null) ? $event['lines'] : [],
            'datetime_label' => $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '—',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentAuditEntry(array $row): array
    {
        $timestamp = strtotime((string) ($row['created_at'] ?? 'now'));

        return [
            'action_label' => AuditService::actionLabel((string) ($row['action'] ?? '')),
            'user_name' => (string) ($row['user_name'] ?? 'Sistema'),
            'datetime_label' => $timestamp !== false ? date('d/m/Y H:i:s', $timestamp) : '—',
            'ip_address' => (string) ($row['ip_address'] ?? '—'),
        ];
    }

    /**
     * @param array<string, mixed> $event
     */
    private static function sortKey(array $event): int
    {
        $timestamp = strtotime((string) ($event['datetime'] ?? ''));

        return $timestamp !== false ? $timestamp : 0;
    }
}
