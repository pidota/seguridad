<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Repositories\Cctv\OfficeVisitRepository;
use App\Repositories\Cctv\RecordingRequestRepository;

final class VisitDashboardService
{
    public function __construct(
        private readonly OfficeVisitRepository $visits = new OfficeVisitRepository(),
        private readonly RecordingRequestRepository $requests = new RecordingRequestRepository(),
        private readonly RecordingRequestStatusCatalog $statuses = new RecordingRequestStatusCatalog()
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function operatorPanel(?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $pendingDays = (int) cctv_config('recording_request_pending_alert_days', 3);

        $pendingAlerts = array_map(function (array $row): array {
            $row['status_label'] = $this->statuses->label((string) $row['status']);
            $row['status_tone'] = $this->statuses->tone((string) $row['status']);

            return $row;
        }, $this->requests->pendingAlerts(8));

        $currentVisits = array_map(function (array $row): array {
            $row['visit_reason_label'] = VisitReasonCatalog::label($row['visit_reason'] ?? null);

            return $row;
        }, $this->visits->currentInOffice($today));

        $stale = array_map(function (array $row): array {
            $row['status_label'] = $this->statuses->label((string) $row['status']);

            return $row;
        }, $this->requests->stalePending($pendingDays, 8));

        $supervision = array_map(function (array $row): array {
            $row['status_label'] = $this->statuses->label((string) $row['status']);
            $row['status_tone'] = $this->statuses->tone((string) $row['status']);

            return $row;
        }, $this->requests->supervisionList(12));

        return [
            'today' => $today,
            'pending_alert_days' => $pendingDays,
            'visits_today' => $this->visits->countToday($today),
            'recording_requests_today' => $this->requests->countToday($today),
            'pending_complaint' => $this->requests->countByStatus(RecordingRequestStatusCatalog::PENDING_COMPLAINT),
            'incomplete_documentation' => $this->requests->countByStatus(RecordingRequestStatusCatalog::INCOMPLETE_DOCUMENTATION),
            'pending_review' => $this->requests->countByStatus(RecordingRequestStatusCatalog::PENDING_REVIEW),
            'recording_found' => $this->requests->countByStatus(RecordingRequestStatusCatalog::RECORDING_FOUND),
            'approved_for_delivery' => $this->requests->countByStatus(RecordingRequestStatusCatalog::APPROVED),
            'delivered_total' => $this->requests->countByStatus(RecordingRequestStatusCatalog::DELIVERED),
            'delivered_today' => $this->requests->countDeliveredToday($today),
            'pending_alerts' => $pendingAlerts,
            'stale_requests' => $stale,
            'supervision' => $supervision,
            'current_visits' => $currentVisits,
            'current_visits_count' => count($currentVisits),
        ];
    }
}
