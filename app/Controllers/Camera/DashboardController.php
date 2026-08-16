<?php

declare(strict_types=1);

namespace App\Controllers\Camera;

use App\Services\Cctv\CameraService;
use App\Services\Cctv\LogEntryService;
use App\Services\Cctv\ShiftService;
use App\Services\Cctv\VisitDashboardService;
use Core\Auth;
use Core\Request;

final class DashboardController extends CameraController
{
    public function __construct(
        private readonly ShiftService $shifts = new ShiftService(),
        private readonly LogEntryService $logEntries = new LogEntryService(),
        private readonly CameraService $cameras = new CameraService(),
        private readonly VisitDashboardService $visitDashboard = new VisitDashboardService()
    ) {
    }

    public function index(Request $request): void
    {
        $shiftPanel = hasPermission('cctv.shifts.view')
            ? $this->shifts->dashboardForOperator(Auth::id())
            : [
                'open_shift' => null,
                'last_shift' => null,
                'opening_checks' => [],
                'can_start' => false,
            ];

        $openShift = $shiftPanel['open_shift'] ?? null;
        $logOrder = strtolower(trim((string) $request->query('log_order', 'desc'))) === 'asc' ? 'asc' : 'desc';
        $activeShiftDashboard = null;
        $shiftTimeline = ['items' => [], 'order' => $logOrder, 'total' => 0];
        $camerasWithIssues = $this->cameras->countMonitoringIssues();

        if ($openShift !== null) {
            if (hasPermission('cctv.log.view')) {
                $activeShiftDashboard = $this->logEntries->activeShiftDashboard(
                    $openShift,
                    $shiftPanel['opening_checks'] ?? [],
                    $camerasWithIssues,
                    10,
                    $logOrder
                );
                $shiftTimeline = $activeShiftDashboard['timeline'];
            } else {
                $activeShiftDashboard = [
                    'stats' => [
                        'total_entries' => 0,
                        'incidents' => 0,
                        'general_entries' => 0,
                        'technical_issues' => 0,
                        'coordinations' => 0,
                        'police_communications' => 0,
                        'cameras_with_issues' => $camerasWithIssues,
                    ],
                    'recent_items' => [],
                ];
            }
        }

        $supervisionDashboard = hasPermission('cctv.shifts.view_all')
            ? $this->shifts->supervisionDashboard($camerasWithIssues, 8)
            : null;

        $visitsDashboard = hasPermission('cctv.visits.view')
            ? $this->visitDashboard->operatorPanel()
            : null;

        $this->cameraView('dashboard/index', [
            'title' => 'Central de Cámaras',
            'shiftPanel' => $shiftPanel,
            'supervisionDashboard' => $supervisionDashboard,
            'visitsDashboard' => $visitsDashboard,
            'activeShiftDashboard' => $activeShiftDashboard,
            'shiftTimeline' => $shiftTimeline,
            'logOrderOptions' => LogEntryService::timelineOrderOptions(),
            'canViewLog' => hasPermission('cctv.log.view'),
            'canViewAllLog' => hasPermission('cctv.log.view_all'),
            'canCreateLog' => hasPermission('cctv.log.create'),
            'canCreateVisit' => hasPermission('cctv.visits.create'),
            'canCloseShift' => hasPermission('cctv.shifts.close'),
            'canEditLog' => hasPermission('cctv.log.edit'),
            'canViewCamerasMap' => hasPermission('cctv.cameras.view'),
            'camerasMapCount' => hasPermission('cctv.cameras.view')
                ? count($this->cameras->listForMap(!hasPermission('cctv.cameras.manage')))
                : 0,
            'moduleScripts' => $this->cctvScripts('dashboard.js'),
        ]);
    }
}
