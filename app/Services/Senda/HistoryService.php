<?php

declare(strict_types=1);

namespace App\Services\Senda;

use App\Repositories\Senda\AssistResultRepository;
use App\Repositories\Senda\AssistedReferralRepository;
use App\Repositories\Senda\FollowUpRepository;
use App\Repositories\Senda\PersonRepository;
use App\Services\AuditService;

/**
 * Ficha histórica consolidada de una persona en SENDA.
 * Combina atenciones, fichas, ASSIST y seguimientos sin N+1.
 */
final class HistoryService
{
    public function __construct(
        private readonly PersonRepository $people = new PersonRepository(),
        private readonly AssistedReferralRepository $referrals = new AssistedReferralRepository(),
        private readonly AssistResultRepository $assistResults = new AssistResultRepository(),
        private readonly FollowUpRepository $followUps = new FollowUpRepository(),
        private readonly FollowUpService $followUpPresenter = new FollowUpService(),
        private readonly AssistClassificationService $assistClassification = new AssistClassificationService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dossier(int $personId, string $order = 'desc'): array
    {
        $record = $this->people->findById($personId);

        if ($record === null) {
            throw new \Core\Exceptions\HttpException(404, 'La persona no existe.');
        }

        $canViewPeople = hasPermission('senda.people.view');
        $canViewAttentions = hasPermission('senda.attentions.view');
        $canViewReferrals = hasPermission('senda.referrals.view');
        $canViewFollowUps = hasPermission('senda.followups.view');

        $attentionRows = $canViewAttentions || $canViewReferrals || $canViewFollowUps
            ? $this->people->attentionsFor($personId)
            : [];
        $attentionIds = array_map(static fn (array $row): int => (int) $row['id'], $attentionRows);

        $referralRows = ($canViewReferrals && $attentionIds !== [])
            ? $this->referrals->forAttentionIds($attentionIds)
            : [];
        $referralsByAttention = $this->indexLatestReferral($referralRows);
        $assistByReferral = $canViewReferrals
            ? $this->assistResults->groupedByReferralIds(array_map(
                static fn (array $row): int => (int) $row['id'],
                $referralRows
            ))
            : [];

        $followUpRows = ($canViewFollowUps && $attentionIds !== [])
            ? $this->followUps->forAttentionIds($attentionIds)
            : [];
        $presentedFollowUps = array_map([$this->followUpPresenter, 'present'], $followUpRows);

        $attentions = array_map(fn (array $row): array => $this->presentAttention(
            $row,
            $referralsByAttention[(int) $row['id']] ?? null,
            $assistByReferral
        ), $attentionRows);

        usort($attentions, static function (array $a, array $b): int {
            return strcmp(
                (string) $b['sort_key'],
                (string) $a['sort_key']
            );
        });

        $referrals = [];
        foreach ($referralRows as $row) {
            $referrals[] = $this->presentReferral($row, $assistByReferral[(int) $row['id']] ?? []);
        }

        usort($presentedFollowUps, static function (array $a, array $b): int {
            return strcmp(
                (string) ($b['follow_up_date'] ?? '') . (string) ($b['follow_up_time'] ?? ''),
                (string) ($a['follow_up_date'] ?? '') . (string) ($a['follow_up_time'] ?? '')
            );
        });

        $timeline = $this->timeline(
            $canViewAttentions ? $attentions : [],
            $canViewReferrals ? $referrals : [],
            $canViewFollowUps ? $presentedFollowUps : [],
            $order
        );
        $next = $canViewFollowUps ? $this->nextFollowUp($presentedFollowUps) : null;
        $lastFollowUp = $canViewFollowUps ? ($presentedFollowUps[0] ?? null) : null;
        $lastAttention = $canViewAttentions ? ($attentions[0] ?? null) : null;
        $firstAttention = $canViewAttentions && $attentions !== []
            ? $attentions[array_key_last($attentions)]
            : null;

        return [
            'person' => $this->presentPerson($record, $canViewPeople),
            'permissions' => [
                'people' => $canViewPeople,
                'attentions' => $canViewAttentions,
                'referrals' => $canViewReferrals,
                'followups' => $canViewFollowUps,
            ],
            'metrics' => [
                'attentions_count' => $canViewAttentions ? count($attentions) : 0,
                'referrals_count' => $canViewReferrals ? count($referrals) : 0,
                'followups_count' => $canViewFollowUps ? count($presentedFollowUps) : 0,
                'first_attention' => $firstAttention,
                'last_attention' => $lastAttention,
                'last_follow_up' => $lastFollowUp,
                'next_follow_up' => $next,
                'last_action' => $this->lastAction($timeline),
            ],
            'timeline' => $timeline,
            'attentions' => $canViewAttentions ? $attentions : [],
            'referrals' => $canViewReferrals ? $referrals : [],
            'follow_ups' => $canViewFollowUps ? $presentedFollowUps : [],
            'order' => $order === 'asc' ? 'asc' : 'desc',
        ];
    }

    public function auditConsultation(int $personId, string $rut): void
    {
        $this->audit->log(
            AuditService::ACTION_VIEW_PERSON_HISTORY,
            AuditService::MODULE_SENDA,
            AuditService::RESOURCE_PERSON,
            $personId,
            null,
            ['person_id' => $personId, 'rut' => $rut]
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentPerson(array $row, bool $canViewPeople): array
    {
        $person = (new \App\Services\Senda\PersonService())->present($row);

        if ($canViewPeople) {
            return $person;
        }

        return [
            'id' => $person['id'],
            'full_name' => $person['full_name'],
            'rut' => $person['rut'],
            'birth_date' => $person['birth_date'] ?? null,
            'age' => $person['age'] ?? null,
            'phone' => null,
            'email' => null,
            'address' => null,
            'education' => null,
            'occupation' => null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $referral
     * @param array<int, list<array<string, mixed>>> $assistByReferral
     * @return array<string, mixed>
     */
    private function presentAttention(array $row, ?array $referral, array $assistByReferral): array
    {
        $entry = EntryType::meta((string) ($row['entry_type'] ?? ''));
        $time = substr(trim((string) ($row['attention_time'] ?? '')), 0, 5);
        $date = substr(trim((string) ($row['attention_date'] ?? '')), 0, 10);
        $fichaId = isset($row['ficha_id']) && $row['ficha_id'] !== null ? (int) $row['ficha_id'] : null;

        return [
            'id' => (int) $row['id'],
            'attention_number' => (string) ($row['attention_number'] ?? ''),
            'attention_date' => $date,
            'attention_time' => $time,
            'datetime_label' => $this->formatDateTime($date, $time),
            'sort_key' => $date . ' ' . ($time !== '' ? $time : '00:00') . ':00',
            'entry_type' => $entry['value'],
            'entry_label' => $entry['label'],
            'entry_tone' => $entry['tone'],
            'officer' => trim((string) ($row['created_by_name'] ?? '')) ?: '—',
            'referral_institution_type' => (string) ($row['referral_institution_type'] ?? ''),
            'referral_institution_type_label' => ReferralInstitutionType::isValid((string) ($row['referral_institution_type'] ?? ''))
                ? ReferralInstitutionType::label((string) $row['referral_institution_type'])
                : '',
            'referral_institution_name' => (string) ($row['referral_institution_name'] ?? ''),
            'ficha_id' => $fichaId,
            'has_ficha' => $fichaId !== null,
            'followup_count' => (int) ($row['followup_count'] ?? 0),
            'url' => hasPermission('senda.attentions.view')
                ? url('/senda/attentions/' . $row['id'])
                : null,
            'referral' => $referral !== null
                ? $this->presentReferral($referral, $assistByReferral[(int) $referral['id']] ?? [])
                : null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $assistRows
     * @return array<string, mixed>
     */
    private function presentReferral(array $row, array $assistRows): array
    {
        $status = ReferralStatus::fromRow($row);
        $screening = $row['screening_used'] ?? null;
        $used = $screening !== null && $screening !== '' && (int) $screening === 1;
        $completed = ReferralStatus::isCompleted($status);
        $requestDate = substr(trim((string) ($row['request_date'] ?? '')), 0, 10);
        $createdAt = trim((string) ($row['created_at'] ?? ''));
        $time = $createdAt !== '' ? substr($createdAt, 11, 5) : '';
        $finishedAt = $completed ? substr(trim((string) ($row['updated_at'] ?? $createdAt)), 0, 10) : '';

        $assist = $this->presentAssistRows($assistRows);

        return [
            'id' => (int) $row['id'],
            'senda_attention_id' => (int) ($row['senda_attention_id'] ?? 0),
            'attention_number' => (string) ($row['attention_number'] ?? ''),
            'request_date' => $requestDate,
            'datetime_label' => $this->formatDateTime($requestDate, $time),
            'sort_key' => $requestDate . ' ' . ($time !== '' ? $time : '00:01') . ':00',
            'status' => $status,
            'status_label' => ReferralStatus::label($status),
            'is_completed' => $completed,
            'finished_at' => $finishedAt,
            'finished_label' => $finishedAt !== '' ? date('d-m-Y', strtotime($finishedAt)) : '—',
            'screening_used' => $used,
            'screening_label' => $used ? 'Sí' : 'No',
            'officer' => trim((string) ($row['created_by_name'] ?? '')) ?: '—',
            'assist' => $assist,
            'assist_summary' => $used ? $this->assistSummary($assist) : 'No aplica (sin tamizaje)',
            'url' => hasPermission('senda.referrals.view')
                ? url('/senda/referrals/' . $row['id'])
                : null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{key: string, label: string, score: string, risk_level: string, risk_label: string}>
     */
    private function presentAssistRows(array $rows): array
    {
        $byKey = [];
        foreach ($rows as $row) {
            $byKey[(string) ($row['substance'] ?? '')] = $row;
        }

        $items = [];
        foreach (AssistedReferralCatalog::assistSubstances() as $substance) {
            $key = $substance['key'];
            $row = $byKey[$key] ?? [];
            $score = $row['score'] ?? null;
            $stored = trim((string) ($row['risk_level'] ?? ''));

            $items[] = [
                'key' => $key,
                'label' => $substance['label'],
                'score' => $score === null || $score === '' ? '' : (string) (int) $score,
                'risk_level' => $stored,
                'risk_label' => $stored !== '' ? $this->assistClassification->label($stored) : '—',
            ];
        }

        return $items;
    }

    /**
     * @param list<array{score: string, label: string, risk_label: string}> $assist
     */
    private function assistSummary(array $assist): string
    {
        $parts = [];
        foreach ($assist as $row) {
            if ($row['score'] === '') {
                continue;
            }

            $parts[] = $row['label'] . ': ' . $row['risk_label'];
        }

        return $parts === [] ? 'Tamizaje sí, sin puntajes ingresados' : implode(' · ', $parts);
    }

    /**
     * @param list<array<string, mixed>> $attentions
     * @param list<array<string, mixed>> $referrals
     * @param list<array<string, mixed>> $followUps
     * @return list<array<string, mixed>>
     */
    private function timeline(array $attentions, array $referrals, array $followUps, string $order): array
    {
        $events = [];

        foreach ($attentions as $attention) {
            $lines = [
                $attention['entry_label'],
                'N.º ' . $attention['attention_number'],
                'Funcionario: ' . $attention['officer'],
            ];
            if ($attention['entry_type'] === EntryType::DERIVACION && $attention['referral_institution_name'] !== '') {
                $lines[] = 'Institución: ' . $attention['referral_institution_name'];
            }

            $events[] = [
                'sort_key' => $attention['sort_key'] . '-1',
                'type' => 'attention',
                'type_label' => 'Atención',
                'badge' => $attention['entry_label'],
                'tone' => $attention['entry_tone'],
                'icon' => $attention['entry_type'] === EntryType::DERIVACION ? 'bi-signpost-split' : 'bi-person-walking',
                'datetime_label' => $attention['datetime_label'],
                'officer' => $attention['officer'],
                'lines' => $lines,
                'url' => $attention['url'],
            ];
        }

        foreach ($referrals as $referral) {
            $events[] = [
                'sort_key' => $referral['sort_key'] . '-2',
                'type' => 'referral',
                'type_label' => 'Ficha de Referencia',
                'badge' => 'Ficha de Referencia',
                'tone' => 'referral',
                'icon' => 'bi-file-earmark-medical',
                'datetime_label' => $referral['datetime_label'],
                'officer' => $referral['officer'],
                'lines' => [
                    'Tamizaje aplicado: ' . $referral['screening_label'],
                    'Estado: ' . $referral['status_label'],
                    'Atención ' . $referral['attention_number'],
                    'Funcionario: ' . $referral['officer'],
                ],
                'url' => $referral['url'],
            ];

            if ($referral['screening_used']) {
                $events[] = [
                    'sort_key' => $referral['sort_key'] . '-3',
                    'type' => 'assist',
                    'type_label' => 'Tamizaje ASSIST',
                    'badge' => 'Tamizaje ASSIST',
                    'tone' => 'assist',
                    'icon' => 'bi-clipboard2-data',
                    'datetime_label' => $referral['datetime_label'],
                    'officer' => $referral['officer'],
                    'lines' => [$referral['assist_summary']],
                    'url' => $referral['url'],
                ];
            }
        }

        foreach ($followUps as $followUp) {
            $date = substr(trim((string) ($followUp['follow_up_date'] ?? '')), 0, 10);
            $time = substr(trim((string) ($followUp['follow_up_time'] ?? '')), 0, 5);
            $next = FollowUpStatus::nextDate($followUp);
            $lines = [
                'Tipo: ' . (string) ($followUp['contact_type_label'] ?? '—'),
                'Resultado: ' . (string) ($followUp['result_label'] ?? '—'),
                'Funcionario: ' . (trim((string) ($followUp['created_by_name'] ?? '')) ?: '—'),
            ];
            if ($next !== null) {
                $lines[] = 'Próximo seguimiento: ' . date('d-m-Y', strtotime($next));
            }

            $events[] = [
                'sort_key' => $date . ' ' . ($time !== '' ? $time : '00:00') . ':00-4',
                'type' => 'followup',
                'type_label' => 'Seguimiento',
                'badge' => 'Seguimiento',
                'tone' => 'followup',
                'icon' => 'bi-arrow-repeat',
                'datetime_label' => $this->formatDateTime($date, $time),
                'officer' => trim((string) ($followUp['created_by_name'] ?? '')) ?: '—',
                'lines' => $lines,
                'url' => url('/senda/follow-ups/' . $followUp['id']),
            ];
        }

        usort($events, static function (array $a, array $b) use ($order): int {
            $cmp = strcmp((string) $a['sort_key'], (string) $b['sort_key']);

            return $order === 'asc' ? $cmp : -$cmp;
        });

        return $events;
    }

    /**
     * @param list<array<string, mixed>> $followUps
     * @return array<string, mixed>|null
     */
    private function nextFollowUp(array $followUps): ?array
    {
        $pending = [];
        foreach ($followUps as $row) {
            if (!FollowUpStatus::isPending($row)) {
                continue;
            }

            $pending[] = $row;
        }

        if ($pending === []) {
            return null;
        }

        usort($pending, static function (array $a, array $b): int {
            return strcmp((string) FollowUpStatus::nextDate($a), (string) FollowUpStatus::nextDate($b));
        });

        $chosen = $pending[0];
        $date = FollowUpStatus::nextDate($chosen) ?? '';
        $status = FollowUpStatus::OVERDUE;
        if (FollowUpStatus::isDueToday($chosen)) {
            $status = FollowUpStatus::DUE_TODAY;
        } elseif (!FollowUpStatus::isOverdue($chosen)) {
            $status = FollowUpStatus::PENDING;
        }

        return [
            'date' => $date,
            'date_label' => $date !== '' ? date('d-m-Y', strtotime($date)) : '—',
            'status' => $status,
            'status_label' => match ($status) {
                FollowUpStatus::DUE_TODAY => 'Seguimiento para hoy',
                FollowUpStatus::OVERDUE => 'Seguimiento atrasado',
                default => 'Próximo seguimiento',
            },
            'tone' => FollowUpStatus::tone($status),
            'follow_up' => $chosen,
        ];
    }

    /**
     * @param list<array<string, mixed>> $timeline
     * @return array<string, mixed>|null
     */
    private function lastAction(array $timeline): ?array
    {
        if ($timeline === []) {
            return null;
        }

        $latest = $timeline[0];
        foreach ($timeline as $event) {
            if (strcmp((string) $event['sort_key'], (string) $latest['sort_key']) > 0) {
                $latest = $event;
            }
        }

        return $latest;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function indexLatestReferral(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $attentionId = (int) $row['senda_attention_id'];
            if (!isset($indexed[$attentionId])) {
                $indexed[$attentionId] = $row;
            }
        }

        return $indexed;
    }

    private function formatDateTime(string $date, string $time): string
    {
        if ($date === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return '—';
        }

        $label = date('d-m-Y', strtotime($date));

        return $time !== '' ? $label . ' ' . $time : $label;
    }
}
