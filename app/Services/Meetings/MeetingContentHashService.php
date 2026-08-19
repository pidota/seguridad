<?php

declare(strict_types=1);

namespace App\Services\Meetings;

final class MeetingContentHashService
{
    /**
     * @param array<string, mixed> $meeting
     */
    public function compute(array $meeting): string
    {
        $payload = [
            'meeting_number' => (string) ($meeting['meeting_number'] ?? ''),
            'meeting_date' => (string) ($meeting['meeting_date'] ?? ''),
            'meeting_time' => substr((string) ($meeting['meeting_time'] ?? ''), 0, 8),
            'meeting_place' => trim((string) ($meeting['meeting_place'] ?? '')),
            'additional_notes' => trim((string) ($meeting['additional_notes'] ?? '')),
            'next_meeting_required' => !empty($meeting['next_meeting_required']) ? 1 : 0,
            'next_meeting_date' => (string) ($meeting['next_meeting_date'] ?? ''),
            'next_meeting_time' => substr((string) ($meeting['next_meeting_time'] ?? ''), 0, 8),
            'next_meeting_notes' => trim((string) ($meeting['next_meeting_notes'] ?? '')),
            'content_version' => (int) ($meeting['content_version'] ?? 1),
            'participants' => $this->participantPayload($meeting['participants'] ?? []),
            'topics' => $this->topicPayload($meeting['topics'] ?? []),
            'agreements' => $this->agreementPayload($meeting['agreements'] ?? []),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function participantPayload(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            if (($row['participant_type'] ?? '') === 'internal') {
                $items[] = [
                    'type' => 'internal',
                    'user_id' => (int) ($row['user_id'] ?? 0),
                    'signature_required' => !empty($row['signature_required']) ? 1 : 0,
                ];
            } else {
                $items[] = [
                    'type' => 'external',
                    'name' => trim((string) ($row['external_name'] ?? '')),
                    'position' => trim((string) ($row['external_position'] ?? '')),
                    'organization' => trim((string) ($row['external_organization'] ?? '')),
                ];
            }
        }

        usort($items, static fn (array $a, array $b): int => json_encode($a) <=> json_encode($b));

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{position: int, description: string}>
     */
    private function topicPayload(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'position' => (int) ($row['position'] ?? 0),
                'description' => trim((string) ($row['description'] ?? '')),
            ];
        }

        usort($items, static fn (array $a, array $b): int => ($a['position'] <=> $b['position']));

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function agreementPayload(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'position' => (int) ($row['position'] ?? 0),
                'description' => trim((string) ($row['description'] ?? '')),
                'responsible_user_id' => (int) ($row['responsible_user_id'] ?? 0) ?: null,
                'responsible_text' => trim((string) ($row['responsible_text'] ?? '')),
                'due_date' => (string) ($row['due_date'] ?? ''),
            ];
        }

        usort($items, static fn (array $a, array $b): int => ($a['position'] <=> $b['position']));

        return $items;
    }
}
