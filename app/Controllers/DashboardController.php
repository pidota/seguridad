<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Meetings\MeetingSignatureService;
use App\Services\Senda\FollowUpService;
use Core\Auth;
use Core\Controller;
use Core\Request;
use Core\Response;

final class DashboardController extends Controller
{
    public function home(Request $request): void
    {
        if (Auth::check()) {
            Response::redirect(url('/dashboard'));
        }

        Response::redirect(url('/login'));
    }

    public function index(): void
    {
        $modules = array_values(array_filter(
            $this->modules(),
            static fn (array $module): bool => hasPermission($module['permission'])
        ));

        $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'user' => Auth::user(),
            'modules' => $modules,
            'followUpAlertPanel' => hasPermission('senda.followups.view')
                ? (new FollowUpService())->dashboardAlertPanel()
                : null,
            'meetingSignaturePanel' => hasPermission('meetings.view_pending_signatures')
                ? $this->meetingSignaturePanel()
                : null,
        ]);
    }

    /**
     * @return array{count: int, url: string}|null
     */
    private function meetingSignaturePanel(): ?array
    {
        $count = (new MeetingSignatureService())->getPendingCountForUser();
        if ($count < 1) {
            return null;
        }

        return [
            'count' => $count,
            'url' => url('/meetings/pending-signatures'),
        ];
    }

    private function modules(): array
    {
        return [
            [
                'name' => 'Central de Cámaras',
                'description' => 'Monitoreo y registro de novedades de videovigilancia comunal.',
                'icon' => 'bi-camera-video',
                'route' => '/cctv',
                'permission' => 'cctv.access',
                'tone' => 'navy',
            ],
            [
                'name' => 'SENDA',
                'description' => 'Atenciones, derivaciones y seguimiento comunitario.',
                'icon' => 'bi-heart-pulse',
                'route' => '/senda',
                'permission' => 'senda.access',
                'tone' => 'teal',
            ],
            [
                'name' => 'Reuniones',
                'description' => 'Actas de reunión, acuerdos, compromisos y firma simple interna.',
                'icon' => 'bi-journal-text',
                'route' => $this->meetingsEntryRoute(),
                'permission' => 'meetings.access',
                'tone' => 'navy',
            ],
            [
                'name' => 'Oficina de la Mujer',
                'description' => 'Orientación, registro y seguimiento de atenciones.',
                'icon' => 'bi-person-hearts',
                'route' => '/women',
                'permission' => 'women.access',
                'tone' => 'wine',
            ],
            [
                'name' => 'Guardias',
                'description' => 'Turnos, rondas y bitácora de novedades en terreno.',
                'icon' => 'bi-person-badge',
                'route' => '/guards',
                'permission' => 'guards.access',
                'tone' => 'gold',
            ],
        ];
    }

    private function meetingsEntryRoute(): string
    {
        if (hasPermission('senda.meetings.create')) {
            return '/senda/meetings/create';
        }

        if (hasPermission('meetings.create')) {
            return '/meetings/create';
        }

        if (hasPermission('senda.meetings.view')) {
            return '/senda/meetings';
        }

        return '/meetings';
    }
}
