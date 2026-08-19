<?php

declare(strict_types=1);

namespace App\Services\WomenOffice;

use App\Repositories\AuditRepository;
use App\Services\AuditService;

final class WomenHistoryService
{
    public function __construct(
        private readonly AuditRepository $audits = new AuditRepository()
    ) {
    }

    /**
     * @param array<string, mixed> $case
     * @return array<string, mixed>
     */
    public function metrics(array $case): array
    {
        $events = $this->collectOperationalEvents($case);
        $lastAction = null;
        $nextFollowUp = null;

        foreach ($events as $event) {
            if (($event['kind'] ?? '') === 'next_follow_up' && $nextFollowUp === null) {
                $nextFollowUp = $event;
            }
        }

        usort($events, static fn (array $a, array $b): int => self::eventSortKey($b) <=> self::eventSortKey($a));

        foreach ($events as $event) {
            if (($event['kind'] ?? '') !== 'next_follow_up') {
                $lastAction = $event;
                break;
            }
        }

        return [
            'actions_count' => count($case['actions'] ?? []),
            'referrals_count' => count($case['referrals'] ?? []),
            'followups_count' => count($case['followups'] ?? []),
            'last_action' => $lastAction !== null ? $this->presentOperationalEvent($lastAction) : null,
            'next_follow_up' => $nextFollowUp !== null ? $this->presentOperationalEvent($nextFollowUp) : null,
        ];
    }

    /**
     * @param array<string, mixed> $case
     * @return list<array<string, mixed>>
     */
    public function timeline(array $case, string $order = 'desc'): array
    {
        $events = $this->collectOperationalEvents($case);

        usort(
            $events,
            static fn (array $a, array $b): int => $order === 'asc'
                ? self::eventSortKey($a) <=> self::eventSortKey($b)
                : self::eventSortKey($b) <=> self::eventSortKey($a)
        );

        return array_values(array_map([$this, 'presentOperationalEvent'], $events));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function auditEntries(int $caseId, string $order = 'desc'): array
    {
        $rows = $this->audits->forResource(
            AuditService::MODULE_WOMEN,
            AuditService::RESOURCE_WOMEN_CASE,
            $caseId
        );

        if ($order === 'asc') {
            $rows = array_reverse($rows);
        }

        return array_map([$this, 'presentAuditEntry'], $rows);
    }

    /**
     * @param array<string, mixed> $case
     * @return list<array<string, mixed>>
     */
    private function collectOperationalEvents(array $case): array
    {
        $events = [];

        if (!empty($case['reported_at'])) {
            $channel = trim((string) ($case['report_channel_name'] ?? ''));
            if (!empty($case['report_channel_other'])) {
                $channel .= $channel !== '' ? ' — ' : '';
                $channel .= (string) $case['report_channel_other'];
            }

            $events[] = [
                'kind' => 'registered',
                'tone' => 'registered',
                'icon' => 'bi-folder-plus',
                'datetime' => (string) $case['reported_at'],
                'title' => 'Caso registrado',
                'lines' => array_values(array_filter([
                    $channel !== '' ? 'Canal: ' . $channel : null,
                    !empty($case['created_by_name']) ? 'Funcionario: ' . (string) $case['created_by_name'] : null,
                ])),
            ];
        }

        foreach ($case['actions'] ?? [] as $action) {
            if (empty($action['action_date'])) {
                continue;
            }

            $events[] = [
                'kind' => 'action',
                'tone' => 'action',
                'icon' => 'bi-lightning',
                'datetime' => self::combineDateTime(
                    (string) $action['action_date'],
                    isset($action['action_time_short']) ? (string) $action['action_time_short'] : null
                ),
                'title' => 'Acción',
                'lines' => array_values(array_filter([
                    !empty($action['action_type_name']) ? (string) $action['action_type_name'] : null,
                    !empty($action['description']) ? (string) $action['description'] : null,
                    !empty($action['institution']) ? 'Institución: ' . (string) $action['institution'] : null,
                    !empty($action['created_by_name']) ? 'Funcionario: ' . (string) $action['created_by_name'] : null,
                ])),
            ];
        }

        foreach ($case['referrals'] ?? [] as $referral) {
            if (empty($referral['referral_date'])) {
                continue;
            }

            $events[] = [
                'kind' => 'referral',
                'tone' => 'referral',
                'icon' => 'bi-send',
                'datetime' => (string) $referral['referral_date'] . ' 12:00:00',
                'title' => 'Derivación',
                'lines' => array_values(array_filter([
                    !empty($referral['institution_name']) ? 'Institución: ' . (string) $referral['institution_name'] : null,
                    !empty($referral['program_area']) ? (string) $referral['program_area'] : null,
                    !empty($referral['referral_status_name']) ? 'Estado: ' . (string) $referral['referral_status_name'] : null,
                    !empty($referral['created_by_name']) ? 'Funcionario: ' . (string) $referral['created_by_name'] : null,
                ])),
            ];
        }

        foreach ($case['followups'] ?? [] as $followup) {
            if (empty($followup['follow_up_date'])) {
                continue;
            }

            $contact = trim((string) ($followup['contact_type_name'] ?? ''));
            if (!empty($followup['contact_type_other'])) {
                $contact .= $contact !== '' ? ' — ' : '';
                $contact .= (string) $followup['contact_type_other'];
            }

            $events[] = [
                'kind' => 'followup',
                'tone' => 'followup',
                'icon' => 'bi-telephone',
                'datetime' => self::combineDateTime(
                    (string) $followup['follow_up_date'],
                    isset($followup['follow_up_time_short']) ? (string) $followup['follow_up_time_short'] : null
                ),
                'title' => 'Seguimiento',
                'lines' => array_values(array_filter([
                    $contact !== '' ? 'Contacto: ' . $contact : null,
                    !empty($followup['result_name']) ? 'Resultado: ' . (string) $followup['result_name'] : null,
                    !empty($followup['created_by_name']) ? 'Funcionario: ' . (string) $followup['created_by_name'] : null,
                ])),
            ];

            if (!empty($followup['is_pending']) && !empty($followup['next_follow_up_date'])) {
                $events[] = [
                    'kind' => 'next_follow_up',
                    'tone' => self::nextFollowUpTone((string) $followup['next_follow_up_date']),
                    'icon' => 'bi-calendar-event',
                    'datetime' => (string) $followup['next_follow_up_date'] . ' 08:00:00',
                    'title' => 'Próximo seguimiento',
                    'lines' => [
                        'Programado para ' . date('d-m-Y', strtotime((string) $followup['next_follow_up_date'])),
                    ],
                ];
            }
        }

        return $events;
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function presentOperationalEvent(array $event): array
    {
        $timestamp = strtotime((string) ($event['datetime'] ?? 'now'));

        return [
            'kind' => (string) ($event['kind'] ?? ''),
            'tone' => (string) ($event['tone'] ?? 'default'),
            'icon' => (string) ($event['icon'] ?? 'bi-circle'),
            'title' => (string) ($event['title'] ?? ''),
            'lines' => is_array($event['lines'] ?? null) ? $event['lines'] : [],
            'datetime_label' => $timestamp !== false
                ? date('d/m/Y H:i', $timestamp)
                : '—',
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
            'id' => (int) ($row['id'] ?? 0),
            'action' => (string) ($row['action'] ?? ''),
            'action_label' => AuditService::actionLabel((string) ($row['action'] ?? '')),
            'user_name' => (string) ($row['user_name'] ?? 'Sistema'),
            'datetime_label' => $timestamp !== false ? date('d/m/Y H:i:s', $timestamp) : '—',
            'ip_address' => (string) ($row['ip_address'] ?? '—'),
            'summary' => $this->auditSummary((string) ($row['action'] ?? '')),
        ];
    }

    private function auditSummary(string $action): string
    {
        return match ($action) {
            AuditService::ACTION_CREATED => 'Se creó el registro del caso.',
            AuditService::ACTION_UPDATED => 'Se modificaron antecedentes del caso.',
            AuditService::ACTION_VIEW_CASE => 'Consulta autorizada del detalle.',
            AuditService::ACTION_DELETED => 'Se eliminó información asociada.',
            default => 'Evento registrado en auditoría.',
        };
    }

    private static function combineDateTime(string $date, ?string $time): string
    {
        $time = $time !== null && $time !== '' ? substr($time, 0, 5) . ':00' : '12:00:00';

        return $date . ' ' . $time;
    }

    /**
     * @param array<string, mixed> $event
     */
    private static function eventSortKey(array $event): int
    {
        $timestamp = strtotime((string) ($event['datetime'] ?? ''));

        return $timestamp !== false ? $timestamp : 0;
    }

    private static function nextFollowUpTone(string $date): string
    {
        $today = date('Y-m-d');

        if ($date < $today) {
            return 'overdue';
        }

        if ($date === $today) {
            return 'due';
        }

        return 'scheduled';
    }
}
