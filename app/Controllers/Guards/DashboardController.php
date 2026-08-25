<?php

declare(strict_types=1);

namespace App\Controllers\Guards;

use App\Exceptions\Guards\OpenShiftAlreadyExistsException;
use App\Services\Guards\CctvLinkService;
use App\Services\Guards\LogEntryService;
use App\Services\Guards\ShiftService;
use Core\Auth;
use Core\Session;

final class DashboardController extends GuardsController
{
    public function __construct(
        private readonly ShiftService $shifts = new ShiftService(),
        private readonly LogEntryService $logEntries = new LogEntryService(),
        private readonly CctvLinkService $cctvLink = new CctvLinkService()
    ) {
    }

    public function index(): void
    {
        $guardId = Auth::id();
        $dashboard = $this->shifts->dashboardForGuard($guardId);
        $openShift = $dashboard['open_shift'] ?? null;
        $recentEntries = [];
        $stats = ['total_entries' => 0, 'incidents' => 0, 'rounds' => 0, 'cctv_linked' => 0];

        if ($openShift !== null) {
            $detail = $this->shifts->detailForView((int) $openShift['id'], $guardId);
            $stats = $detail['stats'];
            $recentEntries = array_slice(array_reverse($detail['log_entries']), 0, 5);
        }

        $this->guardsView('dashboard/index', [
            'title' => 'Guardias Municipales',
            'openShift' => $openShift,
            'canStartShift' => (bool) ($dashboard['can_start'] ?? false),
            'openShiftsCount' => (int) ($dashboard['open_shifts_count'] ?? 0),
            'shiftStats' => $stats,
            'recentEntries' => $recentEntries,
            'incomingCoordinations' => $this->cctvLink->incomingCoordinations(5),
            'incomingCount' => $this->cctvLink->incomingCount(),
            'canCreateLog' => hasPermission('guards.log.create') && $openShift !== null,
            'canCloseShift' => hasPermission('guards.shifts.close') && $openShift !== null,
            'canViewCctv' => hasPermission('cctv.log.view'),
        ]);
    }
}
