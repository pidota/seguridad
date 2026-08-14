<?php

declare(strict_types=1);

namespace App\Services\Senda;

use App\Repositories\Senda\AssistResultRepository;
use App\Repositories\Senda\AssistedReferralRepository;
use App\Repositories\Senda\FollowUpRepository;
use App\Repositories\Senda\PersonRepository;

final class PersonHistoryService
{
    public function __construct(
        private readonly PersonRepository $people = new PersonRepository(),
        private readonly AssistedReferralRepository $referrals = new AssistedReferralRepository(),
        private readonly AssistResultRepository $assistResults = new AssistResultRepository(),
        private readonly FollowUpRepository $followUps = new FollowUpRepository(),
        private readonly FollowUpService $followUpPresenter = new FollowUpService(),
        private readonly AssistClassificationService $assistClassification = new AssistClassificationService()
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forPerson(int $personId): array
    {
        $attentions = $this->people->attentionsFor($personId);
        $attentionIds = array_map(static fn (array $row): int => (int) $row['id'], $attentions);
        $referrals = $this->indexLatestReferral($this->referrals->forAttentionIds($attentionIds));
        $assistByReferral = $this->assistResults->groupedByReferralIds(
            array_map(static fn (array $row): int => (int) $row['id'], array_values($referrals))
        );
        $followUpsByAttention = $this->groupFollowUps($this->followUps->forAttentionIds($attentionIds));

        $canViewReferral = hasPermission('senda.referrals.view');
        $canViewFollowUp = hasPermission('senda.followups.view');

        $history = [];
        foreach ($attentions as $index => $attention) {
            $history[] = $this->presentAttention(
                $attention,
                $index + 1,
                $referrals[(int) $attention['id']] ?? null,
                $assistByReferral,
                $followUpsByAttention[(int) $attention['id']] ?? [],
                $canViewReferral,
                $canViewFollowUp
            );
        }

        return $history;
    }

    /**
     * @param array<string, mixed> $attention
     * @param array<string, mixed>|null $referral
     * @param array<int, list<array<string, mixed>>> $assistByReferral
     * @param list<array<string, mixed>> $followUps
     * @return array<string, mixed>
     */
    private function presentAttention(
        array $attention,
        int $ordinal,
        ?array $referral,
        array $assistByReferral,
        array $followUps,
        bool $canViewReferral,
        bool $canViewFollowUp
    ): array {
        $entry = EntryType::meta((string) ($attention['entry_type'] ?? ''));
        $arrived = $this->formatDateTime($attention['attention_date'] ?? null, $attention['attention_time'] ?? null);
        $officer = trim((string) ($attention['created_by_name'] ?? ''));
        $hasFicha = $referral !== null;
        $presentedFollowUps = array_map([$this->followUpPresenter, 'present'], $followUps);
        $lastFollowUp = $presentedFollowUps !== [] ? $presentedFollowUps[array_key_last($presentedFollowUps)] : null;

        $ficha = null;
        $screeningAnswer = 'Sin ficha';
        $assistAnswer = 'Sin ficha';

        if ($hasFicha && $canViewReferral) {
            $ficha = $this->presentFicha($referral, $assistByReferral[(int) $referral['id']] ?? []);
            $screeningAnswer = $ficha['screening_label'];
            $assistAnswer = $ficha['assist_summary'];
        } elseif ($hasFicha && !$canViewReferral) {
            $screeningAnswer = 'Sin permiso para consultar la ficha';
            $assistAnswer = 'Sin permiso para consultar la ficha';
        }

        $hadFollowUp = $presentedFollowUps !== [];
        $lastAnswer = 'No';
        $nextAnswer = 'No';

        if (!$canViewFollowUp && $hadFollowUp) {
            $lastAnswer = 'Sin permiso para consultar seguimientos';
            $nextAnswer = 'Sin permiso para consultar seguimientos';
        } elseif ($lastFollowUp !== null) {
            $lastAnswer = $this->followUpLine($lastFollowUp);
            $nextAnswer = $this->nextFollowUpAnswer($lastFollowUp);
        }

        $children = [];
        if ($hasFicha) {
            $children[] = [
                'kind' => 'ficha',
                'label' => 'Ficha Referencia',
                'meta' => $ficha['status_label'] ?? ($canViewReferral ? 'Registrada' : ''),
                'url' => $this->fichaUrl($referral),
            ];
        }

        if ($canViewFollowUp) {
            foreach ($presentedFollowUps as $index => $followUp) {
                $isLastFollowUp = $lastFollowUp !== null && (int) $followUp['id'] === (int) $lastFollowUp['id'];
                $meta = $this->followUpLine($followUp);
                if ($isLastFollowUp && FollowUpStatus::isPending($followUp)) {
                    $meta .= ' · Próximo: ' . $this->nextFollowUpAnswer($followUp);
                }

                $children[] = [
                    'kind' => 'followup',
                    'label' => 'Seguimiento ' . ($index + 1),
                    'meta' => $meta,
                    'url' => url('/senda/follow-ups/' . $followUp['id']),
                    'is_last' => $isLastFollowUp,
                    'is_overdue' => !empty($followUp['is_overdue']),
                    'is_due_today' => !empty($followUp['is_due_today']),
                ];
            }
        }

        return [
            'id' => (int) $attention['id'],
            'ordinal' => $ordinal,
            'attention_number' => (string) ($attention['attention_number'] ?? ''),
            'arrived_at' => $arrived,
            'entry_type' => $entry['value'],
            'entry_label' => $entry['label'],
            'entry_tone' => $entry['tone'],
            'officer' => $officer !== '' ? $officer : '—',
            'url' => hasPermission('senda.attentions.edit')
                ? url('/senda/attentions/' . $attention['id'] . '/edit')
                : null,
            'has_ficha' => $hasFicha,
            'ficha' => $ficha,
            'follow_ups' => $presentedFollowUps,
            'last_follow_up' => $lastFollowUp,
            'children' => $children,
            'answers' => [
                ['question' => '¿Cuándo llegó?', 'answer' => $arrived],
                ['question' => '¿Fue derivación o demanda espontánea?', 'answer' => $entry['label']],
                ['question' => '¿Quién realizó la atención?', 'answer' => $officer !== '' ? $officer : '—'],
                ['question' => '¿Se creó ficha?', 'answer' => $hasFicha ? 'Sí' : 'No'],
                ['question' => '¿Se utilizó tamizaje?', 'answer' => $screeningAnswer],
                ['question' => '¿Cuál fue el resultado ASSIST?', 'answer' => $assistAnswer],
                ['question' => '¿Tuvo seguimiento?', 'answer' => $hadFollowUp ? 'Sí (' . count($presentedFollowUps) . ')' : 'No'],
                ['question' => '¿Cuál fue el último seguimiento?', 'answer' => $lastAnswer],
                ['question' => '¿Existe un próximo seguimiento?', 'answer' => $nextAnswer],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $referral
     * @param list<array<string, mixed>> $assistRows
     * @return array<string, mixed>
     */
    private function presentFicha(array $referral, array $assistRows): array
    {
        $status = ReferralStatus::fromRow($referral);
        $screening = $referral['screening_used'] ?? null;
        $used = $screening !== null && $screening !== '' && (int) $screening === 1;

        return [
            'id' => (int) $referral['id'],
            'status' => $status,
            'status_label' => ReferralStatus::label($status),
            'screening_used' => $used,
            'screening_label' => $used ? 'Sí' : 'No',
            'assist_summary' => $used ? $this->assistSummary($assistRows) : 'No aplica (sin tamizaje)',
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function assistSummary(array $rows): string
    {
        $parts = [];
        $substances = [];
        foreach (AssistedReferralCatalog::assistSubstances() as $substance) {
            $substances[$substance['key']] = $substance['label'];
        }

        foreach ($rows as $row) {
            if ($row['score'] === null || $row['score'] === '') {
                continue;
            }

            $key = (string) $row['substance'];
            $label = $substances[$key] ?? $key;
            $risk = $this->assistClassification->label(
                $row['risk_level'] !== null && $row['risk_level'] !== ''
                    ? (string) $row['risk_level']
                    : $this->assistClassification->classify($key, (int) $row['score'])
            );
            $parts[] = $label . ' ' . (int) $row['score'] . ' (' . $risk . ')';
        }

        return $parts === [] ? 'Tamizaje sí, sin puntajes ingresados' : implode(' · ', $parts);
    }

    /**
     * @param array<string, mixed> $followUp
     */
    private function followUpLine(array $followUp): string
    {
        $date = !empty($followUp['follow_up_date'])
            ? date('d-m-Y', strtotime((string) $followUp['follow_up_date']))
            : '—';
        $parts = [
            $date,
            (string) ($followUp['contact_type_label'] ?? '—'),
            (string) ($followUp['result_label'] ?? '—'),
        ];

        return implode(' · ', $parts);
    }

    /**
     * @param array<string, mixed> $followUp
     */
    private function nextFollowUpAnswer(array $followUp): string
    {
        if (!FollowUpStatus::isPending($followUp)) {
            return 'No';
        }

        $next = FollowUpStatus::nextDate($followUp);
        if ($next === null) {
            return 'No';
        }

        $label = date('d-m-Y', strtotime($next));

        if (FollowUpStatus::isOverdue($followUp)) {
            return $label . ' (Atrasado)';
        }

        if (FollowUpStatus::isDueToday($followUp)) {
            return $label . ' (Hoy)';
        }

        return $label;
    }

    /**
     * @param array<string, mixed> $referral
     */
    private function fichaUrl(array $referral): ?string
    {
        $id = (int) ($referral['id'] ?? 0);
        if ($id < 1) {
            return null;
        }

        $completed = ReferralStatus::isCompleted($referral);
        if ($completed && !hasPermission('senda.referrals.edit_completed') && !hasPermission('senda.referrals.edit')) {
            return null;
        }

        if (!$completed && !hasPermission('senda.referrals.edit')) {
            return null;
        }

        return url('/senda/referrals/' . $id . '/edit');
    }

    private function formatDateTime(mixed $date, mixed $time): string
    {
        $date = trim((string) $date);
        if ($date === '') {
            return '—';
        }

        $label = date('d-m-Y', strtotime($date));
        $time = substr(trim((string) $time), 0, 5);

        return $time !== '' ? $label . ' ' . $time : $label;
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

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<int, list<array<string, mixed>>>
     */
    private function groupFollowUps(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['senda_attention_id']][] = $row;
        }

        return $grouped;
    }
}
