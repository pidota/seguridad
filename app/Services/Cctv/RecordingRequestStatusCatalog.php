<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Repositories\Cctv\RecordingRequestStatusRepository;

final class RecordingRequestStatusCatalog
{
    public const PENDING_COMPLAINT = 'pending_complaint';
    public const INCOMPLETE_DOCUMENTATION = 'incomplete_documentation';
    public const PENDING_REVIEW = 'pending_review';
    public const UNDER_REVIEW = 'under_review';
    public const RECORDING_FOUND = 'recording_found';
    public const RECORDING_NOT_FOUND = 'recording_not_found';
    public const APPROVED = 'approved';
    public const DELIVERED = 'delivered';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';

    public function __construct(
        private readonly RecordingRequestStatusRepository $statuses = new RecordingRequestStatusRepository()
    ) {
    }

    /**
     * @return list<array{value: string, label: string, tone: string}>
     */
    public function options(): array
    {
        return array_map(
            static fn (array $row): array => [
                'value' => (string) $row['slug'],
                'label' => (string) $row['name'],
                'tone' => (string) ($row['tone'] ?? 'info'),
            ],
            $this->statuses->allActive()
        );
    }

    public function label(string $slug): string
    {
        $row = $this->statuses->findBySlug($slug);

        return $row !== null ? (string) $row['name'] : $slug;
    }

    public function tone(string $slug): string
    {
        $row = $this->statuses->findBySlug($slug);

        return $row !== null ? (string) ($row['tone'] ?? 'info') : 'info';
    }

    public function isValid(string $slug): bool
    {
        return $this->statuses->findBySlug($slug) !== null;
    }

    /**
     * @return list<string>
     */
    public function allowedTransitions(string $current, bool $hasApprove, bool $hasReview, bool $hasDeliver): array
    {
        unset($hasDeliver);

        return match ($current) {
            self::PENDING_REVIEW => $hasReview
                ? [self::UNDER_REVIEW, self::INCOMPLETE_DOCUMENTATION, self::REJECTED]
                : [],
            self::UNDER_REVIEW => $hasReview
                ? [self::RECORDING_FOUND, self::RECORDING_NOT_FOUND, self::INCOMPLETE_DOCUMENTATION, self::REJECTED]
                : [],
            self::RECORDING_FOUND => $hasApprove
                ? [self::APPROVED, self::REJECTED]
                : ($hasReview ? [self::REJECTED] : []),
            default => [],
        };
    }

    public function requiresComplaintForDelivery(string $status): bool
    {
        return in_array($status, [self::DELIVERED, self::APPROVED], true);
    }

    /**
     * @return list<string>
     */
    public function activeStatuses(): array
    {
        return [
            self::PENDING_COMPLAINT,
            self::INCOMPLETE_DOCUMENTATION,
            self::PENDING_REVIEW,
            self::UNDER_REVIEW,
            self::RECORDING_FOUND,
            self::RECORDING_NOT_FOUND,
            self::APPROVED,
        ];
    }
}
