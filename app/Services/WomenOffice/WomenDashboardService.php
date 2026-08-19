<?php

declare(strict_types=1);

namespace App\Services\WomenOffice;

use App\Repositories\WomenOffice\CaseRepository;
use Core\Auth;

final class WomenDashboardService
{
    public function __construct(
        private readonly CaseRepository $cases = new CaseRepository()
    ) {
    }

    /**
     * @return list<array{key: string, label: string, count: int, tone: string, path: string}>
     */
    public function summaryMetrics(): array
    {
        $scope = $this->scopedUserId();
        $monthStart = date('Y-m-01');

        return [
            [
                'key' => 'today',
                'label' => 'Denuncias registradas hoy',
                'count' => $this->cases->countRegisteredToday($scope),
                'tone' => 'today',
                'path' => '/women/cases?' . http_build_query([
                    'date_from' => date('Y-m-d'),
                    'date_to' => date('Y-m-d'),
                ]),
            ],
            [
                'key' => 'month',
                'label' => 'Denuncias del mes',
                'count' => $this->cases->countRegisteredThisMonth($scope),
                'tone' => 'month',
                'path' => '/women/cases?' . http_build_query([
                    'date_from' => $monthStart,
                    'date_to' => date('Y-m-d'),
                ]),
            ],
            [
                'key' => 'active',
                'label' => 'Casos activos',
                'count' => $this->cases->countActive($scope),
                'tone' => 'active',
                'path' => '/women/cases',
            ],
            [
                'key' => 'pending_followups',
                'label' => 'Casos con seguimiento pendiente',
                'count' => $this->cases->countPendingFollowUps($scope),
                'tone' => 'pending',
                'path' => '/women/follow-ups',
            ],
            [
                'key' => 'closed',
                'label' => 'Casos cerrados',
                'count' => $this->cases->countClosed($scope),
                'tone' => 'closed',
                'path' => '/women/cases',
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, count: int, tone: string, path: string}>
     */
    public function metrics(): array
    {
        return $this->summaryMetrics();
    }

    /**
     * @return array{
     *     due_today: int,
     *     overdue: int,
     *     pending_referrals: int,
     *     urgent_cases: int
     * }
     */
    public function alerts(): array
    {
        $scope = $this->scopedUserId();

        return [
            'due_today' => $this->cases->countFollowUpsDueToday($scope),
            'overdue' => $this->cases->countFollowUpsOverdue($scope),
            'pending_referrals' => $this->cases->countPendingReferrals($scope),
            'urgent_cases' => $this->cases->countUrgentActive($scope),
        ];
    }

    /**
     * @return list<array{key: string, label: string, count: int, tone: string, path: string}>
     */
    public function alertCards(): array
    {
        $alerts = $this->alerts();

        return [
            [
                'key' => 'due_today',
                'label' => 'Seguimientos para hoy',
                'count' => $alerts['due_today'],
                'tone' => 'due',
                'path' => '/women/follow-ups?due=today',
            ],
            [
                'key' => 'overdue',
                'label' => 'Seguimientos atrasados',
                'count' => $alerts['overdue'],
                'tone' => 'overdue',
                'path' => '/women/follow-ups?due=overdue',
            ],
            [
                'key' => 'pending_referrals',
                'label' => 'Derivaciones pendientes',
                'count' => $alerts['pending_referrals'],
                'tone' => 'referral',
                'path' => '/women/cases?referral_pending=yes',
            ],
            [
                'key' => 'urgent_cases',
                'label' => 'Prioridad urgente activa',
                'count' => $alerts['urgent_cases'],
                'tone' => 'urgent',
                'path' => '/women/cases?priority=urgent',
            ],
        ];
    }

    private function scopedUserId(): ?int
    {
        if (hasPermission('women.cases.view_all')) {
            return null;
        }

        $userId = Auth::id();

        return $userId !== null ? (int) $userId : null;
    }
}
